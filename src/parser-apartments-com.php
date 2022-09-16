<?php

use App\Classes\MySQL;
use App\Classes\Redis;
use App\Classes\QueueApartmentsCom as Queue;

require_once __DIR__ . '/bootstrap.php';

//Fork settings
pcntl_async_signals(true);

pcntl_signal(SIGTERM, 'signalHandler'); // Termination ('kill' was called)
pcntl_signal(SIGHUP, 'signalHandler'); // Terminal log-out
pcntl_signal(SIGINT, 'signalHandler'); // Interrupted (Ctrl-C is pressed)

// save parent pid
file_put_contents('parentPid.out', getmypid());

echo "Apartments.com - ";

if (isset($argv[1]) && $argv[1] === 'init') {
    echo "Init.." . PHP_EOL;

    $redis = Redis::init();
    $redis->flushall();

    $citiesDB = file_get_contents(__DIR__ . '/cities.json');
    $citiesDB = json_decode($citiesDB, true);

    /**
     * paste first links in queue
     * and now we can get links for parsing...
     */
    foreach ($citiesDB as $states) {
        foreach ($states as $code => $citiesArray) {
            foreach ($citiesArray as $city) {
                $city = str_replace(' ', '-', strtolower($city));
                $code = strtolower($code);
                $base_link = 'https://www.apartments.com/' . urlencode($city . '-' . $code) . '/';
                $task = '{"link":"' . $base_link . '", "method":"get_links"}';
                $redis->rpush('tasks', $task);
            }
        }
    }
} elseif (isset($argv[1]) && 'update' === $argv[1]) {
    echo "Update.." . PHP_EOL;
    $db = new MySQL('parsing','local');
    $redis = Redis::init();
    $redis->flushall();
    $dateNow = date('Y-m-d H:i:s');

    $query = $db->pdo->prepare(
        "SELECT `link` FROM `properties` WHERE (TIMESTAMPDIFF(day, last_update, '$dateNow') >= "
            . env('DIFF_OF_DAYS', 1) .
            ") AND is_deleted = 0 AND link LIKE '%apartments.com%'"
    );
    $query->execute();
    $links = $query->fetchAll();

    // print_r($links);    
    // Adding tasks to queue
    foreach ($links as $link) {
        $task = '{"link":"' . $link->link . '", "method":"update"}';
        $redis->rpush('tasks', $task);
    }
} elseif (isset($argv[1]) && 'byZip' === $argv[1]) {
    echo "Init by zip.." . PHP_EOL;

    $redis = Redis::init();
    $redis->flushall();

    $citiesDB = file_get_contents(__DIR__ . '/cities.json');
    $citiesDB = json_decode($citiesDB, true);

    /**
     * paste first links in queue
     * and now we can get links for parsing...
     */
    foreach ($citiesDB as $states) {
        foreach ($states as $code => $citiesArray) {
            foreach ($citiesArray as $city) {
                $city = str_replace(' ', '-', strtolower($city));
                $code = strtolower($code);
                $base_link = 'https://www.apartments.com/' . urlencode($city . '-' . $code) . '/';
                $task = '{"link":"' . $base_link . '", "method":"get_links", "by":"zip"}';
                // echo 'Parser: ' . $task . PHP_EOL;
                $redis->rpush('tasks', $task);
            }
        }
    }
}

echo "Start.." . PHP_EOL;

$queue = new Queue();
$queue->startApartmentsCom();

function signalHandler($signal)
{
    global $queue;
    unset($queue);
    exit;
}
