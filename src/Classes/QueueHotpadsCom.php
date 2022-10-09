<?php

namespace App\Classes;

use Exception;
use DiDom\Document;

class QueueHotpadsCom
{
    // Max number of forks
    protected $limit;
    // Stop for cycle
    protected $stop = false;
    // An array with child pid
    protected $pid = [];
    // Defines the parent process
    protected $parent = true;
    // HTTP Response
    protected $http_response;
    // Http Errors
    // public static $http_errr;

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
                ]));
            }
        }
    }

    /**
     * Starting parser and distributing tasks among forks
     */
    public function startHotpadsCom()
    {
        // global $http_errr;
        while (!$this->stop) {
            $this->checkPid();
            $task = json_decode(Redis::init()->brpop('tasks'), true);
            // echo $task['link'] . PHP_EOL;
            if (count($this->pid) < $this->limit && $task) {
                $this->fork($task);
            } elseif (count($this->pid) >= $this->limit && $task) {
                Redis::init()->rpush('tasks', json_encode($task));
                sleep(1);
            } elseif (!$task) {
                $this->stop = true;
                exit();
            }
            // echo 'startHotPadsCom: hththt - ' . $http_errr . PHP_EOL;
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
     * Creating the forks
     *
     * @param  array $task
     */
    protected function fork($task)
    {
        global $http_errr;
        $pid = pcntl_fork();

        if (-1 === $pid) {
            die('Could not fork');
        }

        if ($pid) {
            $this->pid[$pid] = $task;
        } else {
            $this->parent = false;
            $http_response = $this->parse($task);
            if (!$http_response) {
                $http_errr++;
                echo 'http_errr - ' . $http_errr . ' ';
            }
            sleep(2);
            return false;
            exit();
        }
    }

    /**
     * Pulling the content and passing tasks to parsers classes
     *
     * @param  array $task
     * @return bool
     */
    public function parse($task)
    {
        $request = StormProxy::send($task['link'], 'hotpads.com');
        // echo 'http_code - ' . $request['http_code'] . PHP_EOL;
        if ($request['http_code'] === 200) {
            if (!$request['response']) {
                Redis::init()->rpush('tasks', json_encode([
                    'link' => $task['link'],
                    'class' => $task['class'],
                ]));

                return false;
            }

            $content = new Document($request['response']);

            // echo $request['response'];

            // Creating class instance for parseing
            $class = new $task['class']($content, $task);
            // Run the class
            $class->parse();

            return true;
        } elseif ($request['http_code'] === 403 || $request['http_code'] === 0) {
            Redis::init()->rpush('tasks', json_encode([
                'link' => $task['link'],
                'class' => $task['class'],
            ]));
            // echo 'parse - 403' . PHP_EOL;
            // self::$http_errr++;
            return false;
        } elseif ($request['http_code'] === 404 && $task['class'] == '\\App\\Classes\\CheckLinksHotpadsCom') { // Initializing the class for checking ads when 404
            $class = new $task['class']($content = null, $task, true);
            $class->parse();

            return true;
        } elseif ($request['http_code'] === 404) { // Removing non-working urls without the class for checking
            return false;
        }

        // If response is != 200, pushing the task again
        Redis::init()->rpush('tasks', json_encode([
            'link' => $task['link'],
            'class' => $task['class'],
        ]));

        return false;
    }

    /**
     * Cleaning the text
     *
     * @param  string $text
     * @return string
     */
    public static function clearText($text)
    {
        $text = preg_replace('/(?:&nbsp;|\h)+/u', ' ', $text);
        $text = preg_replace('/\h*(\R)\s*/u', '$1', $text);
        $text = trim($text);

        return $text;
    }
}
