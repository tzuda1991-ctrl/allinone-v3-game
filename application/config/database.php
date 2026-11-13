<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$active_group = 'default';
$query_builder = TRUE;

$db_host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? '127.0.0.1'));
$db_port = getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: ($_ENV['DB_PORT'] ?? '3306'));
$db_user = getenv('MYSQLUSER') ?: (getenv('DB_USERNAME') ?: ($_ENV['DB_USERNAME'] ?? 'root'));
$db_pass = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASSWORD') ?: ($_ENV['DB_PASSWORD'] ?? ''));
$db_name = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'railway'));

$db['default'] = array(
    'dsn'      => '',
    'hostname' => $db_host,
    'port'     => (int)$db_port,
    'username' => $db_user,
    'password' => $db_pass,
    'database' => $db_name,
    'dbdriver' => 'mysqli',
    'dbprefix' => '',
    'pconnect' => FALSE,
    'db_debug' => FALSE,
    'cache_on' => FALSE,
    'cachedir' => '',
    'char_set' => 'utf8',
    'dbcollat' => 'utf8_general_ci',
    'swap_pre' => '',
    'encrypt'  => FALSE,
    'compress' => FALSE,
    'stricton' => FALSE,
    'failover' => array(),
    'save_queries' => TRUE
);
