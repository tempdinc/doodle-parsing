<?php

namespace App\Classes;

class ImageUnitCrawlerApartmentsCom
{
    public static function send($key, $rentalId, $rentalType)
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://www.apartments.com/services/rental/attachments",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "{\"ListingKey\":\"" . $key . "\", \"RentalId\":\"" . $rentalId . "\", \"RentalType\":\"" . $rentalType . "\"}",
            CURLOPT_HTTPHEADER => [
                "User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:81.0) Gecko/20100101 Firefox/81.0",
                "Accept: application/json, text/javascript, */*; q=0.01",
                "Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3",
                "Content-Type: application/json",
                "X-Requested-With: XMLHttpRequest",
                "Origin: https://www.apartments.com",
                "Connection: keep-alive",
                "TE: Trailers"
            ],
        ]);
        curl_setopt($curl, CURLOPT_PROXY, env('PROXY', ''));

        $response = json_decode(curl_exec($curl), true);

        $data = [];

        foreach ($response as $arr) {
            $data[] = $arr['Uri'];
        }

        curl_close($curl);

        return (count($data) > 0) ? json_encode($data, JSON_PRETTY_PRINT) : '';
    }
}
