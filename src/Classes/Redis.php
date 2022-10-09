<?php

namespace App\Classes;

use Exception;
use Predis\Client;

class Redis
{
    protected $redis;

    public function __construct()
    {
        try {
            $this->redis = new Client([
                'host' => env('REDIS_HOST', '127.0.0.1'),
                'port' => env('REDIS_PORT', 6379),
                'async' => true,
                'read_write_timeout' => 10,
            ]);
        } catch (\Predis\Connection\ConnectionException $e) {
            die($e->getMessage());
        }
    }

    public function __destruct()
    {
        $this->redis = null;
    }

    /**
     * @return Redis
     */
    public static function init()
    {
        return new self();
    }

    public function flushall()
    {
        $this->redis->flushall();

        return $this;
    }

    public function rpush($list, $value)
    {
        return $this->redis->rpush($list, $value);
    }

    public function lpop($list)
    {
        return $this->redis->lpop($list);
    }

    public function lrange($list, $from = 0, $to = -1)
    {
        return $this->redis->lrange($list, $from, $to);
    }

    public function blpop($list, $index = 0)
    {
        return $this->redis->blpop($list, $index)[1];
    }

    public function brpop($list, $index = 0)
    {
        try {
            return $this->redis->brpop($list, $index)[1];
        } catch (Exception $e) {
            // echo 'Redis brpop exception: ',  $e->getMessage(), PHP_EOL;
        }
    }
}
