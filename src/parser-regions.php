<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';

echo date("Y-m-d H:i:s") . " Get regions - ";
file_put_contents(LOG_DIR . '/get-regions.log', '[' . date('Y-m-d H:i:s') . '] Get regions - ', FILE_APPEND);

echo "Init.. ";

$old_regions = [];

$regionsDB = file_get_contents(__DIR__ . '/regions.json');
$regionsDB = json_decode($regionsDB, true);

$rz_full_regions = [];
foreach ($regionsDB as $regionDB) {
    foreach ($regionDB as $region_key => $region_cities) {
        var_dump($region_key);
        var_dump($region_cities);
        // $rz_full_regions[$region_key] = $region_cities;
        $temp_city = [];
        foreach ($region_cities as $region_city) {
            $region_city_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($region_city)));
            $temp_city[] = [
                'city_name' => $region_city,
                'city_slug' => $region_city_slug
            ];
        }
        $rz_full_regions[$region_key] = $temp_city;
    }
}
file_put_contents('old-regions.json', json_encode($rz_full_regions));

$old_citiesDB = file_get_contents(__DIR__ . '/new-regions.json');
$old_regions = json_decode($old_citiesDB, true);
$old_regions_key = array_keys($old_regions);
var_dump($old_regions);

foreach ($rz_full_regions as $rz_full_region => $rz_full_cities) {
    $array_search = array_search($rz_full_region, $old_regions_key);
    echo ' | ' . $rz_full_region;
    if ($array_search !== false && $old_regions[$rz_full_region] !== NULL) {
        $old_regions[$rz_full_region] = array_merge($old_regions[$rz_full_region], $rz_full_cities);
        $old_regions[$rz_full_region] = array_unique($old_regions[$rz_full_region], SORT_REGULAR);
        array_multisort(array_column($old_regions[$rz_full_region], 'city_slug'), SORT_ASC, $old_regions[$rz_full_region]);
    } else {
        $old_regions[$rz_full_region] = $rz_full_cities;
        var_dump($old_regions);
    }
}

var_dump($old_regions);

file_put_contents('old-regions.json', json_encode($old_regions));

// Clear log files
$f = fopen(LOG_DIR . '/new-regions.json', 'w');
fclose($f);

$citiesDB = file_get_contents(__DIR__ . '/cities.json');
$citiesDB = json_decode($citiesDB, true);
$counter = 0;
foreach ($citiesDB as $states) {
    foreach ($states as $code => $citiesArray) {
        foreach ($citiesArray as $city) {
            $region = strtoupper($city) . ', ' . strtoupper($code);
            $key = array_search($region, $old_regions_key);
            // var_dump($old_regions[$region]);
            if ($key !== false) {
                echo $old_regions_key[$key] . ' >>>>>>>>>> ';
            } else {
                echo '!!!!!!' . $region . ' >>>>>>>>>> ';
            }

            $city = str_replace(' ', '-', strtolower($city));
            $code = strtolower($code);
            $base_link = 'https://rentprogress.com/bin/progress-residential/property-search.market-' . urlencode($city . '-' . $code) . '.json';
            echo $base_link;
            $marketDB = file_get_contents($base_link);
            $marketDB = json_decode($marketDB, true);
            $new_cities = [];
            foreach ($marketDB as $markets) {
                foreach ($markets as $market) {

                    $new_city = $market['city'] . ', ' . $market['state'];
                    $new_city_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($new_city)));
                    // $new_city_slug = str_replace("'", "", str_replace(',', '', str_replace(' ', '-', strtolower($new_city))));
                    /*
                    echo ' <' . $key . '> ';
                    if ($key !== false) {
                        $city_key = array_search($new_city, array_column($old_regions[$region], 'city_name'));
                        echo $new_city . ' | ' . $city_key . ' | ';
                        if ($city_key === false) {
                            $new_cities[] = [
                                'city_name' => $new_city,
                                'city_slug' => $new_city_slug
                            ];
                        }
                    } else {
                    */
                    $new_cities[] = [
                        'city_name' => $new_city,
                        'city_slug' => $new_city_slug
                    ];

                    // }
                    $counter++;
                }
            }
            // var_dump($new_cities);
            if ($key !== false && $old_regions[$region] !== NULL) {
                $new_cities = array_merge($old_regions[$region], $new_cities);
                $new_cities = array_unique($new_cities, SORT_REGULAR);
                array_multisort(array_column($new_cities, 'city_slug'), SORT_ASC, $new_cities);
                // $old_regions[$region] = array_merge($old_regions[$region], $new_cities);
            }
            $old_regions[$region] = $new_cities;
            echo PHP_EOL;
        }
    }
}
echo $counter;
file_put_contents('new-regions.json', json_encode($old_regions));

function array_unique_key($array, $key)
{
    $tmp = $key_array = array();
    $i = 0;

    foreach ($array as $val) {
        if (!in_array($val[$key], $key_array)) {
            $key_array[$i] = $val[$key];
            $tmp[$i] = $val;
        }
        $i++;
    }
    return $tmp;
}
