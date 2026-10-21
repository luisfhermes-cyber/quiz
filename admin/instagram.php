<?php
/**
 * Painel: contas do Instagram que publicam os resultados nos Stories (até 10).
 * As contas são usadas na ordem da lista: quando uma atinge o limite de Stories do dia
 * (ou o token dela dá problema), a próxima é usada automaticamente.
 */

require_once __DIR__ . '/_comum.php';
exigir_login();

$pdo = db();
$erros = [];

/** O Instagram só consegue baixar a imagem se o site estiver na internet. */
function endereco_local(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));

    return $host === 'localhost'
        || preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|0\.)/', $host) === 1
        || substr($host, -6) === '.local';
}

function voltar(?int $editar = null): void
{
    header('Location: instagram.php' . ($editar ? '?editar=' . $editar : ''));
    exit;
}

function conta_por_id(PDO $pdo, int $id): ?array
{
    $q = $pdo->prepare('SELECT * FROM instagram_contas WHERE id = ?');
    $q->execute([$id]);
    $c = $q->fetch();

    if (!$c) {
        return null;
    }

    foreach (['usuario', 'ig_user_id', 'access_token'] as $campo) {
        $c[$campo] = (string) ($c[$campo] ?? '');
    }

    return $c;
}

// Configuração geral (liga/desliga + endereço do site) — linha única
$geral = ['ativo' => 0, 'url_publica' => ''];
$linhaGeral = $pdo->query('SELECT ativo, url_publica FROM instagram_config WHERE id = 1')->fetch();

if ($linhaGeral) {
    $geral = ['ativo' => (int) $linhaGeral['ativo'], 'url_publica' => (string) ($linhaGeral['url_publica'] ?? '')];
}

$formConta = null; // valores mostrados no formulário de conta (em caso de erro, mantém o digitado)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        http_response_code(400);
        exit('Requisição inválida.');
    }

    $acao = $_POST['acao'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);

    // ---------- Geral: ligar/desligar e endereço do site ----------
    if ($acao === 'salvar_geral') {
        $url = rtrim(trim((string) ($_POST['url_publica'] ?? '')), '/');
        $ativo = isset($_POST['ativo']) ? 1 : 0;
        $aviso = [];

        if ($url !== '') {
            $partes = parse_url($url);

            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($partes['scheme'] ?? '', ['http', 'https'], true)
                || isset($partes['query']) || isset($partes['fragment'])) {
                flash('erro', 'O endereço público do site está inválido. Exemplo: https://suaescola.com.br/quiz');
                voltar();
            }

            if (endereco_local($url)) {
                $aviso[] = 'Atenção: este endereço é local. O Instagram não consegue baixar a imagem por ele; use o endereço público do site.';
            }
        }

        if ($ativo) {
            $contasProntas = array_filter(ig_contas($pdo), 'ig_conta_completa');

            if ($url === '') {
                $ativo = 0;
                $aviso[] = 'A publicação não foi ligada: falta o endereço público do site.';
            } elseif (!$contasProntas) {
                $ativo = 0;
                $aviso[] = 'A publicação não foi ligada: cadastre ao menos uma conta com @, ID e token.';
            }
        }

        $pdo->prepare(
            'INSERT INTO instagram_config (id, ativo, url_publica) VALUES (1, :a, :u)
             ON DUPLICATE KEY UPDATE ativo = VALUES(ativo), url_publica = VALUES(url_publica)'
        )->execute([':a' => $ativo, ':u' => $url !== '' ? $url : null]);

        flash($aviso && isset($_POST['ativo']) && !$ativo ? 'alerta' : 'ok', 'Configuração salva. ' . implode(' ', $aviso));
        voltar();
    }

    // ---------- Ações sobre uma conta existente ----------
    if (in_array($acao, ['testar', 'renovar', 'liberar', 'remover', 'subir', 'descer', 'ligar', 'desligar'], true)) {
        $conta = conta_por_id($pdo, $id);

        if (!$conta) {
            flash('erro', 'Conta não encontrada.');
            voltar();
        }

        if ($acao === 'testar') {
            $t = ig_testar_conexao($conta);

            if ($t['ok']) {
                $extra = '';

                if ($conta['ig_user_id'] === '' && $t['id'] !== '') {
                    $pdo->prepare('UPDATE instagram_contas SET ig_user_id = ? WHERE id = ?')->execute([$t['id'], $id]);
                    $extra = ' ID descoberto e salvo: ' . $t['id'] . '.';
                }

                if ($t['username'] !== '' && strcasecmp(ltrim($conta['usuario'], '@'), $t['username']) !== 0) {
                    flash('alerta', 'O token pertence a @' . $t['username'] . ', mas a conta está cadastrada como ' . $conta['usuario'] . '. Confira.' . $extra);
                } else {
                    flash('ok', 'Conexão OK: @' . $t['username'] . '.' . $extra);
                }
            } else {
                flash('erro', 'Falha ao conectar ' . $conta['usuario'] . ': ' . $t['erro']);
            }
        } elseif ($acao === 'renovar') {
            $r = ig_renovar_token($conta);

            if ($r['ok']) {
                $pdo->prepare('UPDATE instagram_contas SET access_token = ?, token_atualizado_em = NOW() WHERE id = ?')->execute([$r['token'], $id]);
                flash('ok', 'Token de ' . $conta['usuario'] . ' renovado por mais ' . $r['dias'] . ' dias.');
            } else {
                flash('erro', 'Não foi possível renovar ' . $conta['usuario'] . ': ' . $r['erro']);
            }
        } elseif ($acao === 'liberar') {
            $pdo->prepare('UPDATE instagram_contas SET bloqueada_ate = NULL, ultimo_erro = NULL WHERE id = ?')->execute([$id]);
            flash('ok', $conta['usuario'] . ' liberada para voltar a ser usada.');
        } elseif ($acao === 'ligar' || $acao === 'desligar') {
            $pdo->prepare('UPDATE instagram_contas SET ativo = ? WHERE id = ?')->execute([$acao === 'ligar' ? 1 : 0, $id]);
            flash('ok', $conta['usuario'] . ($acao === 'ligar' ? ' ligada.' : ' desligada (não será usada).'));
        } elseif ($acao === 'remover') {
            $pdo->prepare('DELETE FROM instagram_contas WHERE id = ?')->execute([$id]);
            flash('ok', $conta['usuario'] . ' removida. O histórico das publicações dela continua guardado.');
        } else { // subir / descer: troca de lugar com a vizinha
            $lista = $pdo->query('SELECT id FROM instagram_contas ORDER BY ordem, id')->fetchAll(PDO::FETCH_COLUMN);
            $pos = array_search($id, array_map('intval', $lista), true);
            $alvo = $acao === 'subir' ? $pos - 1 : $pos + 1;

            if ($pos !== false && isset($lista[$alvo])) {
                [$lista[$pos], $lista[$alvo]] = [$lista[$alvo], $lista[$pos]];
            }

            $atualiza = $pdo->prepare('UPDATE instagram_contas SET ordem = ? WHERE id = ?');

            foreach ($lista as $i => $contaId) {
                $atualiza->execute([$i + 1, $contaId]);
            }
        }

        voltar();
    }

    // ---------- Adicionar / editar conta ----------
    if ($acao === 'salvar_conta' || $acao === 'salvar_conta_testar') {
        $existente = $id > 0 ? conta_por_id($pdo, $id) : null;

        if ($id > 0 && !$existente) {
            flash('erro', 'Conta não encontrada.');
            voltar();
        }

        $novo = $existente ?? [
            'id' => 0, 'ordem' => 0, 'ativo' => 1, 'usuario' => '', 'ig_user_id' => '', 'access_token' => '',
            'api_host' => 'graph.instagram.com', 'api_versao' => 'v21.0', 'limite_dia' => IG_LIMITE_PADRAO,
            'token_atualizado_em' => null,
        ];

        $trocouToken = false;

        $normalizado = ig_validar_usuario((string) ($_POST['usuario'] ?? ''));

        if ($normalizado === null) {
            $erros[] = 'O @ da conta está inválido. Use só letras, números, ponto e sublinhado (ex.: @senai.maravilha).';
            $novo['usuario'] = trim((string) ($_POST['usuario'] ?? ''));
        } else {
            $novo['usuario'] = $normalizado;
        }

        $novo['ig_user_id'] = preg_replace('/\D/', '', (string) ($_POST['ig_user_id'] ?? ''));

        if (strlen($novo['ig_user_id']) > 30) {
            $erros[] = 'O ID da conta tem dígitos demais.';
        }

        $novo['api_host'] = in_array($_POST['api_host'] ?? '', ['graph.instagram.com', 'graph.facebook.com'], true)
            ? $_POST['api_host'] : 'graph.instagram.com';

        $versao = trim((string) ($_POST['api_versao'] ?? ''));
        $novo['api_versao'] = preg_match('/^v\d{1,2}\.\d$/', $versao) ? $versao : 'v21.0';

        $limite = (int) ($_POST['limite_dia'] ?? IG_LIMITE_PADRAO);

        if ($limite < 1 || $limite > 100) {
            $erros[] = 'O limite de Stories por dia deve ficar entre 1 e 100 (a Meta permite no máximo 100).';
            $limite = IG_LIMITE_PADRAO;
        }

        $novo['limite_dia'] = $limite;

        $tokenDigitado = preg_replace('/\s+/', '', (string) ($_POST['access_token'] ?? ''));

        if ($tokenDigitado !== '') {
            if (strlen($tokenDigitado) < 20 || strlen($tokenDigitado) > 1000 || !preg_match('/^[A-Za-z0-9_\-.|]+$/', $tokenDigitado)) {
                $erros[] = 'O token parece inválido. Copie o token inteiro, sem espaços.';
            } else {
                $novo['access_token'] = $tokenDigitado;
                $trocouToken = true;
            }
        } elseif (!$existente) {
            $erros[] = 'Informe o token de acesso da conta.';
        }

        $novo['ativo'] = isset($_POST['ativo_conta']) ? 1 : 0;

        if (!$existente) {
            $total = (int) $pdo->query('SELECT COUNT(*) FROM instagram_contas')->fetchColumn();

            if ($total >= IG_MAX_CONTAS) {
                $erros[] = 'Você já cadastrou o máximo de ' . IG_MAX_CONTAS . ' contas. Remova uma para adicionar outra.';
            }
        }

        if (!$erros && $novo['usuario'] !== '') {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM instagram_contas WHERE usuario = ? AND id <> ?');
            $dup->execute([$novo['usuario'], $id]);

            if ((int) $dup->fetchColumn() > 0) {
                $erros[] = 'Já existe uma conta cadastrada com o @ ' . $novo['usuario'] . '.';
            }
        }

        $aviso = [];

        if (!$erros && $acao === 'salvar_conta_testar') {
            $t = ig_testar_conexao($novo);

            if ($t['ok']) {
                if ($novo['ig_user_id'] === '' && $t['id'] !== '') {
                    $novo['ig_user_id'] = $t['id'];
                }

                if ($t['username'] !== '' && strcasecmp(ltrim($novo['usuario'], '@'), $t['username']) !== 0) {
                    $aviso[] = 'O token pertence a @' . $t['username'] . ', mas você digitou ' . $novo['usuario'] . '. Confira.';
                }

                $aviso[] = 'Conexão OK: @' . $t['username'] . ' (ID ' . $novo['ig_user_id'] . ').';
            } else {
                $aviso[] = 'Falha ao conectar: ' . $t['erro'];

                if ($novo['ativo']) {
                    $novo['ativo'] = 0;
                    $aviso[] = 'A conta foi salva desligada.';
                }
            }
        }

        if (!$erros) {
            if ($existente) {
                $pdo->prepare(
                    'UPDATE instagram_contas SET ativo = :ativo, usuario = :usuario, ig_user_id = :id_ig, access_token = :token,
                            api_host = :host, api_versao = :versao, limite_dia = :limite,
                            token_atualizado_em = :tokenem' . ($trocouToken ? ', bloqueada_ate = NULL, ultimo_erro = NULL' : '') . '
                     WHERE id = :id'
                )->execute([
                    ':ativo' => (int) $novo['ativo'], ':usuario' => $novo['usuario'],
                    ':id_ig' => $novo['ig_user_id'] !== '' ? $novo['ig_user_id'] : null, ':token' => $novo['access_token'],
                    ':host' => $novo['api_host'], ':versao' => $novo['api_versao'], ':limite' => $novo['limite_dia'],
                    ':tokenem' => $trocouToken ? date('Y-m-d H:i:s') : ($novo['token_atualizado_em'] ?: null),
                    ':id' => $id,
                ]);
            } else {
                $ordem = (int) $pdo->query('SELECT COALESCE(MAX(ordem), 0) + 1 FROM instagram_contas')->fetchColumn();

                $pdo->prepare(
                    'INSERT INTO instagram_contas (ordem, ativo, usuario, ig_user_id, access_token, api_host, api_versao, limite_dia, token_atualizado_em)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                )->execute([
                    $ordem, (int) $novo['ativo'], $novo['usuario'], $novo['ig_user_id'] !== '' ? $novo['ig_user_id'] : null,
                    $novo['access_token'], $novo['api_host'], $novo['api_versao'], $novo['limite_dia'],
                ]);
            }

            $incompleta = $novo['ativo'] && ($novo['ig_user_id'] === '');

            if ($incompleta) {
                $aviso[] = 'Falta o ID da conta: use "Salvar e testar conexão" para descobrir pelo token. Sem ele a conta não é usada.';
            }

            flash($incompleta || strpos(implode(' ', $aviso), 'Falha') !== false ? 'alerta' : 'ok', 'Conta ' . $novo['usuario'] . ' salva. ' . implode(' ', $aviso));
            voltar();
        }

        $novo['access_token'] = $existente['access_token'] ?? ''; // o token digitado nunca volta para a tela
        $formConta = $novo;
    } elseif ($acao !== '') {
        http_response_code(400);
        exit('Ação inválida.');
    }
}

// ---------- Dados da tela ----------
$todas = ig_contas($pdo, false);
$cfg = ig_config($pdo);
$publicando = ig_pronto($cfg);

$editarId = (int) ($_GET['editar'] ?? 0);

if ($formConta === null) {
    $formConta = [
        'id' => 0, 'ativo' => 1, 'usuario' => '', 'ig_user_id' => '', 'access_token' => '',
        'api_host' => 'graph.instagram.com', 'api_versao' => 'v21.0', 'limite_dia' => IG_LIMITE_PADRAO,
    ];

    if ($editarId > 0 && ($c = conta_por_id($pdo, $editarId))) {
        $formConta = $c;
    }
}

$editando = (int) $formConta['id'] > 0;
$cheio = count($todas) >= IG_MAX_CONTAS;

$vencendo = [];

foreach ($todas as $c) {
    if ($c['access_token'] !== '' && $c['api_host'] === 'graph.instagram.com' && !empty($c['token_atualizado_em'])) {
        $dias = (int) floor((time() - strtotime($c['token_atualizado_em'])) / 86400);

        if ($dias >= 50) {
            $vencendo[] = $c['usuario'] . ' (' . $dias . ' dias)';
        }
    }
}

$rotulos = [
    'disponivel' => ['publicada', 'Em uso / disponível'],
    'limite'     => ['pendente', 'Limite do dia atingido'],
    'bloqueada'  => ['erro', 'Bloqueada'],
    'desligada'  => ['rejeitada', 'Desligada / incompleta'],
];

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
  <div class="aviso alerta">Token(s) perto de vencer (valem 60 dias): <?= h(implode(', ', $vencendo)) ?>. Use "Renovar token" na lista abaixo.</div>
<?php endif; ?>

<section class="card">
  <h2>Situação</h2>
  <div class="estado">
    <?php if ($publicando): ?>
      <span class="etiqueta publicada">Ligada</span>
      <span>Os resultados enviados vão para a fila de <a href="publicacoes.php">Publicações</a> e saem nos Stories depois que você aprova. As contas são usadas na ordem da lista abaixo.</span>
    <?php else: ?>
      <span class="etiqueta rejeitada">Desligada</span>
      <span>Enquanto estiver desligada, o botão do quiz só salva a imagem para a pessoa publicar no próprio Instagram.</span>
    <?php endif; ?>
  </div>
</section>

<form method="post" class="card" autocomplete="off" style="display: flex; flex-direction: column; gap: 16px;">
  <h2>Geral</h2>
  <?= csrf_campo() ?>
  <input type="hidden" name="acao" value="salvar_geral">

  <label class="marcar">
    <input type="checkbox" name="ativo" value="1" <?= $geral['ativo'] ? 'checked' : '' ?>>
    <span>Publicar os resultados nos Stories das contas abaixo (com aprovação da equipe)</span>
  </label>

  <div class="campos">
    <div class="largo">
      <label for="url_publica">Endereço público do site</label>
      <input type="url" id="url_publica" name="url_publica" value="<?= h($geral['url_publica']) ?>" placeholder="https://suaescola.com.br/quiz">
      <p class="dica">O Instagram baixa a imagem por este endereço, então o site precisa estar na internet (não vale localhost). É o endereço da pasta onde estão o <code>index.html</code> e a pasta <code>api</code>, sem barra no final.</p>
    </div>
  </div>

  <div class="acoes">
    <button class="btn" type="submit">Salvar</button>
  </div>
</form>

<section class="card">
  <h2>Contas que publicam (<?= count($todas) ?> de <?= IG_MAX_CONTAS ?>)</h2>
  <p class="muted" style="margin-bottom: 12px;">
    A conta nº 1 publica até atingir o limite de Stories do dia; depois a nº 2, e assim por diante.
    Se uma conta der erro de limite ou de token, ela é pulada por um tempo e a próxima assume sozinha.
    A contagem usa as últimas 24 horas.
  </p>

  <?php if (!$todas): ?>
    <p class="vazio">Nenhuma conta cadastrada. Adicione a primeira abaixo.</p>
  <?php else: ?>
  <div class="tabela-wrap">
    <table>
      <thead>
        <tr><th>Nº</th><th>Conta</th><th>Situação</th><th>Stories 24 h</th><th>Token</th><th>Ações</th></tr>
      </thead>
      <tbody>
      <?php foreach ($todas as $i => $c):
          $sit = ig_conta_situacao($c);
          $dias = !empty($c['token_atualizado_em']) ? (int) floor((time() - strtotime($c['token_atualizado_em'])) / 86400) : null;
          $ultimo = $i === count($todas) - 1;
      ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td>
            <strong><?= h($c['usuario']) ?></strong>
            <?php if ($c['ig_user_id'] === ''): ?><br><span class="erro-txt">falta o ID (use "Testar")</span><?php endif; ?>
          </td>
          <td>
            <span class="etiqueta <?= h($rotulos[$sit][0]) ?>"><?= h($rotulos[$sit][1]) ?></span>
            <?php if ($sit === 'bloqueada'): ?>
              <br><span class="muted">até <?= h(date('d/m H:i', strtotime($c['bloqueada_ate']))) ?></span>
            <?php endif; ?>
            <?php if ($c['ultimo_erro'] !== '' && $sit !== 'disponivel'): ?>
              <br><span class="erro-txt"><?= h(mb_substr($c['ultimo_erro'], 0, 160)) ?></span>
            <?php endif; ?>
          </td>
          <td><?= (int) $c['usadas_24h'] ?> / <?= (int) $c['limite_dia'] ?></td>
          <td>
            <?= $c['access_token'] !== '' ? '<code>••••' . h(substr($c['access_token'], -4)) . '</code>' : '<span class="muted">–</span>' ?>
            <?php if ($dias !== null): ?><br><span class="muted"><?= $dias ?> dia(s)</span><?php endif; ?>
          </td>
          <td>
            <div class="acoes">
              <?php
              $botao = function (string $acaoBotao, string $rotulo, string $classe = 'sec', string $confirma = '') use ($c) {
                  echo '<form method="post" class="inline"' . ($confirma !== '' ? ' onsubmit="return confirm(\'' . h($confirma) . '\');"' : '') . '>'
                      . csrf_campo()
                      . '<input type="hidden" name="acao" value="' . h($acaoBotao) . '">'
                      . '<input type="hidden" name="id" value="' . (int) $c['id'] . '">'
                      . '<button class="btn ' . $classe . '" type="submit">' . h($rotulo) . '</button></form>';
              };

              if ($i > 0) { $botao('subir', '↑'); }
              if (!$ultimo) { $botao('descer', '↓'); }
              ?>
              <a class="btn sec" href="instagram.php?editar=<?= (int) $c['id'] ?>#form-conta">Editar</a>
              <?php
              $botao('testar', 'Testar');
              if ($c['api_host'] === 'graph.instagram.com' && $c['access_token'] !== '') { $botao('renovar', 'Renovar token'); }
              if ($sit === 'bloqueada') { $botao('liberar', 'Liberar'); }
              $botao((int) $c['ativo'] === 1 ? 'desligar' : 'ligar', (int) $c['ativo'] === 1 ? 'Desligar' : 'Ligar');
              $botao('remover', 'Remover', 'perigo', 'Remover ' . $c['usuario'] . '? O token dela será apagado.');
              ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<?php if ($cheio && !$editando): ?>
  <div class="aviso alerta">Você já cadastrou o máximo de <?= IG_MAX_CONTAS ?> contas. Remova uma para adicionar outra, ou edite uma existente.</div>
<?php else: ?>
<form method="post" class="card" id="form-conta" autocomplete="off" style="display: flex; flex-direction: column; gap: 16px;">
  <h2><?= $editando ? 'Editar ' . h($formConta['usuario']) : 'Adicionar conta' ?></h2>
  <?= csrf_campo() ?>
  <input type="hidden" name="id" value="<?= (int) $formConta['id'] ?>">

  <label class="marcar">
    <input type="checkbox" name="ativo_conta" value="1" <?= $formConta['ativo'] ? 'checked' : '' ?>>
    <span>Usar esta conta para publicar</span>
  </label>

  <div class="campos">
    <div>
      <label for="usuario">@ da conta</label>
      <input type="text" id="usuario" name="usuario" value="<?= h($formConta['usuario']) ?>" placeholder="@senai.maravilha" maxlength="31" required>
      <p class="dica">Precisa ser conta profissional (Comercial ou Criador).</p>
    </div>
    <div>
      <label for="ig_user_id">ID da conta <span class="muted">(opcional)</span></label>
      <input type="text" id="ig_user_id" name="ig_user_id" value="<?= h($formConta['ig_user_id']) ?>" inputmode="numeric" placeholder="Deixe vazio para descobrir sozinho">
      <p class="dica">Se vazio, "Salvar e testar conexão" descobre pelo token.</p>
    </div>

    <div class="largo">
      <label for="access_token">Token de acesso</label>
      <input type="password" id="access_token" name="access_token" value="" placeholder="<?= $editando && $formConta['access_token'] !== '' ? 'Salvo. Cole outro aqui só se quiser trocar.' : 'Cole aqui o token gerado no painel de desenvolvedores da Meta' ?>" autocomplete="new-password">
      <p class="dica">Fica guardado no servidor e nunca é mostrado de volta. Quem tem o token controla a conta, então não envie a ninguém.</p>
    </div>

    <div>
      <label for="limite_dia">Máximo de Stories por dia</label>
      <input type="number" id="limite_dia" name="limite_dia" min="1" max="100" value="<?= (int) $formConta['limite_dia'] ?>">
      <p class="dica">Ao chegar nesse número (em 24 h), passa para a próxima conta. A Meta permite no máximo 100.</p>
    </div>
    <div>
      <label for="api_host">Tipo de token</label>
      <select id="api_host" name="api_host">
        <option value="graph.instagram.com" <?= $formConta['api_host'] === 'graph.instagram.com' ? 'selected' : '' ?>>Login do Instagram (graph.instagram.com)</option>
        <option value="graph.facebook.com" <?= $formConta['api_host'] === 'graph.facebook.com' ? 'selected' : '' ?>>Login do Facebook / Página (graph.facebook.com)</option>
      </select>
    </div>
    <div>
      <label for="api_versao">Versão da API</label>
      <input type="text" id="api_versao" name="api_versao" value="<?= h($formConta['api_versao']) ?>" maxlength="6">
      <p class="dica">Só mude se a Meta avisar que a versão foi desativada.</p>
    </div>
  </div>

  <div class="acoes">
    <button class="btn" type="submit" name="acao" value="salvar_conta_testar">Salvar e testar conexão</button>
    <button class="btn sec" type="submit" name="acao" value="salvar_conta">Só salvar</button>
    <?php if ($editando): ?><a href="instagram.php">Cancelar</a><?php endif; ?>
  </div>
</form>
<?php endif; ?>

<section class="card">
  <h2>Como conseguir o token</h2>
  <details>
    <summary style="cursor: pointer; font-weight: 700;">Ver o passo a passo</summary>
    <ol style="margin: 12px 0 0 20px; display: flex; flex-direction: column; gap: 8px;">
      <li>No aplicativo do Instagram da conta, mude para <strong>conta profissional</strong> (Configurações › Tipo de conta e ferramentas).</li>
      <li>Entre em <a href="https://developers.facebook.com/apps/" target="_blank" rel="noopener">developers.facebook.com/apps</a>, crie um app e adicione o produto <strong>Instagram</strong> (configuração com Login do Instagram). Um mesmo app pode atender várias contas.</li>
      <li>Adicione cada conta ao app e gere o <strong>token de acesso</strong> dela com as permissões <code>instagram_business_basic</code> e <code>instagram_business_content_publish</code>.</li>
      <li>Cole o token acima e clique em <strong>Salvar e testar conexão</strong>. O painel confere a conta e preenche o ID sozinho.</li>
    </ol>
    <p class="dica" style="margin-top: 12px;">
      Regras da Meta: só contas profissionais publicam por API; só é possível marcar contas <strong>públicas</strong>; o limite é de 100 publicações por conta a cada 24 horas.
      Para contas sem função no app, a Meta exige a análise do app (App Review). Detalhes em
      <a href="https://developers.facebook.com/docs/instagram-platform/content-publishing/" target="_blank" rel="noopener">documentação oficial</a>.
    </p>
  </details>
</section>
<?php
rodape();
