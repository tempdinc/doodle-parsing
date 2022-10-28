<?php
namespace App\Classes;

use Exception;
use DiDom\Document;

class QueueApartmentsCom
{
    // Max number of forks
    protected $limit;
    // Stop for cycle
    protected $stop = false;
    // An array with child pid
    protected $pid = [];
    // Defines the parent process
    protected $parent = true;

    public function __construct()
    {
        $this->limit = env('FORK_LIMIT', 100);
    }

    /**
     * Adding the tasks back to queue
     */
    public function __destruct()
    {
        if (true === $this->parent) {
            foreach ($this->pid as $pid => $link) {
                posix_kill($pid, SIGTERM);
                Redis::init()->rpush('aborted_links', $link);
            }
        }
    }

    /**
     * Starting parser and distributing tasks among forks
     */    
    public function startApartmentsCom()
    {
        global $counter_tasks;
        global $counter_errors;

        while (!$this->stop) {
            $this->checkPid();
            $task = json_decode(Redis::init()->brpop('tasks'), true);
            
            if ($task && $task['method'] == 'get_links') {
                // echo $task['method'];
            } else {
                // echo $task['method'];
                // if($task['method'] == 'parse') $new_counter = $taskCounter->increment();
            }

            $requestCounter = new Counter();
            $requestCounter->incrementTask(); // Counting requests

            if (count($this->pid) < $this->limit && $task) {
                // get link
                $link = $task['link'];
                $method = $task['method'];
                $by = $task['by'] ?? null;
                $this->fork($link, $method, $by);
            } elseif (count($this->pid) >= $this->limit && $task) {
                Redis::init()->rpush('tasks', json_encode($task));
                sleep(1);
            } elseif (!$task) {
                // we does not have tasks, and have free proccess, so parsing completed
                // echo "COMPLETED PARSING Apartments.com!" . PHP_EOL;
                $this->stop = true;
            }
        }
    }

    /**
     * Checking pids
     *
     * @return void
     */
    protected function checkPid()
    {
        foreach (array_keys($this->pid) as $pid) {
            $res = pcntl_waitpid($pid, $status, WNOHANG);

            if (-1 === $res || $res > 0) {
                unset($this->pid[$pid]);
            }
        }
    }

    protected function fork($link, $method = 'parse', $by = null)
    {
        $pid = pcntl_fork();

        if (-1 === $pid) {
            die('Could not fork');
        }

        // if fork is ok then do next
        if ($pid) {
            $this->pid[$pid] = $link;
        } else {
            $this->parent = false;
            $this->parse($link, $method, $by);
            exit();
        }
    }

    protected function parse($link, $method = 'parse', $by)
    {
        $request = StormProxy::send($link, 'www.apartments.com');
        $task = '{"link":"' . $link . '", "method":"' . $method . '", "by":"' . $by . '"}';

        if ($request['http_code'] === 200) {
            try {
                // parse data
                if (is_string($request['response']) && $request['response'] !== '') {
                    $content = new Document($request['response']);
                    $crawler = new DataCrawlerApartmentsCom();
                    $crawler->parse($content, $link, $method, $by);
                } else {
                    Redis::init()->rpush('tasks', $task);
                }
            } catch (Exception $e) {
                echo 'Error Msg:' . $e->getMessage() . "\nCode:" . $request['http_code'] . ' - ' . $link . PHP_EOL;
                file_put_contents(LOG_DIR . '/parse-problem.log', '[' . date('Y-m-d H:i:s') . '] Msg:' . $e->getMessage() . "\nCode:" . $request['http_code'] . ' - ' . $link . PHP_EOL, FILE_APPEND);
            }

            return true;
        } elseif (404 !== $request['http_code']) {
            // back link if not 404
            Redis::init()->rpush('tasks', $task);

            return false;
        }

        if ($method === 'update') {
            $db = new MySQL('parsing','local');
            $date = date('Y-m-d H:i:s');
            $query = $db->pdo->prepare("UPDATE `properties` SET `last_update` = ?, is_deleted = ? WHERE `link` = ?");
            $query->execute([$date, 1, $link]);
        } else {
            file_put_contents(LOG_DIR . '/404links.log', '[' . date('Y-m-d H:i:s') . '] ' . $link . ' method: ' . $method . PHP_EOL, FILE_APPEND);
        }
        return false;
    }
}
