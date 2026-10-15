<?php
/**
 * Painel: login + resumo + lista de resultados com filtros.
 */

require_once __DIR__ . '/_comum.php';

$erro = '';

// ---------- Sair ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'sair') {
    if (csrf_ok()) {
        $_SESSION = [];
        session_destroy();
    }

    header('Location: index.php');
    exit;
}

// ---------- Entrar ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'entrar') {
    $bloqueadoAte = (int) ($_SESSION['bloqueio_ate'] ?? 0);

    if ($bloqueadoAte > time()) {
        $erro = 'Muitas tentativas. Aguarde ' . ($bloqueadoAte - time()) . ' segundos.';
    } elseif (hash_equals(ADMIN_SENHA, (string) ($_POST['senha'] ?? ''))) {
        session_regenerate_id(true);
        $_SESSION['admin'] = true;
        $_SESSION['falhas'] = 0;
        header('Location: index.php');
        exit;
    } else {
        usleep(700000);
        $_SESSION['falhas'] = (int) ($_SESSION['falhas'] ?? 0) + 1;

        if ($_SESSION['falhas'] >= 5) {
            $_SESSION['falhas'] = 0;
            $_SESSION['bloqueio_ate'] = time() + 60;
        }

        $erro = 'Senha incorreta.';
    }
}

// ---------- Tela de login ----------
if (!logado()) {
    cabecalho('Entrar', false);
    ?>
    <form method="post" class="card login" autocomplete="off">
      <h1>Entrar</h1>
      <p class="muted">Acesso restrito à equipe do SENAI.</p>
      <?php if ($erro): ?><div class="aviso erro"><?= h($erro) ?></div><?php endif; ?>
      <input type="hidden" name="acao" value="entrar">
      <div>
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" required autofocus>
      </div>
      <button class="btn" type="submit">Entrar</button>
    </form>
    <?php
    rodape();
    exit;
}

// ---------- Dados do painel ----------
$pdo = db();

$totais = $pdo->query(
    'SELECT
        (SELECT COUNT(*) FROM participantes)                  AS participantes,
        (SELECT COUNT(*) FROM tentativas)                     AS tentativas,
        (SELECT COALESCE(SUM(houve_desempate), 0) FROM tentativas) AS desempates,
        (SELECT COUNT(*) FROM compartilhamentos)              AS compartilhamentos'
)->fetch();

$porArea = $pdo->query(
    'SELECT a.id, a.nome, a.cor,
            COUNT(t.id) AS vitorias,
            (SELECT ROUND(AVG(p.pontos), 1) FROM pontuacoes p WHERE p.area_id = a.id) AS media
     FROM areas a
     LEFT JOIN tentativas t ON t.area_vencedora_id = a.id
     GROUP BY a.id, a.nome, a.cor, a.ordem
     ORDER BY a.ordem'
)->fetchAll();

$maxVitorias = max(array_merge([1], array_map(fn($a) => (int) $a['vitorias'], $porArea)));

$filtro = ler_filtros();
$porPagina = 25;
$pagina = max(1, (int) ($_GET['p'] ?? 1));

$contagem = $pdo->prepare('SELECT COUNT(*) FROM v_resultados' . $filtro['sql']);
$contagem->execute($filtro['params']);
$total = (int) $contagem->fetchColumn();
$paginas = max(1, (int) ceil($total / $porPagina));
$pagina = min($pagina, $paginas);

$lista = $pdo->prepare(
    'SELECT * FROM v_resultados' . $filtro['sql'] . ' ORDER BY tentativa_id DESC LIMIT :lim OFFSET :off'
);
foreach ($filtro['params'] as $chave => $valor) {
    $lista->bindValue($chave, $valor);
}
$lista->bindValue(':lim', $porPagina, PDO::PARAM_INT);
$lista->bindValue(':off', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
$lista->execute();
$linhas = $lista->fetchAll();

$v = $filtro['valores'];
$paramsUrl = array_filter(['q' => $v['q'], 'area' => $v['area'] ?: '', 'de' => $v['de'], 'ate' => $v['ate']], fn($x) => $x !== '');

function url_pagina(array $paramsUrl, int $p): string
{
    return 'index.php?' . http_build_query($paramsUrl + ['p' => $p]);
}

cabecalho('Resultados');
?>
<h1>Resultados do quiz</h1>

<?php if (senha_padrao()): ?>
  <div class="aviso alerta">A senha do painel ainda é a padrão. Troque <code>ADMIN_SENHA</code> no arquivo <code>api/config.php</code>.</div>
<?php endif; ?>

<?php if (($_GET['msg'] ?? '') === 'excluido'): ?>
  <div class="aviso ok">Registro excluído.</div>
<?php endif; ?>

<section class="numeros">
  <div class="numero"><b><?= (int) $totais['participantes'] ?></b><span>Participantes</span></div>
  <div class="numero"><b><?= (int) $totais['tentativas'] ?></b><span>Quizzes concluídos</span></div>
  <div class="numero"><b><?= (int) $totais['desempates'] ?></b><span>Com desempate</span></div>
  <div class="numero"><b><?= (int) $totais['compartilhamentos'] ?></b><span>Cliques em compartilhar</span></div>
</section>

<section class="card">
  <h2>Qual área mais combinou</h2>
  <?php foreach ($porArea as $a): ?>
    <div class="area-linha">
      <div class="area-topo">
        <span><?= h($a['nome']) ?></span>
        <span><?= (int) $a['vitorias'] ?> <small>· média <?= $a['media'] !== null ? h(str_replace('.', ',', $a['media'])) : '–' ?>/15 pts</small></span>
      </div>
      <div class="barra"><i style="width: <?= round(((int) $a['vitorias'] / $maxVitorias) * 100) ?>%; background: <?= h($a['cor']) ?>"></i></div>
    </div>
  <?php endforeach; ?>
</section>

<section class="card">
  <h2>Participantes</h2>

  <form method="get" class="filtros">
    <div>
      <label for="q">Buscar</label>
      <input type="search" id="q" name="q" value="<?= h($v['q']) ?>" placeholder="Nome, celular ou Instagram">
    </div>
    <div>
      <label for="area">Área</label>
      <select id="area" name="area">
        <option value="0">Todas</option>
        <?php foreach ($porArea as $a): ?>
          <option value="<?= (int) $a['id'] ?>" <?= $v['area'] === (int) $a['id'] ? 'selected' : '' ?>><?= h($a['nome']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label for="de">De</label>
      <input type="date" id="de" name="de" value="<?= h($v['de']) ?>">
    </div>
    <div>
      <label for="ate">Até</label>
      <input type="date" id="ate" name="ate" value="<?= h($v['ate']) ?>">
    </div>
    <div class="acoes">
      <button class="btn" type="submit">Filtrar</button>
    </div>
  </form>

  <div class="acoes" style="margin: 14px 0;">
    <span class="muted"><?= $total ?> resultado(s)</span>
    <a class="btn sec" href="exportar.php?<?= h(http_build_query($paramsUrl)) ?>">Exportar CSV</a>
    <?php if ($paramsUrl): ?><a href="index.php">Limpar filtros</a><?php endif; ?>
  </div>

  <div class="tabela-wrap">
    <table>
      <thead>
        <tr><th>Data</th><th>Nome</th><th>Celular</th><th>Instagram</th><th>Área</th><th>Desempate</th><th>Compart.</th><th></th></tr>
      </thead>
      <tbody>
      <?php if (!$linhas): ?>
        <tr><td colspan="8" class="vazio">Nenhum resultado encontrado.</td></tr>
      <?php endif; ?>
      <?php foreach ($linhas as $l): ?>
        <tr>
          <td><?= h(date('d/m/Y H:i', strtotime($l['data_hora']))) ?></td>
          <td><?= h($l['nome']) ?></td>
          <td><?= h(formatar_celular($l['celular'])) ?></td>
          <td><?= $l['instagram'] ? h($l['instagram']) : '<span class="muted">–</span>' ?></td>
          <td><span class="chip" style="background: <?= h($l['area_cor']) ?>"><?= h($l['area_vencedora']) ?></span></td>
          <td><?= $l['houve_desempate'] ? 'Sim (' . (int) $l['rodadas_desempate'] . ')' : '<span class="muted">Não</span>' ?></td>
          <td><?= (int) $l['compartilhamentos'] ?></td>
          <td><a href="tentativa.php?id=<?= (int) $l['tentativa_id'] ?>">Detalhes</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($paginas > 1): ?>
    <nav class="pag" aria-label="Páginas">
      <?php if ($pagina > 1): ?><a href="<?= h(url_pagina($paramsUrl, $pagina - 1)) ?>">‹ Anterior</a><?php endif; ?>
      <span class="atual"><?= $pagina ?> / <?= $paginas ?></span>
      <?php if ($pagina < $paginas): ?><a href="<?= h(url_pagina($paramsUrl, $pagina + 1)) ?>">Próxima ›</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</section>
<?php
rodape();
