<?php

namespace App\Classes;

use Exception;
use DiDom\Document;

class QueueRentProgressCom
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
            foreach ($this->pid as $pid => $task) {
                posix_kill($pid, SIGTERM);
                Redis::init()->rpush('tasks', json_encode([
                    'class' => $task['class'],
                    'link' => $task['link'],
                    'location' => $task['location']
                ]));
            }
        }
    }

    /**
     * Starting parser and distributing tasks among forks
     */
    public function startRentProgressCom()
    {
        while (!$this->stop) {
            $this->checkPid();
            $task = json_decode(Redis::init()->brpop('tasks'), true);
            // echo $task['link'] . PHP_EOL;
            if (count($this->pid) < $this->limit && $task) {
                // echo 'Queue fork...' . PHP_EOL;
                $this->fork($task);
            } elseif (count($this->pid) >= $this->limit && $task) {
                // echo 'Queue rpush...' . PHP_EOL;
                Redis::init()->rpush('tasks', json_encode($task));
                sleep(1);
            } elseif (!$task) {
                // echo 'COMPLETED PARSING RentProgress.com!' . PHP_EOL;
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

    /**
     * Creating the forms
     *
     * @param  array $task
     */
    protected function fork($task)
    {
        $pid = pcntl_fork();

        if (-1 === $pid) {
            die('Could not fork');
        }

        if ($pid) {
            $this->pid[$pid] = $task;
        } else {
            $this->parent = false;
            $this->parse($task);
            exit();
        }
    }

    /**
     * Pulling the content and passing tasks to parsers classes
     *
     * @param  array $task
     * @return bool
     */
    protected function parse($task)
    {
        $request = StormProxy::send($task['link'], 'rentprogress.com');
        if ($request['http_code'] === 200) {
            $method = ($task['class'] == '\App\Classes\ParseLinkPageRentProgressCom') ? 'parse' : 'get_links';
            file_put_contents(LOG_DIR . '/rentprogress-com-data-crawler.log', '[' . date('Y-m-d H:i:s') . '] ' . $task['link'] . ' - ' . $method . PHP_EOL, FILE_APPEND);
            if (!$request['response']) {
                Redis::init()->rpush('tasks', json_encode([
                    'link' => $task['link'],
                    'class' => $task['class'],
                    'location' => $task['location']
                ]));

                return false;
            }

            // Creating class instance for parsing
            $class = new $task['class']($request['response'], $task);

            //Run the class
            $class->parse();

            return true;
        } elseif ($request['http_code'] === 403 || $request['http_code'] === 0) {
            file_put_contents(LOG_DIR . '/parse-problem.log', '[' . date('Y-m-d H:i:s') . '] Msg:' . $e->getMessage() . "\nCode:" . $request['http_code'] . ' - ' . $task['link'] . PHP_EOL, FILE_APPEND);
            Redis::init()->rpush('tasks', json_encode([
                'link' => $task['link'],
                'class' => $task['class'],
                'location' => $task['location']
            ]));

            return false;
        } elseif ($request['http_code'] === 404) {
            file_put_contents(LOG_DIR . '/404links.log', '[' . date('Y-m-d H:i:s') . '] Msg:' . $e->getMessage() . "\nCode:" . $request['http_code'] . ' - ' . $task['link'] . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents(LOG_DIR . '/parse-problem.log', '[' . date('Y-m-d H:i:s') . '] Msg:' . $e->getMessage() . "\nCode:" . $request['http_code'] . ' - ' . $task['link'] . PHP_EOL, FILE_APPEND);
        }

        // If response is != 200, pushing the task again
        Redis::init()->rpush('tasks', json_encode([
            'link' => $task['link'],
            'class' => $task['class'],
            'location' => $task['location']
        ]));

        return false;
    }
}
