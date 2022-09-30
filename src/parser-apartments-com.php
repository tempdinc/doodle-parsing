<?php

$counter_tasks = 0;
$counter_errors = 0;

use App\Classes\MySQL;
use App\Classes\Redis;
use App\Classes\QueueApartmentsCom as Queue;
use App\Classes\Counter;

require_once __DIR__ . '/bootstrap.php';

//Fork settings
pcntl_async_signals(true);

pcntl_signal(SIGTERM, 'signalHandler'); // Termination ('kill' was called)
pcntl_signal(SIGHUP, 'signalHandler'); // Terminal log-out
pcntl_signal(SIGINT, 'signalHandler'); // Interrupted (Ctrl-C is pressed)

// Clear log files
$f = fopen(LOG_DIR . '/apartments-com-data-crawler.log', 'w');
fclose($f);
$f = fopen(LOG_DIR . '/parse-problem.log', 'w');
fclose($f);
$f = fopen(LOG_DIR . '/404links.log', 'w');
fclose($f);

// save parent pid
file_put_contents('parentPid.out', getmypid());

echo date("Y-m-d H:i:s") . " Apartments.com - ";
file_put_contents(LOG_DIR . '/apartments-com.log', '[' . date('Y-m-d H:i:s') . '] Apartments.com - ', FILE_APPEND);

$filter = '';

if (isset($argv[1]) && $argv[1] === 'short-term') {
    $filter = 'short-term/';
}

if (isset($argv[2]) && $argv[2] === 'short-term') {
    $filter = 'short-term/';
}

if (isset($argv[1]) && 'update' === $argv[1]) {
    echo "Update.. ";
    file_put_contents(LOG_DIR . '/apartments-com.log', 'Update.. ', FILE_APPEND);
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
    echo "Init by zip.. ";
    file_put_contents(LOG_DIR . '/apartments-com.log', 'Init by zip.. ', FILE_APPEND);
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
                $base_link = 'https://www.apartments.com/' . urlencode($city . '-' . $code) . '/' . $filter;
                $task = '{"link":"' . $base_link . '", "method":"get_links", "by":"zip"}';
                // echo 'Parser: ' . $task . PHP_EOL;
                $redis->rpush('tasks', $task);
            }
        }
    }
} else {
    echo "Init.. ";
    file_put_contents(LOG_DIR . '/apartments-com.log', 'Init.. ', FILE_APPEND);
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
                $base_link = 'https://www.apartments.com/' . urlencode($city . '-' . $code) . '/' . $filter;
                $task = '{"link":"' . $base_link . '", "method":"get_links"}';
                $redis->rpush('tasks', $task);
            }
        }
    }
}

$queue = new Queue();
$queue->startApartmentsCom();

$parse_counter = lines(LOG_DIR . '/apartments-com-data-crawler.log');
$parse_problem_counter = lines(LOG_DIR . '/parse-problem.log');
$parse_404_counter = lines(LOG_DIR . '/404links.log');

echo ">>> " . date("Y-m-d H:i:s") ." - End.. Links processed:" . $parse_counter . " \033[31mParse problems received:" . $parse_problem_counter . "\033[0m" . " \033[34mError 404 received:" . $parse_404_counter . "\033[0m" . PHP_EOL;
file_put_contents(LOG_DIR . '/apartments-com.log', '>>> [' . date('Y-m-d H:i:s') . '] - End.. Links processed:' . $parse_counter . ' Parse problems received:' . $parse_problem_counter . ' Error 404 received:' . $parse_404_counter  . PHP_EOL, FILE_APPEND);

function signalHandler($signal)
{
    global $queue;
    unset($queue);
    exit;
}

// Count files lines
function lines($file) { 
    if(!file_exists($file)) return 0;

    $file_arr = file($file); 
    $lines = count($file_arr); 
    return $lines; 
} 