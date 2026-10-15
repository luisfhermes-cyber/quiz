<?php
/**
 * Configuração do Quiz SENAI.
 * Edite apenas os valores abaixo.
 */

// ----- Banco de dados (XAMPP: usuário "root" e senha vazia) -----
define('DB_HOST', getenv('QUIZ_DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('QUIZ_DB_NAME') ?: 'quiz_senai');
define('DB_USER', getenv('QUIZ_DB_USER') ?: 'root');
define('DB_PASS', getenv('QUIZ_DB_PASS') !== false ? getenv('QUIZ_DB_PASS') : '');

// ----- Senha do painel (admin/). TROQUE antes de publicar o site! -----
define('ADMIN_SENHA', getenv('QUIZ_ADMIN_SENHA') ?: 'trocar-esta-senha');

date_default_timezone_set('America/Sao_Paulo');
