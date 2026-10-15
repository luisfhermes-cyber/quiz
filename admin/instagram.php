<?php
/**
 * Painel: configuração da conta do Instagram que publica os resultados nos Stories.
 * Aqui você define o @ da conta da escola, o token de acesso e o endereço público do site.
 */

require_once __DIR__ . '/_comum.php';
exigir_login();

$pdo = db();
$atual = ig_config($pdo);
$erros = [];
$valores = $atual; // o que aparece nos campos (se der erro, mantém o que foi digitado)

/** Salva a configuração (linha única, id = 1). */
function salvar_config(PDO $pdo, array $c, bool $trocouToken): void
{
    $pdo->prepare(
        'INSERT INTO instagram_config (id, ativo, usuario, ig_user_id, access_token, api_host, api_versao, url_publica, token_atualizado_em)
         VALUES (1, :ativo, :usuario, :id, :token, :host, :versao, :url, :tokenem)
         ON DUPLICATE KEY UPDATE ativo = VALUES(ativo), usuario = VALUES(usuario), ig_user_id = VALUES(ig_user_id),
            access_token = VALUES(access_token), api_host = VALUES(api_host), api_versao = VALUES(api_versao),
            url_publica = VALUES(url_publica), token_atualizado_em = VALUES(token_atualizado_em)'
    )->execute([
        ':ativo'   => (int) $c['ativo'],
        ':usuario' => $c['usuario'] !== '' ? $c['usuario'] : null,
        ':id'      => $c['ig_user_id'] !== '' ? $c['ig_user_id'] : null,
        ':token'   => $c['access_token'] !== '' ? $c['access_token'] : null,
        ':host'    => $c['api_host'],
        ':versao'  => $c['api_versao'],
        ':url'     => $c['url_publica'] !== '' ? $c['url_publica'] : null,
        ':tokenem' => $trocouToken ? date('Y-m-d H:i:s') : ($c['token_atualizado_em'] ?: null),
    ]);
}

/** O Instagram só consegue baixar a imagem se o site estiver na internet. */
function endereco_local(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    return $host === 'localhost'
        || preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|0\.)/', $host) === 1
        || substr($host, -6) === '.local';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        http_response_code(400);
        exit('Requisição inválida.');
    }

    $acao = $_POST['acao'] ?? '';

    // ---------- Renovar token ----------
    if ($acao === 'renovar') {
        $r = ig_renovar_token($atual);

        if ($r['ok']) {
            $atual['access_token'] = $r['token'];
            salvar_config($pdo, $atual, true);
            flash('ok', 'Token renovado por mais ' . $r['dias'] . ' dias.');
        } else {
            flash('erro', 'Não foi possível renovar: ' . $r['erro']);
        }

        header('Location: instagram.php');
        exit;
    }

    if ($acao !== 'salvar' && $acao !== 'salvar_testar') {
        http_response_code(400);
        exit('Ação inválida.');
    }

    // ---------- Validar o formulário ----------
    $novo = $atual;
    $trocouToken = false;

    $usuarioDigitado = trim((string) ($_POST['usuario'] ?? ''));
    $novo['usuario'] = '';

    if ($usuarioDigitado !== '') {
        $normalizado = ig_validar_usuario($usuarioDigitado);

        if ($normalizado === null) {
            $erros[] = 'O @ da conta está inválido. Use só letras, números, ponto e sublinhado (ex.: @senai.maravilha).';
        } else {
            $novo['usuario'] = $normalizado;
        }
    }

    $novo['ig_user_id'] = preg_replace('/\D/', '', (string) ($_POST['ig_user_id'] ?? ''));

    if (strlen($novo['ig_user_id']) > 30) {
        $erros[] = 'O ID da conta tem dígitos demais.';
    }

    $novo['api_host'] = in_array($_POST['api_host'] ?? '', ['graph.instagram.com', 'graph.facebook.com'], true)
        ? $_POST['api_host'] : 'graph.instagram.com';

    $versao = trim((string) ($_POST['api_versao'] ?? ''));
    $novo['api_versao'] = preg_match('/^v\d{1,2}\.\d$/', $versao) ? $versao : 'v21.0';

    $url = rtrim(trim((string) ($_POST['url_publica'] ?? '')), '/');
    $novo['url_publica'] = '';

    if ($url !== '') {
        $partes = parse_url($url);

        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($partes['scheme'] ?? '', ['http', 'https'], true)
            || isset($partes['query']) || isset($partes['fragment'])) {
            $erros[] = 'O endereço público do site está inválido. Exemplo: https://quiz.suaescola.com.br (ou https://suaescola.com.br/quiz).';
        } else {
            $novo['url_publica'] = $url;
        }
    }

    $tokenDigitado = preg_replace('/\s+/', '', (string) ($_POST['access_token'] ?? ''));

    if ($tokenDigitado !== '') {
        if (strlen($tokenDigitado) < 20 || strlen($tokenDigitado) > 1000 || !preg_match('/^[A-Za-z0-9_\-.|]+$/', $tokenDigitado)) {
            $erros[] = 'O token parece inválido. Copie o token inteiro, sem espaços.';
        } else {
            $novo['access_token'] = $tokenDigitado;
            $trocouToken = true;
        }
    }

    $novo['ativo'] = isset($_POST['ativo']) ? 1 : 0;
    $valores = $novo;
    $valores['access_token'] = $atual['access_token']; // o token nunca volta para a tela

    if (!$erros) {
        $avisos = [];

        // ---------- Testar a conexão (descobre o ID e o @ se estiverem vazios) ----------
        $testeOk = null;

        if ($acao === 'salvar_testar') {
            $teste = ig_testar_conexao($novo);
            $testeOk = $teste['ok'];

            if ($teste['ok']) {
                if ($novo['ig_user_id'] === '' && $teste['id'] !== '') {
                    $novo['ig_user_id'] = $teste['id'];
                }

                if ($novo['usuario'] === '' && $teste['username'] !== '') {
                    $novo['usuario'] = '@' . $teste['username'];
                }

                if ($teste['username'] !== '' && strcasecmp(ltrim($novo['usuario'], '@'), $teste['username']) !== 0) {
                    $avisos[] = 'O token pertence a @' . $teste['username'] . ', mas você digitou ' . $novo['usuario'] . '. Confira qual conta deve publicar.';
                }

                $avisos[] = 'Conexão OK: conta @' . $teste['username'] . ' (ID ' . $novo['ig_user_id'] . ').';
            } else {
                $avisos[] = 'Falha ao conectar: ' . $teste['erro'];
            }
        }

        // ---------- Só liga se tudo estiver preenchido (e a conexão, se testada, funcionou) ----------
        if ($novo['ativo']) {
            $faltando = [];

            foreach (['usuario' => 'o @ da conta', 'ig_user_id' => 'o ID da conta', 'access_token' => 'o token', 'url_publica' => 'o endereço público do site'] as $campo => $nome) {
                if ($novo[$campo] === '') {
                    $faltando[] = $nome;
                }
            }

            if ($faltando) {
                $novo['ativo'] = 0;
                $avisos[] = 'A publicação não foi ligada: falta informar ' . implode(', ', $faltando) . '.';
            } elseif ($testeOk === false) {
                $novo['ativo'] = 0;
                $avisos[] = 'A publicação não foi ligada porque a conexão falhou.';
            } elseif (endereco_local($novo['url_publica'])) {
                $avisos[] = 'Atenção: o endereço "' . $novo['url_publica'] . '" é local. O Instagram não consegue baixar a imagem por ele; use o endereço público do site.';
            }
        }

        salvar_config($pdo, $novo, $trocouToken);

        $temProblema = $testeOk === false || ($_POST['ativo'] ?? null) !== null && !$novo['ativo'];
        flash($temProblema ? 'alerta' : 'ok', 'Configuração salva. ' . implode(' ', $avisos));

        header('Location: instagram.php');
        exit;
    }
}

// ---------- Tela ----------

$diasToken = null;

if (!empty($atual['access_token']) && !empty($atual['token_atualizado_em'])) {
    $diasToken = (int) floor((time() - strtotime($atual['token_atualizado_em'])) / 86400);
}

$vencendo = $diasToken !== null && $atual['api_host'] === 'graph.instagram.com' && $diasToken >= 50;
$publicando = ig_pronto($atual);

cabecalho('Instagram');
?>
<h1>Instagram</h1>

<?php flash_mostrar(); ?>

<?php if ($erros): ?>
  <div class="aviso erro">
    <?php foreach ($erros as $e): ?><div><?= h($e) ?></div><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($vencendo): ?>
  <div class="aviso alerta">O token foi gerado há <?= (int) $diasToken ?> dias e vale 60. Clique em "Renovar token" abaixo antes que as publicações parem de funcionar.</div>
<?php endif; ?>

<section class="card">
  <h2>Situação</h2>
  <div class="estado">
    <?php if ($publicando): ?>
      <span class="etiqueta publicada">Ligada</span>
      <span>Os resultados enviados vão para a fila de <a href="publicacoes.php">Publicações</a> e saem nos Stories de <strong><?= h($atual['usuario']) ?></strong> depois que você aprova.</span>
    <?php else: ?>
      <span class="etiqueta rejeitada">Desligada</span>
      <span>Enquanto estiver desligada, o botão do quiz só salva a imagem para a pessoa publicar no próprio Instagram.</span>
    <?php endif; ?>
  </div>
  <?php if ($atual['access_token'] !== ''): ?>
    <p class="muted" style="margin-top: 10px;">
      Token salvo (termina em <code>••••<?= h(substr($atual['access_token'], -4)) ?></code><?= $diasToken !== null ? ', atualizado há ' . $diasToken . ' dia(s)' : '' ?>).
    </p>
  <?php endif; ?>
</section>

<form method="post" class="card" autocomplete="off" style="display: flex; flex-direction: column; gap: 16px;">
  <h2>Conta que publica</h2>
  <?= csrf_campo() ?>

  <label class="marcar">
    <input type="checkbox" name="ativo" value="1" <?= $valores['ativo'] ? 'checked' : '' ?>>
    <span>Publicar os resultados nos Stories desta conta (com aprovação da equipe)</span>
  </label>

  <div class="campos">
    <div>
      <label for="usuario">@ da conta da escola</label>
      <input type="text" id="usuario" name="usuario" value="<?= h($valores['usuario']) ?>" placeholder="@senai.maravilha" maxlength="31">
      <p class="dica">É a conta que vai publicar e marcar a pessoa. Precisa ser profissional (Comercial ou Criador).</p>
    </div>
    <div>
      <label for="ig_user_id">ID da conta <span class="muted">(opcional)</span></label>
      <input type="text" id="ig_user_id" name="ig_user_id" value="<?= h($valores['ig_user_id']) ?>" inputmode="numeric" placeholder="Deixe vazio para descobrir sozinho">
      <p class="dica">Se vazio, "Salvar e testar" descobre pelo token.</p>
    </div>

    <div class="largo">
      <label for="access_token">Token de acesso</label>
      <input type="password" id="access_token" name="access_token" value="" placeholder="<?= $atual['access_token'] !== '' ? 'Salvo. Cole outro aqui só se quiser trocar.' : 'Cole aqui o token gerado no painel de desenvolvedores da Meta' ?>" autocomplete="new-password">
      <p class="dica">Fica guardado no servidor e nunca é mostrado de volta. Quem tem o token controla a conta, então não envie a ninguém.</p>
    </div>

    <div class="largo">
      <label for="url_publica">Endereço público do site</label>
      <input type="url" id="url_publica" name="url_publica" value="<?= h($valores['url_publica']) ?>" placeholder="https://quiz.suaescola.com.br">
      <p class="dica">O Instagram baixa a imagem por este endereço, então o site precisa estar na internet (não vale localhost). É o endereço da pasta onde estão o <code>index.html</code> e a pasta <code>api</code>.</p>
    </div>

    <div>
      <label for="api_host">Tipo de token</label>
      <select id="api_host" name="api_host">
        <option value="graph.instagram.com" <?= $valores['api_host'] === 'graph.instagram.com' ? 'selected' : '' ?>>Login do Instagram (graph.instagram.com)</option>
        <option value="graph.facebook.com" <?= $valores['api_host'] === 'graph.facebook.com' ? 'selected' : '' ?>>Login do Facebook / Página (graph.facebook.com)</option>
      </select>
    </div>
    <div>
      <label for="api_versao">Versão da API</label>
      <input type="text" id="api_versao" name="api_versao" value="<?= h($valores['api_versao']) ?>" maxlength="6">
      <p class="dica">Só mude se a Meta avisar que a versão foi desativada.</p>
    </div>
  </div>

  <div class="acoes">
    <button class="btn" type="submit" name="acao" value="salvar_testar">Salvar e testar conexão</button>
    <button class="btn sec" type="submit" name="acao" value="salvar">Só salvar</button>
  </div>
</form>

<?php if ($atual['access_token'] !== '' && $atual['api_host'] === 'graph.instagram.com'): ?>
<section class="card">
  <h2>Renovar token</h2>
  <p class="muted" style="margin-bottom: 12px;">Os tokens do Login do Instagram valem 60 dias. Renove de tempos em tempos (o token precisa ter mais de 24 horas e não pode ter vencido).</p>
  <form method="post">
    <?= csrf_campo() ?>
    <input type="hidden" name="acao" value="renovar">
    <button class="btn sec" type="submit">Renovar token agora</button>
  </form>
</section>
<?php endif; ?>

<section class="card">
  <h2>Como conseguir o token</h2>
  <details>
    <summary style="cursor: pointer; font-weight: 700;">Ver o passo a passo</summary>
    <ol style="margin: 12px 0 0 20px; display: flex; flex-direction: column; gap: 8px;">
      <li>No aplicativo do Instagram da escola, mude para <strong>conta profissional</strong> (Configurações › Tipo de conta e ferramentas).</li>
      <li>Entre em <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">developers.facebook.com/apps</a>, crie um app e adicione o produto <strong>Instagram</strong> (configuração com Login do Instagram).</li>
      <li>Adicione a conta da escola ao app e gere o <strong>token de acesso</strong> com as permissões <code>instagram_business_basic</code> e <code>instagram_business_content_publish</code>.</li>
      <li>Cole o token acima e clique em <strong>Salvar e testar conexão</strong>. O painel confere a conta e preenche o ID sozinho.</li>
    </ol>
    <p class="dica" style="margin-top: 12px;">
      Regras da Meta: só contas profissionais publicam por API; só é possível marcar contas <strong>públicas</strong>; o limite é de 100 publicações por dia.
      Para contas sem função no app, a Meta exige a análise do app (App Review). Detalhes em
      <a href="https://developers.facebook.com/docs/instagram-platform/content-publishing/" target="_blank" rel="noopener">documentação oficial</a>.
    </p>
  </details>
</section>
<?php
rodape();
