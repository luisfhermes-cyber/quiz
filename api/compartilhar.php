<?php
/**
 * Registra um clique em "Compartilhar no Instagram".
 * Recebe (JSON, POST): { "tentativa_id": 123 }
 */

require_once __DIR__ . '/db.php';

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

$corpo = file_get_contents('php://input');
$dados = ($corpo !== false && strlen($corpo) <= 1024) ? json_decode($corpo, true) : null;

$tentativaId = is_array($dados) ? filter_var($dados['tentativa_id'] ?? null, FILTER_VALIDATE_INT) : false;

if ($tentativaId === false || $tentativaId < 1) {
    responder(422, ['ok' => false, 'erro' => 'Tentativa inválida.']);
}

try {
    $pdo = db();

    $existe = $pdo->prepare('SELECT COUNT(*) FROM tentativas WHERE id = ?');
    $existe->execute([$tentativaId]);

    if (!(int) $existe->fetchColumn()) {
        responder(404, ['ok' => false, 'erro' => 'Tentativa não encontrada.']);
    }

    // Limite simples para evitar enchente de registros na mesma tentativa
    $total = $pdo->prepare('SELECT COUNT(*) FROM compartilhamentos WHERE tentativa_id = ?');
    $total->execute([$tentativaId]);

    if ((int) $total->fetchColumn() < 20) {
        $pdo->prepare('INSERT INTO compartilhamentos (tentativa_id, canal) VALUES (?, ?)')
            ->execute([$tentativaId, 'instagram']);
    }

    responder(201, ['ok' => true]);
} catch (Throwable $erro) {
    error_log('[quiz] compartilhar.php: ' . $erro->getMessage());
    responder(500, ['ok' => false, 'erro' => 'Não foi possível registrar.']);
}
