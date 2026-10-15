<?php
/**
 * Recebe o resultado do quiz para ser publicado nos Stories do Instagram da escola.
 *
 * Recebe (multipart/form-data, POST):
 *   tentativa_id  número do quiz (devolvido por salvar.php)
 *   instagram     @ da pessoa, que será marcado
 *   imagem        imagem do Story (PNG ou JPEG, 1080x1920)
 *
 * IMPORTANTE: aqui nada é publicado. O pedido fica "pendente" e só vai ao Instagram
 * quando alguém da equipe aprova no painel (admin/publicacoes.php). Isso evita que um
 * visitante coloque qualquer imagem na conta oficial da escola.
 */

require_once __DIR__ . '/instagram.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function responder(int $codigo, array $dados): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    responder(405, ['ok' => false, 'erro' => 'Use POST.']);
}

$tentativaId = filter_var($_POST['tentativa_id'] ?? null, FILTER_VALIDATE_INT);

if ($tentativaId === false || $tentativaId < 1) {
    responder(422, ['ok' => false, 'erro' => 'Quiz inválido.']);
}

$arquivoEnviado = $_FILES['imagem'] ?? null;

if (!$arquivoEnviado || ($arquivoEnviado['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $codigoErro = $arquivoEnviado['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($codigoErro === UPLOAD_ERR_INI_SIZE || $codigoErro === UPLOAD_ERR_FORM_SIZE) {
        responder(413, ['ok' => false, 'erro' => 'A imagem é grande demais para o servidor (aumente upload_max_filesize no php.ini).']);
    }

    responder(422, ['ok' => false, 'erro' => 'Imagem não recebida.']);
}

if (!is_uploaded_file($arquivoEnviado['tmp_name'])) {
    responder(400, ['ok' => false, 'erro' => 'Envio inválido.']);
}

$nomeArquivo = null;

try {
    $pdo = db();
    $cfg = ig_config($pdo);

    // Recurso desligado: o quiz volta ao modo "salvar a imagem e publicar você mesmo(a)"
    if (!ig_pronto($cfg)) {
        responder(200, ['ok' => false, 'indisponivel' => true]);
    }

    $busca = $pdo->prepare(
        'SELECT t.id, t.criado_em, p.id AS participante_id, p.instagram
         FROM tentativas t JOIN participantes p ON p.id = t.participante_id
         WHERE t.id = ?'
    );
    $busca->execute([$tentativaId]);
    $tentativa = $busca->fetch();

    if (!$tentativa) {
        responder(404, ['ok' => false, 'erro' => 'Quiz não encontrado.']);
    }

    // Só vale logo depois de terminar o quiz
    if (strtotime($tentativa['criado_em']) < time() - 6 * 3600) {
        responder(410, ['ok' => false, 'erro' => 'Este resultado é antigo demais para ser publicado. Refaça o teste.']);
    }

    // @ da pessoa: o digitado agora, ou o informado no início do quiz
    $usuario = ig_validar_usuario((string) ($_POST['instagram'] ?? ''))
        ?? ig_validar_usuario((string) ($tentativa['instagram'] ?? ''));

    if ($usuario === null) {
        responder(422, ['ok' => false, 'erro' => 'Informe um @ de Instagram válido para ser marcado.']);
    }

    // Já enviou? Responde com a situação atual (não duplica)
    $existente = $pdo->prepare('SELECT status FROM publicacoes WHERE tentativa_id = ?');
    $existente->execute([$tentativaId]);
    $status = $existente->fetchColumn();

    if ($status !== false) {
        responder(200, ['ok' => true, 'status' => $status, 'conta' => $cfg['usuario'], 'ja_enviado' => true]);
    }

    // Limite de pedidos esperando aprovação (proteção contra enchente)
    $pendentes = (int) $pdo->query("SELECT COUNT(*) FROM publicacoes WHERE status IN ('pendente','erro')")->fetchColumn();

    if ($pendentes >= 300) {
        responder(429, ['ok' => false, 'erro' => 'Muitos pedidos em análise agora. Tente mais tarde.']);
    }

    try {
        $nomeArquivo = ig_guardar_imagem($arquivoEnviado['tmp_name']);
    } catch (RuntimeException $erro) {
        responder(422, ['ok' => false, 'erro' => $erro->getMessage()]);
    }

    $pdo->prepare(
        'INSERT INTO publicacoes (tentativa_id, instagram_pessoa, arquivo, status) VALUES (?, ?, ?, \'pendente\')'
    )->execute([$tentativaId, $usuario, $nomeArquivo]);

    // Guarda o @ no cadastro da pessoa se ela não tinha informado antes
    $pdo->prepare('UPDATE participantes SET instagram = ? WHERE id = ? AND (instagram IS NULL OR instagram = \'\')')
        ->execute([$usuario, $tentativa['participante_id']]);

    responder(201, ['ok' => true, 'status' => 'pendente', 'conta' => $cfg['usuario']]);
} catch (Throwable $erro) {
    // Se a gravação falhou depois de guardar a imagem, não deixa o arquivo solto
    ig_apagar_arquivo($nomeArquivo);

    error_log('[quiz] publicar.php: ' . $erro->getMessage());
    responder(500, ['ok' => false, 'erro' => 'Não foi possível enviar agora.']);
}
