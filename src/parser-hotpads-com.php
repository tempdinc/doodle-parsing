<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;
use App\Classes\Redis;
use App\Classes\QueueHotpadsCom as Queue;

require_once __DIR__ . '/bootstrap.php';

//Fork settings
pcntl_async_signals(true);

pcntl_signal(SIGTERM, 'signalHandler'); // Termination ('kill' was called)
pcntl_signal(SIGHUP, 'signalHandler'); // Terminal log-out
pcntl_signal(SIGINT, 'signalHandler'); // Interrupted (Ctrl-C is pressed)

// Saving parent pid
file_put_contents('parentPid.out', getmypid());

echo "Hotpads.com - ";

$http_errr = 0;

// parser initialization
if (isset($argv[1]) && $argv[1] == 'init') {
    echo 'Init..' . PHP_EOL;

    $redis = Redis::init();
    $redis->flushall();

    $zipcodes = file_get_contents(__DIR__ . '/zipcodes.json'); // Reading zipcodes from file
    $zipcodes = json_decode($zipcodes, true);

    // Passing initial tasks into task queue
    foreach ($zipcodes as $zipcode) {
        $link = 'https://hotpads.com/' . $zipcode . '/apartments-for-rent?border=false';
        $redis->rpush('tasks', json_encode([
            'link' => $link,
            'class' => 'App\\Classes\\PaginateParseHotpadsCom'
        ]));
    }
} elseif (isset($argv[1]) && $argv[1] == 'update') { // Giving parser the task to check records relevance
    echo "Update.." . PHP_EOL;
    $db = new MySQL('parsing','local'); // Test
    $redis = Redis::init();
    $redis->flushall();
    $dateNow = date('Y-m-d H:i:s');

    $query = $db->pdo->prepare(
        "SELECT `link` FROM `properties` WHERE (TIMESTAMPDIFF(day, last_update, '$dateNow') >= "
            . env('DIFF_OF_DAYS', 1) .
            ") AND is_deleted = 0 AND link LIKE '%hotpads.com%'"
    );
    $query->execute();
    $links = $query->fetchAll();

    // print_r($links);
    $class = '\\App\\Classes\\CheckLinksHotpadsCom';
    // Adding tasks to queue
    foreach ($links as $link) {
        $redis->rpush('tasks', json_encode([
            'class' => $class,
            'link'  => $link->link
        ]));
    }
}

// parseing start
echo 'Parse..' . PHP_EOL;

static $http_errr = 0;

$queue = new Queue;
$queue->startHotpadsCom();

echo 'End.............................................' . PHP_EOL;

// Stop signals handler
function signalHandler($signal)
{
    global $queue;
    unset($queue);
    exit;
}
