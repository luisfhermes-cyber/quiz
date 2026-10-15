<?php
/**
 * Funções comuns do painel: sessão, login, proteção CSRF, filtros e layout.
 */

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/../api/instagram.php';

$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

session_name('quiz_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => $https,
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');

function logado(): bool
{
    return !empty($_SESSION['admin']);
}

function exigir_login(): void
{
    if (!logado()) {
        header('Location: index.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['csrf']);
}

function senha_padrao(): bool
{
    return ADMIN_SENHA === 'trocar-esta-senha';
}

/** Lê os filtros da URL (busca, área e período) e monta o WHERE para a view v_resultados. */
function ler_filtros(): array
{
    $q    = trim((string) ($_GET['q'] ?? ''));
    $area = (int) ($_GET['area'] ?? 0);
    $de   = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['de'] ?? '')) ? $_GET['de'] : '';
    $ate  = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['ate'] ?? '')) ? $_GET['ate'] : '';

    $onde = [];
    $par  = [];

    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $digitos = preg_replace('/\D/', '', $q);

        $partes = ['nome LIKE :q1', 'instagram LIKE :q2'];
        $par[':q1'] = $like;
        $par[':q2'] = $like;

        if (strlen($digitos) >= 3) {
            $partes[] = 'celular LIKE :q3';
            $par[':q3'] = '%' . $digitos . '%';
        }

        $onde[] = '(' . implode(' OR ', $partes) . ')';
    }

    if ($area > 0) {
        $onde[] = 'area_id = :area';
        $par[':area'] = $area;
    }

    if ($de !== '') {
        $onde[] = 'DATE(data_hora) >= :de';
        $par[':de'] = $de;
    }

    if ($ate !== '') {
        $onde[] = 'DATE(data_hora) <= :ate';
        $par[':ate'] = $ate;
    }

    return [
        'sql'    => $onde ? ' WHERE ' . implode(' AND ', $onde) : '',
        'params' => $par,
        'valores' => ['q' => $q, 'area' => $area, 'de' => $de, 'ate' => $ate],
    ];
}

/** Guarda um aviso para aparecer na próxima tela (depois de um redirecionamento). $tipo: ok, erro ou alerta. */
function flash(string $tipo, string $texto): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'texto' => $texto];
}

function flash_mostrar(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $tipo = in_array($f['tipo'], ['ok', 'erro', 'alerta'], true) ? $f['tipo'] : 'ok';
    echo '<div class="aviso ' . $tipo . '">' . h($f['texto']) . '</div>';
}

/** Quantas publicações esperam aprovação (0 se a tabela ainda não existir). */
function publicacoes_pendentes(): int
{
    try {
        return (int) db()->query("SELECT COUNT(*) FROM publicacoes WHERE status = 'pendente'")->fetchColumn();
    } catch (Throwable $erro) {
        return 0;
    }
}

function cabecalho(string $titulo, bool $menu = true): void
{
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= h($titulo) ?> — Painel Quiz SENAI</title>
  <link rel="stylesheet" href="admin.css">
</head>
<body>
<header class="topo">
  <div class="topo-in">
    <strong class="marca"><span class="selo">SENAI</span> Painel do Quiz</strong>
    <?php if ($menu && logado()): ?>
      <nav class="menu">
        <a href="index.php">Resultados</a>
        <?php $pendentes = publicacoes_pendentes(); ?>
        <a href="publicacoes.php">Publicações<?php if ($pendentes > 0): ?> <span class="contador"><?= $pendentes ?></span><?php endif; ?></a>
        <a href="instagram.php">Instagram</a>
        <form method="post" action="index.php" class="inline">
          <?= csrf_campo() ?>
          <input type="hidden" name="acao" value="sair">
          <button type="submit" class="link">Sair</button>
        </form>
      </nav>
    <?php endif; ?>
  </div>
</header>
<main class="conteudo">
<?php
}

function rodape(): void
{
    echo "</main>\n</body>\n</html>";
}
