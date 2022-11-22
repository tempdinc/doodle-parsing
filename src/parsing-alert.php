<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';

// Start transfer
file_put_contents(LOG_DIR . '/parsing-alert.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

$mailResult = mail('domus159@gmail.com', 'Test parsing alert', 'Test message');

var_dump($mailResult);

echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/parsing-alert.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
