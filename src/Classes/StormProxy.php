<?php

namespace App\Classes;

class StormProxy
{
    public static function send($url, $referer)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'authority: ' . $referer,
            'cache-control: no-cache',
            'upgrade-insecure-requests: 1',
            'scheme: https',
            'user-agent: Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36',
            'accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'sec-fetch-site: same-origin',
            'sec-fetch-mode: navigate',
            'sec-fetch-user: ?1',
            'sec-fetch-dest: document',
            'accept-language: en-US,en;q=0.9',
            'referer: https://' . $referer . '/',
        ]);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 100);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_PROXY, env('PROXY', ''));

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);

        // print_r($response);
        if ($info['http_code'] == 200) {
            // echo $info['http_code'];
            /*
            $newnewnew = new Counter();
            $counter = $newnewnew->incrementTask();
            */
        } else {
            echo " \033[31m" . $info['http_code'] . "\033[0m ";
        }

        return [
            'http_code' => $info['http_code'],
            'response'  => $response,
            'debug'     => $info,
        ];
    }
}
