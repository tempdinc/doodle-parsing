<?php

// show all errors
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

// load composer autoload
require_once __DIR__.'/../vendor/autoload.php';

define('ROOT_DIR', __DIR__);
define('LOG_DIR', ROOT_DIR.'/log');

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

/**
 * @param $key
 * @param null $default
 *
 * @return mixed|null
 */
function env($key, $default = null)
{
    $value = $_ENV[$key] ?? null;

    if (!$value) {
        return $default;
    }

    return $value;
}
