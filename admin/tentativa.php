<?php
/**
 * Detalhes de um quiz: placar de todas as áreas, respostas e exclusão (LGPD).
 */

require_once __DIR__ . '/_comum.php';
exigir_login();

$pdo = db();
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$busca = $pdo->prepare('SELECT * FROM v_resultados WHERE tentativa_id = ?');
$busca->execute([$id]);
$t = $busca->fetch();

if (!$t) {
    http_response_code(404);
    cabecalho('Não encontrado');
    echo '<div class="aviso erro">Resultado não encontrado.</div><p><a href="index.php">← Voltar</a></p>';
    rodape();
    exit;
}

// ---------- Exclusão ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        http_response_code(400);
        exit('Requisição inválida.');
    }

    $acao = $_POST['acao'] ?? '';

    if ($acao === 'excluir_tentativa') {
        $pdo->prepare('DELETE FROM tentativas WHERE id = ?')->execute([$id]);
    } elseif ($acao === 'excluir_participante') {
        // Apaga a pessoa e, em cascata, todos os quizzes, respostas e placares dela
        $pdo->prepare('DELETE FROM participantes WHERE id = ?')->execute([$t['participante_id']]);
    } else {
        http_response_code(400);
        exit('Ação inválida.');
    }

    header('Location: index.php?msg=excluido');
    exit;
}

$placar = $pdo->prepare(
    'SELECT a.nome, a.cor, p.pontos, p.pontos_desempate
     FROM pontuacoes p JOIN areas a ON a.id = p.area_id
     WHERE p.tentativa_id = ?
     ORDER BY p.pontos DESC, p.pontos_desempate DESC, a.ordem'
);
$placar->execute([$id]);
$placar = $placar->fetchAll();

$respostas = $pdo->prepare(
    'SELECT r.ordem, r.valor, r.fase, r.rodada, pe.texto, a.nome AS area, a.cor
     FROM respostas r
     JOIN perguntas pe ON pe.id = r.pergunta_id
     JOIN areas a ON a.id = r.area_id
     WHERE r.tentativa_id = ?
     ORDER BY r.ordem'
);
$respostas->execute([$id]);
$respostas = $respostas->fetchAll();

$outras = $pdo->prepare('SELECT COUNT(*) FROM tentativas WHERE participante_id = ?');
$outras->execute([$t['participante_id']]);
$totalDaPessoa = (int) $outras->fetchColumn();

cabecalho('Resultado #' . $id);
?>
<p><a href="index.php">← Voltar para os resultados</a></p>

<section class="card">
  <h1><?= h($t['nome']) ?></h1>
  <p class="muted">
    <?= h(formatar_celular($t['celular'])) ?>
    <?= $t['instagram'] ? ' · ' . h($t['instagram']) : '' ?>
    · <?= h(date('d/m/Y H:i', strtotime($t['data_hora']))) ?>
    · <?= $totalDaPessoa ?> quiz(zes) desta pessoa
  </p>
  <p style="margin-top: 12px;">Área que mais combina:
    <span class="chip" style="background: <?= h($t['area_cor']) ?>"><?= h($t['area_vencedora']) ?></span>
    <?php if ($t['houve_desempate']): ?><span class="muted"> · desempate em <?= (int) $t['rodadas_desempate'] ?> rodada(s)</span><?php endif; ?>
    <span class="muted"> · <?= (int) $t['compartilhamentos'] ?> clique(s) em compartilhar</span>
  </p>
</section>

<div class="dois">
  <section class="card">
    <h2>Pontuação por área</h2>
    <?php foreach ($placar as $p): ?>
      <div class="area-linha">
        <div class="area-topo">
          <span><?= h($p['nome']) ?></span>
          <span><?= (int) $p['pontos'] ?>/15<?= (int) $p['pontos_desempate'] > 0 ? ' <small>+ ' . (int) $p['pontos_desempate'] . ' desempate</small>' : '' ?></span>
        </div>
        <div class="barra"><i style="width: <?= round(((int) $p['pontos'] / 15) * 100) ?>%; background: <?= h($p['cor']) ?>"></i></div>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="card">
    <h2>Excluir dados</h2>
    <p class="muted" style="margin-bottom: 12px;">A exclusão é definitiva.</p>
    <div class="acoes">
      <form method="post" onsubmit="return confirm('Excluir só este quiz?');">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="acao" value="excluir_tentativa">
        <button class="btn sec" type="submit">Excluir este quiz</button>
      </form>
      <form method="post" onsubmit="return confirm('Excluir esta pessoa e TODOS os quizzes dela?');">
        <?= csrf_campo() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <input type="hidden" name="acao" value="excluir_participante">
        <button class="btn perigo" type="submit">Excluir a pessoa</button>
      </form>
    </div>
  </section>
</div>

<section class="card">
  <h2>Respostas (<?= count($respostas) ?>)</h2>
  <div class="tabela-wrap">
    <table>
      <thead><tr><th>#</th><th>Pergunta</th><th>Área</th><th>Nota</th><th>Fase</th></tr></thead>
      <tbody>
      <?php foreach ($respostas as $r): ?>
        <tr>
          <td><?= (int) $r['ordem'] ?></td>
          <td><?= h($r['texto']) ?></td>
          <td><span class="chip" style="background: <?= h($r['cor']) ?>"><?= h($r['area']) ?></span></td>
          <td><strong><?= (int) $r['valor'] ?></strong></td>
          <td><?= $r['fase'] === 'desempate' ? 'Desempate ' . (int) $r['rodada'] : '<span class="muted">Normal</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php
rodape();
