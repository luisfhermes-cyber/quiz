<?php
/**
 * Integração com a API oficial do Instagram (publicação de Stories).
 * Documentação: https://developers.facebook.com/docs/instagram-platform/content-publishing/
 *
 * Regras da API que este arquivo respeita:
 *  - a conta que publica precisa ser profissional (Comercial ou Criador);
 *  - a imagem precisa ser JPEG e estar numa URL pública (o Instagram baixa a imagem do seu site);
 *  - só é possível marcar contas públicas.
 */

require_once __DIR__ . '/db.php';

const IG_PASTA_ARQUIVOS = __DIR__ . '/../publicacoes/arquivos';
const IG_CAMINHO_PUBLICO = 'publicacoes/arquivos/';
const IG_LARGURA = 1080;
const IG_ALTURA = 1920;

// ---------------------------------------------------------------------
// Configuração
// ---------------------------------------------------------------------

/** Máximo de contas/tokens que podem ser cadastrados. */
const IG_MAX_CONTAS = 10;

/** Limite de publicações por conta a cada 24 h (regra da Meta: 100). */
const IG_LIMITE_PADRAO = 100;

/**
 * Lê a configuração geral (linha única: liga/desliga e endereço do site) e as contas cadastradas.
 * Se as tabelas ainda não existirem, devolve tudo desligado.
 *
 * Devolve também:
 *   'contas'  => contas ligadas e completas (na ordem de uso)
 *   'usuario' => o @ da conta, se houver só uma; vazio se houver várias (a que publica é escolhida na hora)
 */
function ig_config(PDO $pdo): array
{
    $padrao = [
        'ativo' => 0, 'usuario' => '', 'ig_user_id' => '', 'access_token' => '',
        'api_host' => 'graph.instagram.com', 'api_versao' => 'v21.0',
        'url_publica' => '', 'token_atualizado_em' => null, 'contas' => [],
    ];

    try {
        $linha = $pdo->query('SELECT * FROM instagram_config WHERE id = 1')->fetch();
    } catch (Throwable $erro) {
        return $padrao;
    }

    $cfg = $padrao;

    if ($linha) {
        $cfg['ativo'] = (int) $linha['ativo'];
        $cfg['url_publica'] = (string) ($linha['url_publica'] ?? '');
    }

    try {
        $cfg['contas'] = array_values(array_filter(ig_contas($pdo), 'ig_conta_completa'));
    } catch (Throwable $erro) {
        $cfg['contas'] = [];
    }

    $cfg['usuario'] = count($cfg['contas']) === 1 ? $cfg['contas'][0]['usuario'] : '';

    return $cfg;
}

/** Todas as contas cadastradas, na ordem de uso, com quantas publicações fizeram nas últimas 24 h. */
function ig_contas(PDO $pdo, bool $apenasLigadas = true): array
{
    $sql = 'SELECT c.*,
                   (SELECT COUNT(*) FROM publicacoes p
                     WHERE p.conta_id = c.id AND p.status = \'publicada\'
                       AND p.publicado_em >= (NOW() - INTERVAL 24 HOUR)) AS usadas_24h
            FROM instagram_contas c ' . ($apenasLigadas ? 'WHERE c.ativo = 1 ' : '') . 'ORDER BY c.ordem, c.id';

    $contas = $pdo->query($sql)->fetchAll();

    foreach ($contas as &$c) {
        foreach (['usuario', 'ig_user_id', 'access_token', 'ultimo_erro'] as $campo) {
            $c[$campo] = (string) ($c[$campo] ?? '');
        }
    }

    return $contas;
}

function ig_conta_completa(array $c): bool
{
    return (int) $c['ativo'] === 1 && $c['usuario'] !== '' && $c['ig_user_id'] !== '' && $c['access_token'] !== '';
}

/** Situação da conta agora: 'disponivel', 'limite' (já publicou o máximo em 24 h), 'bloqueada' ou 'desligada'. */
function ig_conta_situacao(array $c): string
{
    if (!ig_conta_completa($c)) {
        return 'desligada';
    }

    if (!empty($c['bloqueada_ate']) && strtotime($c['bloqueada_ate']) > time()) {
        return 'bloqueada';
    }

    if ((int) $c['usadas_24h'] >= (int) $c['limite_dia']) {
        return 'limite';
    }

    return 'disponivel';
}

/** Junta a configuração geral com os dados de uma conta (formato que as funções de chamada esperam). */
function ig_cfg_da_conta(array $cfg, array $conta): array
{
    return [
        'usuario' => $conta['usuario'], 'ig_user_id' => $conta['ig_user_id'], 'access_token' => $conta['access_token'],
        'api_host' => $conta['api_host'], 'api_versao' => $conta['api_versao'],
    ] + $cfg;
}

/** A publicação só funciona se estiver ligada, com o endereço do site e ao menos uma conta completa. */
function ig_pronto(array $cfg): bool
{
    return (int) $cfg['ativo'] === 1
        && $cfg['url_publica'] !== ''
        && !empty($cfg['contas']);
}

/** Valida e normaliza um @ de Instagram. Devolve "@nome" ou null. */
function ig_validar_usuario(string $texto): ?string
{
    $texto = trim($texto);

    if ($texto === '') {
        return null;
    }

    if ($texto[0] !== '@') {
        $texto = '@' . $texto;
    }

    return preg_match('/^@[A-Za-z0-9._]{1,30}$/', $texto) ? $texto : null;
}

// ---------------------------------------------------------------------
// Chamadas HTTP
// ---------------------------------------------------------------------

/** Endereço base da API. A variável QUIZ_IG_API_BASE existe só para testes. */
function ig_base(array $cfg): string
{
    $teste = getenv('QUIZ_IG_API_BASE');

    return $teste ? rtrim($teste, '/') : 'https://' . $cfg['api_host'];
}

function ig_url(array $cfg, string $caminho): string
{
    $versao = preg_match('/^v\d+\.\d+$/', (string) $cfg['api_versao']) ? '/' . $cfg['api_versao'] : '';

    return ig_base($cfg) . $versao . '/' . ltrim($caminho, '/');
}

/**
 * Faz a chamada e devolve ['http' => código, 'json' => array|null, 'falha' => texto|null].
 * O token nunca é gravado em log.
 */
function ig_http(string $metodo, string $url, array $params): array
{
    $ch = curl_init();

    if ($metodo === 'GET') {
        $url .= '?' . http_build_query($params);
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    curl_setopt_array($ch, [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 40,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);

    $corpo = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $falha = $corpo === false ? curl_error($ch) : null;
    curl_close($ch);

    $json = is_string($corpo) ? json_decode($corpo, true) : null;

    return ['http' => $http, 'json' => is_array($json) ? $json : null, 'falha' => $falha];
}

/** Texto curto e seguro do erro devolvido pela API. */
function ig_erro_texto(array $resposta): string
{
    if ($resposta['falha'] !== null) {
        return 'Não foi possível falar com o Instagram (' . $resposta['falha'] . ').';
    }

    $erro = $resposta['json']['error'] ?? null;

    if (is_array($erro)) {
        $codigo = isset($erro['code']) ? '(#' . $erro['code'] . ') ' : '';

        return mb_substr($codigo . (string) ($erro['message'] ?? 'Erro desconhecido'), 0, 400);
    }

    return 'Resposta inesperada do Instagram (HTTP ' . $resposta['http'] . ').';
}

/**
 * O erro é da CONTA (limite de publicações, token vencido/sem permissão) e não da imagem?
 * Nesse caso vale tentar a próxima conta cadastrada.
 */
function ig_erro_de_conta(array $resposta): bool
{
    if ($resposta['falha'] !== null) {
        return false;
    }

    $erro = $resposta['json']['error'] ?? null;

    if (is_array($erro)) {
        $codigo = (int) ($erro['code'] ?? 0);
        $sub = (int) ($erro['error_subcode'] ?? 0);
        $texto = strtolower((string) ($erro['message'] ?? ''));

        if (in_array($codigo, [4, 9, 10, 17, 32, 190, 200, 368, 613], true) || $sub === 2207051) {
            return true;
        }

        return str_contains($texto, 'limit') || str_contains($texto, 'access token') || str_contains($texto, 'rate');
    }

    return in_array($resposta['http'], [401, 403, 429], true);
}

/** Token vencido/inválido (a conta precisa de um token novo, não basta esperar). */
function ig_erro_de_token(array $resposta): bool
{
    $erro = $resposta['json']['error'] ?? null;

    return is_array($erro) && ((int) ($erro['code'] ?? 0) === 190 || str_contains(strtolower((string) ($erro['message'] ?? '')), 'access token'));
}

/** Quanto a conta já publicou segundo o próprio Instagram: [usado, total] ou null se não deu para saber. */
function ig_cota(array $cfg): ?array
{
    $r = ig_http('GET', ig_url($cfg, $cfg['ig_user_id'] . '/content_publishing_limit'), [
        'fields' => 'quota_usage,config', 'access_token' => $cfg['access_token'],
    ]);

    if (!ig_ok($r) || empty($r['json']['data'][0])) {
        return null;
    }

    $d = $r['json']['data'][0];

    if (!isset($d['quota_usage'], $d['config']['quota_total'])) {
        return null;
    }

    return [(int) $d['quota_usage'], (int) $d['config']['quota_total']];
}

function ig_ok(array $resposta): bool
{
    return $resposta['falha'] === null
        && $resposta['http'] >= 200 && $resposta['http'] < 300
        && is_array($resposta['json'])
        && !isset($resposta['json']['error']);
}

// ---------------------------------------------------------------------
// Conta: testar conexão e renovar token
// ---------------------------------------------------------------------

/** Confere o token e descobre o @ e o ID da conta. */
function ig_testar_conexao(array $cfg): array
{
    if ($cfg['access_token'] === '') {
        return ['ok' => false, 'erro' => 'Informe o token de acesso.'];
    }

    if ($cfg['ig_user_id'] !== '') {
        $r = ig_http('GET', ig_url($cfg, $cfg['ig_user_id']), ['fields' => 'username', 'access_token' => $cfg['access_token']]);
    } else {
        $r = ig_http('GET', ig_url($cfg, 'me'), ['fields' => 'user_id,username', 'access_token' => $cfg['access_token']]);
    }

    if (!ig_ok($r)) {
        return ['ok' => false, 'erro' => ig_erro_texto($r), 'conta' => ig_erro_de_conta($r), 'token' => ig_erro_de_token($r)];
    }

    return [
        'ok'       => true,
        'username' => (string) ($r['json']['username'] ?? ''),
        'id'       => (string) ($r['json']['user_id'] ?? $r['json']['id'] ?? $cfg['ig_user_id']),
    ];
}

/** Renova o token de longa duração (vale 60 dias). Só para tokens do "Login do Instagram". */
function ig_renovar_token(array $cfg): array
{
    if ($cfg['api_host'] !== 'graph.instagram.com') {
        return ['ok' => false, 'erro' => 'A renovação automática só existe para tokens do Login do Instagram (graph.instagram.com).'];
    }

    $r = ig_http('GET', ig_base($cfg) . '/refresh_access_token', [
        'grant_type'   => 'ig_refresh_token',
        'access_token' => $cfg['access_token'],
    ]);

    if (!ig_ok($r) || empty($r['json']['access_token'])) {
        return ['ok' => false, 'erro' => ig_erro_texto($r), 'conta' => ig_erro_de_conta($r), 'token' => ig_erro_de_token($r)];
    }

    return ['ok' => true, 'token' => (string) $r['json']['access_token'], 'dias' => (int) round(((int) ($r['json']['expires_in'] ?? 0)) / 86400)];
}

// ---------------------------------------------------------------------
// Publicação do Story
// ---------------------------------------------------------------------

/**
 * Publica a imagem como Story e marca o @ (se informado).
 * Se a marcação for recusada (conta privada, @ que não existe...), publica sem marcar.
 * Devolve ['ok' => true, 'media_id' => ..., 'marcou' => bool] ou ['ok' => false, 'erro' => ...].
 */
function ig_publicar_story(array $cfg, string $urlImagem, ?string $usuarioMarcado): array
{
    $idConta = $cfg['ig_user_id'];

    $params = [
        'image_url'    => $urlImagem,
        'media_type'   => 'STORIES',
        'access_token' => $cfg['access_token'],
    ];

    $marcou = false;

    if ($usuarioMarcado !== null) {
        // Em Stories as coordenadas (x, y) são opcionais.
        $params['user_tags'] = json_encode([['username' => ltrim($usuarioMarcado, '@')]]);
        $marcou = true;
    }

    // 1) cria o "container" da mídia
    $r = ig_http('POST', ig_url($cfg, $idConta . '/media'), $params);

    if (!ig_ok($r) && $marcou) {
        $primeiroErro = ig_erro_texto($r);
        unset($params['user_tags']);
        $marcou = false;

        $r = ig_http('POST', ig_url($cfg, $idConta . '/media'), $params);

        if (!ig_ok($r)) {
            return ['ok' => false, 'erro' => $primeiroErro, 'conta' => ig_erro_de_conta($r), 'token' => ig_erro_de_token($r)];
        }
    }

    if (!ig_ok($r) || empty($r['json']['id'])) {
        return ['ok' => false, 'erro' => ig_erro_texto($r), 'conta' => ig_erro_de_conta($r), 'token' => ig_erro_de_token($r)];
    }

    $container = (string) $r['json']['id'];

    // 2) espera o Instagram terminar de processar a imagem (até ~30 s)
    for ($tentativa = 0; $tentativa < 15; $tentativa++) {
        $s = ig_http('GET', ig_url($cfg, $container), ['fields' => 'status_code', 'access_token' => $cfg['access_token']]);

        if (!ig_ok($s)) {
            return ['ok' => false, 'erro' => ig_erro_texto($s), 'conta' => ig_erro_de_conta($s), 'token' => ig_erro_de_token($s)];
        }

        $estado = (string) ($s['json']['status_code'] ?? '');

        if ($estado === 'FINISHED' || $estado === 'PUBLISHED') {
            break;
        }

        if ($estado === 'ERROR' || $estado === 'EXPIRED') {
            return ['ok' => false, 'erro' => 'O Instagram não conseguiu processar a imagem (' . $estado . '). Confira se a URL pública do site está correta e acessível.'];
        }

        sleep(2);
    }

    // 3) publica
    $p = ig_http('POST', ig_url($cfg, $idConta . '/media_publish'), [
        'creation_id'  => $container,
        'access_token' => $cfg['access_token'],
    ]);

    if (!ig_ok($p) || empty($p['json']['id'])) {
        return ['ok' => false, 'erro' => ig_erro_texto($p), 'conta' => ig_erro_de_conta($p), 'token' => ig_erro_de_token($p)];
    }

    return ['ok' => true, 'media_id' => (string) $p['json']['id'], 'marcou' => $marcou];
}

// ---------------------------------------------------------------------
// Imagens guardadas no site (precisam ter URL pública para o Instagram baixar)
// ---------------------------------------------------------------------

/**
 * Valida a imagem enviada e a regrava como JPEG (remove qualquer conteúdo escondido).
 * Devolve o nome do arquivo guardado. Lança Exception com mensagem amigável se algo estiver errado.
 */
function ig_guardar_imagem(string $caminhoTemporario): string
{
    $tamanho = @filesize($caminhoTemporario);

    if ($tamanho === false || $tamanho < 1000 || $tamanho > 6 * 1024 * 1024) {
        throw new RuntimeException('Imagem inválida.');
    }

    $info = @getimagesize($caminhoTemporario);

    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
        throw new RuntimeException('Envie uma imagem PNG ou JPEG.');
    }

    if ($info[0] !== IG_LARGURA || $info[1] !== IG_ALTURA) {
        throw new RuntimeException('A imagem precisa ter ' . IG_LARGURA . 'x' . IG_ALTURA . ' pixels.');
    }

    $imagem = @imagecreatefromstring((string) file_get_contents($caminhoTemporario));

    if (!$imagem) {
        throw new RuntimeException('Não foi possível ler a imagem.');
    }

    if (!is_dir(IG_PASTA_ARQUIVOS) && !@mkdir(IG_PASTA_ARQUIVOS, 0755, true)) {
        throw new RuntimeException('Não foi possível criar a pasta das imagens.');
    }

    // Impede listagem e execução de scripts nessa pasta (Apache)
    $htaccess = IG_PASTA_ARQUIVOS . '/.htaccess';

    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar)\$\">\n  Require all denied\n</FilesMatch>\n");
    }

    $nome = bin2hex(random_bytes(16)) . '.jpg';

    // Fundo branco por segurança e JPEG de alta qualidade
    $final = imagecreatetruecolor(IG_LARGURA, IG_ALTURA);
    imagefill($final, 0, 0, imagecolorallocate($final, 255, 255, 255));
    imagecopy($final, $imagem, 0, 0, 0, 0, IG_LARGURA, IG_ALTURA);

    if (!imagejpeg($final, IG_PASTA_ARQUIVOS . '/' . $nome, 90)) {
        throw new RuntimeException('Não foi possível guardar a imagem.');
    }

    imagedestroy($imagem);
    imagedestroy($final);

    return $nome;
}

function ig_nome_arquivo_valido(?string $nome): bool
{
    return is_string($nome) && preg_match('/^[a-f0-9]{32}\.jpg$/', $nome) === 1;
}

function ig_apagar_arquivo(?string $nome): void
{
    if (ig_nome_arquivo_valido($nome)) {
        @unlink(IG_PASTA_ARQUIVOS . '/' . $nome);
    }
}

function ig_url_imagem(array $cfg, string $nome): string
{
    return rtrim((string) $cfg['url_publica'], '/') . '/' . IG_CAMINHO_PUBLICO . $nome;
}

// ---------------------------------------------------------------------
// Fluxo completo de uma publicação (usado pelo painel ao aprovar)
// ---------------------------------------------------------------------

/**
 * Publica uma publicação pendente (ou com erro) e atualiza o banco.
 * Devolve ['ok' => bool, 'mensagem' => texto].
 */
function ig_processar_publicacao(PDO $pdo, int $id): array
{
    $cfg = ig_config($pdo);

    if (!ig_pronto($cfg)) {
        return ['ok' => false, 'mensagem' => 'A publicação no Instagram não está ligada ou falta algum dado em "Instagram".'];
    }

    // Trava: só um clique processa (evita publicar duas vezes). Uma publicação travada há 10 min pode ser refeita.
    $trava = $pdo->prepare(
        "UPDATE publicacoes SET status = 'publicando', erro = NULL
         WHERE id = :id AND (status IN ('pendente', 'erro')
                             OR (status = 'publicando' AND atualizado_em < (NOW() - INTERVAL 10 MINUTE)))"
    );
    $trava->execute([':id' => $id]);

    if ($trava->rowCount() !== 1) {
        return ['ok' => false, 'mensagem' => 'Esta publicação já foi tratada ou está sendo publicada agora.'];
    }

    $busca = $pdo->prepare('SELECT * FROM publicacoes WHERE id = ?');
    $busca->execute([$id]);
    $pub = $busca->fetch();

    if (!$pub || !ig_nome_arquivo_valido($pub['arquivo']) || !is_file(IG_PASTA_ARQUIVOS . '/' . $pub['arquivo'])) {
        $pdo->prepare("UPDATE publicacoes SET status = 'erro', erro = ? WHERE id = ?")
            ->execute(['A imagem não existe mais. Rejeite este item.', $id]);

        return ['ok' => false, 'mensagem' => 'A imagem desta publicação não existe mais.'];
    }

    @set_time_limit(180);

    $urlImagem = ig_url_imagem($cfg, $pub['arquivo']);
    $resultado = null;
    $conta = null;
    $ultimoErro = '';
    $puladas = [];

    // Usa a primeira conta (na ordem cadastrada) que ainda não atingiu o limite; se falhar por causa da conta, passa para a próxima.
    foreach (ig_contas($pdo) as $candidata) {
        $situacao = ig_conta_situacao($candidata);

        if ($situacao !== 'disponivel') {
            if ($situacao !== 'desligada') {
                $puladas[] = $candidata['usuario'] . ($situacao === 'limite' ? ' (limite do dia)' : ' (bloqueada)');
            }

            continue;
        }

        $cfgConta = ig_cfg_da_conta($cfg, $candidata);

        // Confere com o próprio Instagram (o limite também conta posts feitos fora do quiz)
        $cota = ig_cota($cfgConta);

        if ($cota !== null && $cota[0] >= min($cota[1], (int) $candidata['limite_dia'])) {
            ig_bloquear_conta($pdo, (int) $candidata['id'], 'Limite de publicações do Instagram atingido.', 3600);
            $puladas[] = $candidata['usuario'] . ' (limite do dia)';
            continue;
        }

        $r = ig_publicar_story($cfgConta, $urlImagem, $pub['instagram_pessoa']);

        if ($r['ok']) {
            $resultado = $r;
            $conta = $candidata;
            break;
        }

        $ultimoErro = $r['erro'];

        if (empty($r['conta'])) {
            // Problema da imagem/pedido: outra conta não resolve
            $resultado = $r;
            break;
        }

        ig_bloquear_conta($pdo, (int) $candidata['id'], $r['erro'], !empty($r['token']) ? 86400 : 3600);
        $puladas[] = $candidata['usuario'] . (!empty($r['token']) ? ' (token com problema)' : ' (limite/erro da conta)');
    }

    if ($resultado === null) {
        $msg = 'Nenhuma conta disponível agora' . ($puladas ? ': ' . implode(', ', $puladas) : '') . '.'
            . ($ultimoErro !== '' ? ' Último erro: ' . $ultimoErro : '');

        $pdo->prepare("UPDATE publicacoes SET status = 'erro', erro = ? WHERE id = ?")->execute([mb_substr($msg, 0, 500), $id]);
        error_log('[quiz] publicação #' . $id . ' falhou: ' . $msg);

        return ['ok' => false, 'mensagem' => $msg];
    }

    if (!$resultado['ok']) {
        $pdo->prepare("UPDATE publicacoes SET status = 'erro', erro = ? WHERE id = ?")
            ->execute([mb_substr($resultado['erro'], 0, 500), $id]);

        error_log('[quiz] publicação #' . $id . ' falhou: ' . $resultado['erro']);

        return ['ok' => false, 'mensagem' => $resultado['erro']];
    }

    $pdo->prepare(
        "UPDATE publicacoes SET status = 'publicada', marcou = ?, ig_media_id = ?, erro = NULL, arquivo = NULL,
                publicado_em = NOW(), conta_id = ?, conta_usuario = ?
         WHERE id = ?"
    )->execute([$resultado['marcou'] ? 1 : 0, $resultado['media_id'], $conta['id'], $conta['usuario'], $id]);

    // Publicou com sucesso: se a conta estava marcada com erro, libera
    $pdo->prepare('UPDATE instagram_contas SET bloqueada_ate = NULL, ultimo_erro = NULL WHERE id = ?')->execute([$conta['id']]);

    // A imagem já está no Instagram: não precisa mais ficar no servidor (tem nome e @ da pessoa)
    ig_apagar_arquivo($pub['arquivo']);

    return [
        'ok'       => true,
        'mensagem' => ($resultado['marcou']
            ? 'Publicado nos Stories de ' . $conta['usuario'] . ' marcando ' . $pub['instagram_pessoa'] . '.'
            : 'Publicado nos Stories de ' . $conta['usuario'] . ', mas SEM marcar ' . $pub['instagram_pessoa'] . ' (o Instagram recusou a marcação: o perfil precisa ser público e existir).')
            . ($puladas ? ' Contas puladas: ' . implode(', ', $puladas) . '.' : ''),
    ];
}

/** Tira a conta de uso por um tempo (limite atingido ou problema no token) e guarda o motivo. */
function ig_bloquear_conta(PDO $pdo, int $id, string $motivo, int $segundos): void
{
    $segundos = max(60, (int) $segundos);

    $pdo->prepare(
        'UPDATE instagram_contas SET bloqueada_ate = (NOW() + INTERVAL ' . $segundos . ' SECOND), ultimo_erro = :erro WHERE id = :id'
    )->execute([':erro' => mb_substr($motivo, 0, 500), ':id' => $id]);
}
