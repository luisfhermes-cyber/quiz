<?php
/**
 * Exporta os resultados (com os filtros da tela) em CSV para abrir no Excel.
 */

require_once __DIR__ . '/_comum.php';
exigir_login();

$pdo = db();
$filtro = ler_filtros();

$areas = $pdo->query('SELECT id, nome FROM areas ORDER BY ordem')->fetchAll();

// Uma coluna de pontos por área (os ids vêm do banco, então são seguros no SQL)
$colunas = '';
foreach ($areas as $a) {
    $colunas .= ', (SELECT p.pontos FROM pontuacoes p WHERE p.tentativa_id = v.tentativa_id AND p.area_id = ' . (int) $a['id'] . ') AS a' . (int) $a['id'];
}

$sql = 'SELECT v.*' . $colunas . ' FROM v_resultados v' . $filtro['sql'] . ' ORDER BY v.tentativa_id DESC';

$consulta = $pdo->prepare($sql);
$consulta->execute($filtro['params']);

/** Evita que o Excel interprete texto digitado pelo usuário como fórmula. */
function celula(string $texto): string
{
    if ($texto !== '' && strpos("=+-@\t\r", $texto[0]) !== false) {
        return "'" . $texto;
    }

    return $texto;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="resultados-quiz-senai-' . date('Y-m-d') . '.csv"');

$saida = fopen('php://output', 'w');
fwrite($saida, "\xEF\xBB\xBF"); // BOM para o Excel abrir os acentos corretamente

$cabecalho = ['id', 'data_hora', 'nome', 'celular', 'instagram', 'area_vencedora', 'houve_desempate', 'rodadas_desempate', 'compartilhamentos'];
foreach ($areas as $a) {
    $cabecalho[] = 'pontos ' . $a['nome'];
}
fputcsv($saida, $cabecalho, ';', '"', '');

while ($l = $consulta->fetch()) {
    $linha = [
        $l['tentativa_id'],
        date('d/m/Y H:i', strtotime($l['data_hora'])),
        celula($l['nome']),
        formatar_celular($l['celular']),
        ltrim((string) $l['instagram'], '@'),
        $l['area_vencedora'],
        $l['houve_desempate'] ? 'sim' : 'nao',
        $l['rodadas_desempate'],
        $l['compartilhamentos'],
    ];

    foreach ($areas as $a) {
        $linha[] = $l['a' . (int) $a['id']];
    }

    fputcsv($saida, $linha, ';', '"', '');
}

fclose($saida);
