<?php

namespace App\Classes;

class Proxy
{
    protected const API_BASE = 'https://api.best-proxies.ru/proxylist.txt?includeType=&level=1,2&speed=1,2&limit=0';
    public static array $pid = [];

    public static function load(): void
    {
        $url = self::API_BASE.'&key='.env('PROXY_KEY');
        $response = file_get_contents($url);
        $proxies = explode("\r\n", $response);

        self::log(count($proxies));

        if ($proxies) {
            while (count($proxies) > 0) {
                // check forks
                self::checkPid();

                if (count(self::$pid) <= 100) {
                    $proxy = array_pop($proxies);
                    $pid = pcntl_fork();

                    if ($pid) {
                        self::$pid[] = $pid;
                    } else {
                        $status = self::checkProxy($proxy);

                        if ($status) {
                            Redis::init()->rpush('proxies', $proxy);
                        }
                        exit;
                    }
                } else {
                    sleep(1);
                }
            }
        } else {
            self::log('Can not get proxies');
        }
    }

    public static function get(): ?string
    {
        return Redis::init()->lpop('proxies');
    }

    protected static function log($message): void
    {
        $message = '['.date('Y-m-d H:i:s').'] '.$message;
        file_put_contents(LOG_DIR.'/proxies.log', $message, FILE_APPEND);
    }

    protected static function checkPid(): void
    {
        foreach (self::$pid as $key => $pid) {
            $res = pcntl_waitpid($pid, $status, WNOHANG);

            // If the process has already exited
            if (-1 === $res || $res > 0) {
                unset(self::$pid[$key]);
            }
        }
    }

    protected static function checkProxy($proxy, $timeout = 15)
    {
        // You can use virtually any website here, but in case you need to implement other proxy settings (show annonimity level)
        // I'll leave you with whatismyipaddress.com, because it shows a lot of info.
        $url = 'http://whatismyipaddress.com/';

        $theHeader = curl_init($url);
        curl_setopt($theHeader, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($theHeader, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($theHeader, CURLOPT_PROXY, $proxy);

        //This is not another workaround, it's just to make sure that if the IP uses some god-forgotten CA we can still work with it ;)
        //Plus no security is needed, all we are doing is just 'connecting' to check whether it exists!
        curl_setopt($theHeader, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($theHeader, CURLOPT_SSL_VERIFYPEER, 0);

        //Execute the request
        return curl_exec($theHeader);
    }
}
