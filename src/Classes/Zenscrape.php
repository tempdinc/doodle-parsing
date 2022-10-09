<?php

namespace App\Classes;

class Zenscrape
{
    public static function send($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);

        $data = [
            'url' => $url,
        ];

        curl_setopt($ch, CURLOPT_URL, 'https://app.zenscrape.com/api/v1/get?'.http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: '.env('ZENSCRAPE_KEY'),
        ]);

        $response = curl_exec($ch);

        // get info about request
        $info = curl_getinfo($ch);

        curl_close($ch);

        return [
            'http_code' => $info['http_code'],
            'response' => $response,
        ];
    }
}
