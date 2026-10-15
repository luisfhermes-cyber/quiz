<?php
/**
 * Salva o resultado de um quiz.
 *
 * Recebe (JSON, POST):
 *   {
 *     "nome": "Ana", "celular": "(11) 99999-9999", "instagram": "@ana",
 *     "vencedor": "ds",
 *     "respostas": [ { "pergunta": "texto...", "valor": 4, "fase": "normal", "rodada": 0 }, ... ]
 *   }
 *
 * Os pontos de cada área são recalculados aqui a partir das respostas.
 * Devolve: { "ok": true, "id": <id da tentativa> }
 */

require_once __DIR__ . '/db.php';
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

$corpo = file_get_contents('php://input');

if ($corpo === false || strlen($corpo) > 65536) {
    responder(413, ['ok' => false, 'erro' => 'Requisição grande demais.']);
}

$dados = json_decode($corpo, true);

if (!is_array($dados)) {
    responder(400, ['ok' => false, 'erro' => 'JSON inválido.']);
}

// ---------- Participante ----------

$nome = trim(preg_replace('/[\x00-\x1F\x7F]/u', '', (string) ($dados['nome'] ?? '')));
$celular = preg_replace('/\D/', '', (string) ($dados['celular'] ?? ''));
$instagram = trim((string) ($dados['instagram'] ?? ''));

if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100) {
    responder(422, ['ok' => false, 'erro' => 'Nome inválido.']);
}

if (strlen($celular) < 10 || strlen($celular) > 11) {
    responder(422, ['ok' => false, 'erro' => 'Celular inválido.']);
}

if ($instagram !== '') {
    if ($instagram[0] !== '@') {
        $instagram = '@' . $instagram;
    }

    // Instagram fora do padrão não derruba o resultado: simplesmente não é salvo.
    if (!preg_match('/^@[A-Za-z0-9._]{1,30}$/', $instagram)) {
        $instagram = '';
    }
}

// ---------- Respostas ----------

$respostasRecebidas = $dados['respostas'] ?? null;

if (!is_array($respostasRecebidas) || count($respostasRecebidas) < 1 || count($respostasRecebidas) > 400) {
    responder(422, ['ok' => false, 'erro' => 'Respostas inválidas.']);
}

try {
    $pdo = db();

    // Áreas: codigo => id
    $areas = [];
    foreach ($pdo->query('SELECT id, codigo FROM areas') as $linha) {
        $areas[$linha['codigo']] = (int) $linha['id'];
    }

    // Perguntas: texto => [id, area_id]
    $perguntas = [];
    foreach ($pdo->query('SELECT id, area_id, texto FROM perguntas') as $linha) {
        $perguntas[$linha['texto']] = [(int) $linha['id'], (int) $linha['area_id']];
    }

    $vencedorCodigo = (string) ($dados['vencedor'] ?? '');

    if (!isset($areas[$vencedorCodigo])) {
        responder(422, ['ok' => false, 'erro' => 'Área vencedora inválida.']);
    }

    // Placar recalculado no servidor
    $pontos = [];
    $pontosDesempate = [];
    foreach ($areas as $id) {
        $pontos[$id] = 0;
        $pontosDesempate[$id] = 0;
    }

    $linhasRespostas = [];
    $maiorRodada = 0;
    $temNormal = false;

    foreach (array_values($respostasRecebidas) as $indice => $resposta) {
        if (!is_array($resposta)) {
            responder(422, ['ok' => false, 'erro' => 'Resposta inválida.']);
        }

        $texto = (string) ($resposta['pergunta'] ?? '');
        $valor = filter_var($resposta['valor'] ?? null, FILTER_VALIDATE_INT);
        $fase = (string) ($resposta['fase'] ?? 'normal');
        $rodada = (int) ($resposta['rodada'] ?? 0);

        if (!isset($perguntas[$texto])) {
            responder(422, ['ok' => false, 'erro' => 'Pergunta desconhecida. O texto no java.js precisa ser igual ao do banco.']);
        }

        if ($valor === false || $valor < 1 || $valor > 5) {
            responder(422, ['ok' => false, 'erro' => 'Valor de resposta inválido.']);
        }

        if ($fase !== 'normal' && $fase !== 'desempate') {
            responder(422, ['ok' => false, 'erro' => 'Fase inválida.']);
        }

        $rodada = $fase === 'desempate' ? max(1, min(250, $rodada)) : 0;

        [$perguntaId, $areaId] = $perguntas[$texto];

        if ($fase === 'normal') {
            $pontos[$areaId] += $valor;
            $temNormal = true;
        } else {
            $pontosDesempate[$areaId] += $valor;
            $maiorRodada = max($maiorRodada, $rodada);
        }

        $linhasRespostas[] = [$perguntaId, $areaId, $valor, $fase, $rodada, $indice + 1];
    }

    if (!$temNormal) {
        responder(422, ['ok' => false, 'erro' => 'Faltam respostas do quiz.']);
    }

    // A área vencedora precisa estar entre as de maior pontuação (as áreas empatadas passam pelo desempate)
    $maiorPontuacao = max($pontos);
    $vencedorId = $areas[$vencedorCodigo];

    if ($pontos[$vencedorId] !== $maiorPontuacao) {
        responder(422, ['ok' => false, 'erro' => 'Área vencedora não confere com as respostas.']);
    }

    // ---------- Gravação (tudo ou nada) ----------

    $pdo->beginTransaction();

    $pdo->prepare(
        'INSERT INTO participantes (nome, celular, instagram) VALUES (:nome, :celular, :instagram)
         ON DUPLICATE KEY UPDATE nome = VALUES(nome), instagram = COALESCE(VALUES(instagram), instagram)'
    )->execute([
        ':nome'      => $nome,
        ':celular'   => $celular,
        ':instagram' => $instagram !== '' ? $instagram : null,
    ]);

    $busca = $pdo->prepare('SELECT id FROM participantes WHERE celular = :celular');
    $busca->execute([':celular' => $celular]);
    $participanteId = (int) $busca->fetchColumn();

    $pdo->prepare(
        'INSERT INTO tentativas (participante_id, area_vencedora_id, houve_desempate, rodadas_desempate)
         VALUES (:participante, :area, :desempate, :rodadas)'
    )->execute([
        ':participante' => $participanteId,
        ':area'         => $vencedorId,
        ':desempate'    => $maiorRodada > 0 ? 1 : 0,
        ':rodadas'      => min(255, $maiorRodada),
    ]);

    $tentativaId = (int) $pdo->lastInsertId();

    $insereResposta = $pdo->prepare(
        'INSERT INTO respostas (tentativa_id, pergunta_id, area_id, valor, fase, rodada, ordem)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($linhasRespostas as $linha) {
        $insereResposta->execute(array_merge([$tentativaId], $linha));
    }

    $inserePontuacao = $pdo->prepare(
        'INSERT INTO pontuacoes (tentativa_id, area_id, pontos, pontos_desempate) VALUES (?, ?, ?, ?)'
    );

    foreach ($areas as $areaId) {
        $inserePontuacao->execute([$tentativaId, $areaId, $pontos[$areaId], $pontosDesempate[$areaId]]);
    }

    $pdo->commit();

    // Informa ao quiz se a escola publica os resultados no Instagram (muda o botão da tela final)
    $instagram = ['ativo' => false, 'conta' => ''];

    try {
        $cfgInstagram = ig_config($pdo);

        if (ig_pronto($cfgInstagram)) {
            $instagram = ['ativo' => true, 'conta' => $cfgInstagram['usuario']];
        }
    } catch (Throwable $ignorado) {
    }

    responder(201, ['ok' => true, 'id' => $tentativaId, 'instagram' => $instagram]);
} catch (Throwable $erro) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[quiz] salvar.php: ' . $erro->getMessage());
    responder(500, ['ok' => false, 'erro' => 'Não foi possível salvar.']);
}
