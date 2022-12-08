<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// use App\Classes\MySQL;
require_once(realpath('../../wp-load.php'));

require_once __DIR__ . '/bootstrap.php';

echo date("Y-m-d H:i:s") . " Transfer regions - ";
file_put_contents(LOG_DIR . '/transfer-regions.log', '[' . date('Y-m-d H:i:s') . '] Transfer regions - ', FILE_APPEND);

echo "Init.. ";
file_put_contents(LOG_DIR . '/transfer-regions.log', 'Init.. ', FILE_APPEND);

$citiesDB = file_get_contents(__DIR__ . '/cities.json');
$citiesDB = json_decode($citiesDB, true);
$regions = [];

foreach ($citiesDB as $states) {
    foreach ($states as $code => $citiesArray) {
        foreach ($citiesArray as $city) {
            $city_new = str_replace(' ', '-', strtolower($city));
            $code_new = str_replace(' ', '-', strtolower($code));
            $city_up = strtoupper($city);
            $code_up = strtoupper($code);
            $regions[] = [
                'name' => $city_up . ', ' . $code_up,
                'slug' => $city_new . '-' . $code_new
            ];
        }
    }
}

foreach ($regions as $region) {
    $result = insertNewTerm($region['name'], $region['slug']);
    echo $result;
}

function insertNewTerm($region_name, $region_slug)
{
    $insert_res = wp_insert_term(
        $region_name,  // новый термин
        'rz_regions', // таксономия
        array(
            'description' => '',
            'slug'        => $region_slug,
            'parent'      => 0
        )
    );

    if (is_wp_error($insert_res)) {
        echo $insert_res->get_error_message();
        return false;
    } else {
        return $insert_res['term_id'];
    }
}
