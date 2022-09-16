<?php

use App\Classes\MySQL;
use App\Classes\Redis;
use App\Classes\QueueRentProgressCom as Queue;

require_once __DIR__ . '/bootstrap.php';

//Fork settings
pcntl_async_signals(true);

pcntl_signal(SIGTERM, 'signalHandler'); // Termination ('kill' was called)
pcntl_signal(SIGHUP, 'signalHandler'); // Terminal log-out
pcntl_signal(SIGINT, 'signalHandler'); // Interrupted (Ctrl-C is pressed)

// save parent pid
file_put_contents('parentPid.out', getmypid());

echo "RentProgress.com - ";

if (isset($argv[1]) && $argv[1] === 'init') {
    echo 'Init..' . PHP_EOL;

    $redis = Redis::init();
    $redis->flushall();

    $citiesDB = file_get_contents(__DIR__ . '/cities.json');
    $citiesDB = json_decode($citiesDB, true);

    $class = '\\App\\Classes\\ParseUrlRentProgressCom';

    /**
     * paste first links in queue
     * and now we can get links for parsing...
     */
    foreach ($citiesDB as $states) {
        foreach ($states as $code => $citiesArray) {
            foreach ($citiesArray as $city) {
                $city = str_replace(' ', '-', strtolower($city));
                $code = strtolower($code);
                $base_link = 'https://rentprogress.com/bin/progress-residential/property-search.market-' . urlencode($city . '-' . $code) . '.json';
                echo 'Link - ' . $base_link . PHP_EOL;
                $redis->rpush('tasks', json_encode([
                    'class' => $class,
                    'link'  => $base_link
                ]));
            }
        }
    }
} elseif (isset($argv[1]) && $argv[1] === 'update') { // Giving parser the task to check records relevance
    /*
    echo "Update.." . PHP_EOL;
    $redis = Redis::init();
    $db = new MySQL;

    $class = '\\App\\Classes\\CheckLinksRentProgressCom';

    // Getting all records from the db for checking 
    $allRecords = $db->getAllRecordsDate('properties');
    // Adding records to queue
    foreach ($allRecords as $record) {
        print_r($record->link);
        $redis->rpush('tasks', json_encode([
            'class' => $class,
            'link'  => $record->link
        ]));
    }
    */
    echo "Update.." . PHP_EOL;
    $db = new MySQL('parsing','local');
    $redis = Redis::init();
    $redis->flushall();
    $dateNow = date('Y-m-d H:i:s');

    $query = $db->pdo->prepare(
        "SELECT `link` FROM `properties` WHERE (TIMESTAMPDIFF(day, last_update, '$dateNow') >= "
            . env('DIFF_OF_DAYS', 1) .
            ") AND is_deleted = 0 AND link LIKE '%rentprogress.com%'"
    );
    $query->execute();
    $links = $query->fetchAll();

    // print_r($links);
    $class = '\\App\\Classes\\CheckLinksRentProgressCom';
    // Adding tasks to queue
    foreach ($links as $link) {
        $redis->rpush('tasks', json_encode([
            'class' => $class,
            'link'  => $link->link
        ]));
    }
}

// Parsing start
echo 'Parse..' . PHP_EOL;

$queue = new Queue();
$queue->startRentProgressCom();

// Stop signals handler
function signalHandler($signal)
{
    global $queue;
    unset($queue);
    exit;
}
