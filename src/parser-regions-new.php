<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));

echo date("Y-m-d H:i:s") . "Parse regions - ";
file_put_contents(LOG_DIR . '/parse-regions.log', '[' . date('Y-m-d H:i:s') . '] Parse regions - ', FILE_APPEND);

// NEW FULL REGIONS
$f = fopen(LOG_DIR . '/new-regions.json', 'w');
fclose($f);

// Checking TERMS rz_regions for existing regions from cities.json
$citiesDB = file_get_contents(__DIR__ . '/cities.json');
$citiesDB = json_decode($citiesDB, true);
$counter = 0;
foreach ($citiesDB as $states) {
    foreach ($states as $code => $citiesArray) {
        foreach ($citiesArray as $city) {
            $region = strtoupper($city) . ', ' . strtoupper($code);
            $region_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($region)));
            echo ' | region_slug - ' . $region_slug;
            $region_term = get_term_by('slug', $region_slug, 'rz_regions');
            var_dump($region_slug);
            if ($region_term === false) {
                $rz_region_id = insertNewTerm($region, $region_slug);
            }
        }
    }
}
// exit();

$old_regions = [];

$regionsDB = file_get_contents(__DIR__ . '/regions.json');
$regionsDB = json_decode($regionsDB, true);

$full_regions = [];
foreach ($regionsDB as $regionDB) {
    foreach ($regionDB as $region_key => $region_cities) {
        $temp_city = [];
        foreach ($region_cities as $region_city) {
            $region_city_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($region_city)));
            $temp_city[] = [
                'city_name' => $region_city,
                'city_slug' => $region_city_slug
            ];
        }
        $full_regions[$region_key] = $temp_city;
    }
}
file_put_contents('old-regions.json', json_encode($full_regions));
unset($regionsDB);

$old_citiesDB = file_get_contents(__DIR__ . '/new-regions.json');
$old_regions = json_decode($old_citiesDB, true);
$old_regions_key = array_keys($old_regions);
foreach ($full_regions as $full_region => $full_cities) {
    $array_search = array_search($full_region, $old_regions_key);
    if ($array_search !== false && $old_regions[$full_region] !== NULL) {
        $old_regions[$full_region] = array_merge($old_regions[$full_region], $full_cities);
        $old_regions[$full_region] = array_unique($old_regions[$full_region], SORT_REGULAR);
        array_multisort(array_column($old_regions[$full_region], 'city_slug'), SORT_ASC, $old_regions[$full_region]);
    } else {
        $old_regions[$full_region] = $full_cities;
    }
}
file_put_contents('old-regions.json', json_encode($old_regions));
// unset($old_regions);
// exit();

$citiesDB = file_get_contents(__DIR__ . '/cities.json');
$citiesDB = json_decode($citiesDB, true);
$counter = 0;
foreach ($citiesDB as $states) {
    foreach ($states as $code => $citiesArray) {
        foreach ($citiesArray as $city) {
            unset($marketDB);
            $region = strtoupper($city) . ', ' . strtoupper($code);
            $region_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($region)));

            $key = array_search($region, $old_regions_key);

            $city = str_replace(' ', '-', strtolower($city));
            $code = strtolower($code);
            echo $city . '-' . $code . PHP_EOL;
            var_dump(memory_get_usage());
            $base_link = 'https://rentprogress.com/bin/progress-residential/property-search.market-' . urlencode($city . '-' . $code) . '.json';
            $marketLink = file_get_contents($base_link);
            // echo strlen($marketLink) . PHP_EOL;
            $marketDB = json_decode($marketLink, true);
            $new_cities = [];
            foreach ($marketDB as $markets) {
                if (!empty($market)) {
                    foreach ($markets as $market) {
                        $new_city = $market['city'] . ', ' . $market['state'];
                        $new_city_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($new_city)));

                        $new_cities[] = [
                            'city_name' => $new_city,
                            'city_slug' => $new_city_slug
                        ];
                        $counter++;
                    }
                }
            }

            if ($key !== false && $old_regions[$region] !== NULL) {
                $new_cities = array_merge($old_regions[$region], $new_cities);
                $new_cities = array_unique($new_cities, SORT_REGULAR);
                array_multisort(array_column($new_cities, 'city_slug'), SORT_ASC, $new_cities);
            }
            $old_regions[$region] = $new_cities;
        }
    }
}

foreach ($full_regions as $full_region => $full_cities) {
    $array_search = array_search($full_region, $old_regions_key);
    if ($array_search !== false && $old_regions[$full_region] !== NULL) {
        $old_regions[$full_region] = array_merge($old_regions[$full_region], $full_cities);
        $old_regions[$full_region] = array_unique($old_regions[$full_region], SORT_REGULAR);
        array_multisort(array_column($old_regions[$full_region], 'city_slug'), SORT_ASC, $old_regions[$full_region]);
    } else {
        $old_regions[$full_region] = $full_cities;
    }
}

$rz_full_regions = [];
foreach ($old_regions as $full_region => $full_cities) {
    $region_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($full_region)));
    foreach ($full_cities as $full_city) {
        // $region_slug
        $rz_full_regions[] = [
            'city_name'     => strtoupper($full_city['city_name']),
            'city_slug'     => $full_city['city_slug'],
            'region_name'   => $full_region,
            'region_slug'   => $region_slug
        ];
    }
}
file_put_contents('new-regions.json', json_encode($old_regions));
file_put_contents('new-cities.json', json_encode($rz_full_regions));

file_put_contents(LOG_DIR . '/parse-regions.log', ' total added cities - ' . $counter . PHP_EOL . ' END >>> ' . date('Y-m-d H:i:s'), FILE_APPEND);
// NEW FULL REGIONS END
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
