<?php
/**
 * Conexão com o banco (PDO) e funções de apoio compartilhadas pela API e pelo painel.
 */

require_once __DIR__ . '/config.php';

/** Retorna a conexão com o MySQL (criada uma única vez). */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '-03:00'",
        ]);
    }

    return $pdo;
}

/** Escapa texto para exibir em HTML. */
function h($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formata 11999999999 como (11) 99999-9999. */
function formatar_celular(string $celular): string
{
    if (strlen($celular) === 11) {
        return sprintf('(%s) %s-%s', substr($celular, 0, 2), substr($celular, 2, 5), substr($celular, 7));
    }

    if (strlen($celular) === 10) {
        return sprintf('(%s) %s-%s', substr($celular, 0, 2), substr($celular, 2, 4), substr($celular, 6));
    }

    return $celular;
}
