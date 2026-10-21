<?php
/**
 * Painel: fila de publicações do Instagram.
 * A equipe vê a imagem e o @ que será marcado, e aprova (publica nos Stories) ou rejeita.
 */

require_once __DIR__ . '/_comum.php';
exigir_login();

$pdo = db();

const STATUS_ABAS = [
    'pendente'  => 'Aguardando aprovação',
    'erro'      => 'Com erro',
    'publicada' => 'Publicadas',
    'rejeitada' => 'Rejeitadas',
    'todas'     => 'Todas',
];

// ---------- Ações (aprovar / rejeitar) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        http_response_code(400);
        exit('Requisição inválida.');
    }

    $acao = $_POST['acao'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    $voltar = 'publicacoes.php' . (isset(STATUS_ABAS[$_POST['aba'] ?? '']) ? '?status=' . $_POST['aba'] : '');

    if ($id < 1) {
        http_response_code(400);
        exit('Publicação inválida.');
    }

    if ($acao === 'aprovar') {
        $resultado = ig_processar_publicacao($pdo, $id);
        flash($resultado['ok'] ? 'ok' : 'erro', $resultado['mensagem']);
    } elseif ($acao === 'rejeitar') {
        // Só rejeita o que ainda não foi publicado nem está sendo publicado agora
        $busca = $pdo->prepare("SELECT arquivo FROM publicacoes WHERE id = ? AND status IN ('pendente', 'erro')");
        $busca->execute([$id]);
        $linha = $busca->fetch();

        if ($linha) {
            $pdo->prepare("UPDATE publicacoes SET status = 'rejeitada', arquivo = NULL, erro = NULL WHERE id = ?")->execute([$id]);
            ig_apagar_arquivo($linha['arquivo']); // a imagem não precisa mais ficar no servidor
            flash('ok', 'Publicação rejeitada. A imagem foi apagada do servidor.');
        } else {
            flash('alerta', 'Esta publicação já foi tratada.');
        }
    } else {
        http_response_code(400);
        exit('Ação inválida.');
    }

    header('Location: ' . $voltar);
    exit;
}

// ---------- Listagem ----------
$aba = isset(STATUS_ABAS[$_GET['status'] ?? '']) ? $_GET['status'] : 'pendente';

$contagens = array_fill_keys(array_keys(STATUS_ABAS), 0);

foreach ($pdo->query('SELECT status, COUNT(*) AS total FROM publicacoes GROUP BY status') as $linha) {
    $contagens[$linha['status']] = (int) $linha['total'];
    $contagens['todas'] += (int) $linha['total'];
}

$contagens['erro'] += $contagens['publicando']; // "publicando" preso aparece junto com os erros

$onde = $aba === 'todas' ? '' : ($aba === 'erro' ? "WHERE p.status IN ('erro', 'publicando')" : 'WHERE p.status = :status');

$consulta = $pdo->prepare(
    "SELECT p.*, v.nome, v.celular, v.area_vencedora, v.area_cor, v.data_hora
     FROM publicacoes p
     JOIN v_resultados v ON v.tentativa_id = p.tentativa_id
     $onde
     ORDER BY p.id " . ($aba === 'pendente' ? 'ASC' : 'DESC') . '
     LIMIT 50'
);

if ($onde === 'WHERE p.status = :status') {
    $consulta->bindValue(':status', $aba);
}

$consulta->execute();
$linhas = $consulta->fetchAll();

$cfg = ig_config($pdo);

cabecalho('Publicações');
?>
<h1>Publicações no Instagram</h1>

<?php flash_mostrar(); ?>

<?php if (!ig_pronto($cfg)): ?>
  <div class="aviso alerta">
    A publicação pelo Instagram da escola está <strong>desligada</strong>. Ligue e configure a conta em
    <a href="instagram.php">Instagram</a> para poder aprovar os itens abaixo.
  </div>
<?php else: ?>
  <p class="muted">Ao aprovar, o resultado sai nos Stories de
    <strong><?= h(count($cfg['contas']) === 1 ? $cfg['contas'][0]['usuario'] : 'uma das ' . count($cfg['contas']) . ' contas cadastradas (a primeira que ainda não atingiu o limite do dia)') ?></strong>
    marcando o @ da pessoa. <a href="instagram.php">Ver contas</a></p>
<?php endif; ?>

<nav class="abas" aria-label="Filtrar por situação">
  <?php foreach (STATUS_ABAS as $chave => $nome): ?>
    <a href="publicacoes.php?status=<?= h($chave) ?>" class="<?= $aba === $chave ? 'ativa' : '' ?>">
      <?= h($nome) ?> (<?= (int) $contagens[$chave] ?>)
    </a>
  <?php endforeach; ?>
</nav>

<section class="card">
  <?php if (!$linhas): ?>
    <p class="vazio">Nenhuma publicação nesta lista.</p>
  <?php endif; ?>

  <?php foreach ($linhas as $l):
      $temImagem = ig_nome_arquivo_valido($l['arquivo']) && is_file(IG_PASTA_ARQUIVOS . '/' . $l['arquivo']);
      $podeAgir = in_array($l['status'], ['pendente', 'erro', 'publicando'], true);
  ?>
    <article class="pub">
      <?php if ($temImagem): ?>
        <a href="../<?= h(IG_CAMINHO_PUBLICO . $l['arquivo']) ?>" target="_blank" rel="noopener">
          <img src="../<?= h(IG_CAMINHO_PUBLICO . $l['arquivo']) ?>" alt="Imagem do Story de <?= h($l['nome']) ?>" loading="lazy">
        </a>
      <?php else: ?>
        <div class="semimg">
          <?= $l['status'] === 'publicada' ? 'Já publicada (imagem apagada)' : 'Sem imagem' ?>
        </div>
      <?php endif; ?>

      <div>
        <h3><?= h($l['nome']) ?></h3>
        <p>
          <span class="etiqueta <?= h($l['status']) ?>"><?= h(['pendente' => 'Aguardando', 'publicando' => 'Publicando', 'publicada' => 'Publicada', 'rejeitada' => 'Rejeitada', 'erro' => 'Erro'][$l['status']] ?? $l['status']) ?></span>
          <span class="chip" style="background: <?= h($l['area_cor']) ?>"><?= h($l['area_vencedora']) ?></span>
        </p>
        <p style="margin-top: 8px;">Marcar: <strong><?= h($l['instagram_pessoa']) ?></strong>
          <a href="https://www.instagram.com/<?= h(ltrim($l['instagram_pessoa'], '@')) ?>/" target="_blank" rel="noopener">(ver perfil)</a>
        </p>
        <p class="muted">
          <?= h(formatar_celular($l['celular'])) ?> · enviado em <?= h(date('d/m/Y H:i', strtotime($l['criado_em']))) ?>
          · <a href="tentativa.php?id=<?= (int) $l['tentativa_id'] ?>">ver respostas</a>
        </p>

        <?php if ($l['status'] === 'publicada'): ?>
          <p class="muted">
            Publicada em <?= h(date('d/m/Y H:i', strtotime((string) $l['publicado_em']))) ?>
            <?= $l['conta_usuario'] ? 'por <strong>' . h($l['conta_usuario']) . '</strong>' : '' ?>
            <?= $l['marcou'] ? '· marcou ' . h($l['instagram_pessoa']) : '· <strong>sem marcação</strong> (o Instagram recusou o @)' ?>
          </p>
        <?php endif; ?>

        <?php if ($l['erro']): ?><p class="erro-txt"><?= h($l['erro']) ?></p><?php endif; ?>
      </div>

      <?php if ($podeAgir): ?>
        <div class="acoes-col">
          <form method="post" onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Publicando…';">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="aprovar">
            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
            <input type="hidden" name="aba" value="<?= h($aba) ?>">
            <button class="btn" type="submit" <?= ig_pronto($cfg) ? '' : 'disabled' ?>><?= $l['status'] === 'pendente' ? 'Aprovar e publicar' : 'Tentar de novo' ?></button>
          </form>
          <form method="post" onsubmit="return confirm('Rejeitar? A imagem será apagada e nada será publicado.');">
            <?= csrf_campo() ?>
            <input type="hidden" name="acao" value="rejeitar">
            <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
            <input type="hidden" name="aba" value="<?= h($aba) ?>">
            <button class="btn sec" type="submit">Rejeitar</button>
          </form>
        </div>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>

  <?php if (count($linhas) === 50): ?>
    <p class="muted" style="margin-top: 12px;">Mostrando as 50 primeiras desta lista.</p>
  <?php endif; ?>
</section>
<?php
rodape();
