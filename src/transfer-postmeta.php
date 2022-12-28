<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));
// Clear log files
$f = fopen(LOG_DIR . '/transfer-postmeta.log', 'w');
fclose($f);


// Start transfer
echo date("Y-m-d H:i:s") . " Start publishing WP posts";
file_put_contents(LOG_DIR . '/transfer-postmeta.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);


$premium_to_vendor = array(1.8, 1.8, 1.7, 1.6, 1.5, 1.4, 1.3, 1.25, 1.2, 1.2, 1.2, 1.2, 1.2);


$wp_db = new MySQL('wp', 'local');

/*
// NEW REGIONS START
$terms = get_terms([
    'taxonomy' => 'rz_regions',
    'hide_empty' => false,
]);

$rz_regions = [];
foreach ($terms as $term) {
    $rz_regions[] = [
        'term_id'   => $term->term_id,
        'name'      => $term->name,
        'slug'      => $term->slug
    ];
}
$rz_full_cities = file_get_contents(__DIR__ . '/new-cities.json');
$rz_full_cities = json_decode($rz_full_cities, true);
$rz_full_regions = [];
foreach ($rz_full_cities as $rz_full_city) {
    $rz_regions_key = array_search($rz_full_city['region_slug'], array_column($rz_regions, 'slug'));
    $term_id = $rz_regions[$rz_regions_key]['term_id'];
    $term_name = $rz_regions[$rz_regions_key]['name'];
    $term_slug = $rz_regions[$rz_regions_key]['slug'];
    $rz_full_regions[] = [
        'term_id'       => $term_id,
        'city_name'     => $rz_full_city['city_name'],
        'city_slug'     => $rz_full_city['city_slug'],
        'region_name'   => $rz_full_city['region_name'],
        'region_slug'   => $rz_full_city['region_slug']
    ];
}
// NEW REGIONS END
var_dump($rz_full_regions);
exit();
*/

// Create table wp_units if not exists
$query = $wp_db->pdo->prepare("SELECT * FROM information_schema.tables WHERE table_schema = 'wp_tempd'  AND table_name = 'wp_units' LIMIT 1;");
$query->execute();
$post_ids = $query->fetchAll();

if (empty($post_ids)) {
    $query = "CREATE TABLE `wp_units` (
        `post_id` bigint NOT NULL,
        `rz_search` tinyint DEFAULT NULL,
        `rz_ranking` tinyint DEFAULT '0',
        `rz_bed_min` tinyint NOT NULL DEFAULT '0',
        `rz_bed_max` tinyint NOT NULL DEFAULT '0',
        `rz_bath_min` decimal(4,1) NOT NULL DEFAULT '0.0',
        `rz_bath_max` decimal(4,1) NOT NULL DEFAULT '0.0',
        `rz_sqft_min` mediumint NOT NULL DEFAULT '0',
        `rz_sqft_max` mediumint NOT NULL DEFAULT '0',
        `rz_source_price_min` mediumint DEFAULT NULL,
        `rz_source_price_max` mediumint DEFAULT NULL,
        `price_per_night_min` mediumint DEFAULT NULL,
        `price_per_night_max` mediumint DEFAULT NULL,
        `rz_listing_type` mediumint DEFAULT NULL,
        `rz_lng` decimal(9,6) DEFAULT NULL,
        `rz_lat` decimal(9,6) DEFAULT NULL,
        `rz_listing_region` varchar(256) DEFAULT NULL,
        `rz_unit_type` varchar(256) DEFAULT NULL,
        `rz_status` varchar(256) DEFAULT NULL,
        `rz_booking_type` varchar(256) DEFAULT NULL,
        `rz_post_address1` text,
        `rz_post_address2` text,
        `rz_multi_units` text,
        `rz_gallery` text,
        `rz_unit_source_url` text,
        `rz_amenities_list` text,
        INDEX (post_id),
        PRIMARY KEY (post_id)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3";
    $query = $wp_db->pdo->prepare($query);
    $query->execute();
}

$query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_posts` WHERE `post_type` = ? LIMIT 1");
$query->execute(['rz_listing']);
$total_rz_listing = $query->fetchColumn();
$pages = intdiv($total_rz_listing, 500);
file_put_contents(LOG_DIR . '/transfer-postmeta.log', ' Total posts - ' . $total_rz_listing . PHP_EOL, FILE_APPEND);
echo 'Total posts - ' . $total_rz_listing . PHP_EOL;
$listing_type = ['25769', '380'];
$counter_wrong_posts = 0;
$counter_clear_posts = 0;
$counter_clear_post_content_meta = 0;
$removed_posts_counter = 0;
$post_data = [];
for ($i = 0; $i <= $pages + 1; $i++) {
    echo ' | page #' . $i . PHP_EOL;
    unset($posts);
    $start = $i * 500 - $removed_posts_counter;
    $removed_posts_counter = 0;
    $posts = $wp_db->getAllPostsRZListing($start, 500);
    foreach ($posts as $post) {
        $post_meta = $wp_db->getAllMetaByPost($post->id);
        $post_meta_data = [];
        foreach ($post_meta as $meta) {
            $post_meta_data[$meta['meta_key']] = $meta['meta_value'];
        }
        $post_data[$post->id] = $post_meta_data;
    }
}
$add_sql = "INSERT INTO `wp_units` (
    `post_id`,
    `rz_search`,
    `rz_ranking`,
    `rz_bed_min`,
    `rz_bed_max`,
    `rz_bath_min`,
    `rz_bath_max`,
    `rz_sqft_min`,
    `rz_sqft_max`,
    `rz_source_price_min`,
    `rz_source_price_max`,
    `price_per_night_min`,
    `price_per_night_max`,
    `rz_listing_type`,
    `rz_lng`,
    `rz_lat`,
    `rz_listing_region`,
    `rz_unit_type`,
    `rz_status`,
    `rz_booking_type`,
    `rz_post_address1`,
    `rz_post_address2`,
    `rz_multi_units`,
    `rz_gallery`,
    `rz_unit_source_url`,
    `rz_amenities_list`
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$update_sql = "UPDATE `wp_units` SET
    `rz_search` = ?,
    `rz_ranking` = ?,
    `rz_bed_min` = ?,
    `rz_bed_max` = ?,
    `rz_bath_min` = ?,
    `rz_bath_max` = ?,
    `rz_sqft_min` = ?,
    `rz_sqft_max` = ?,
    `rz_source_price_min` = ?,
    `rz_source_price_max` = ?,
    `price_per_night_min` = ?,
    `price_per_night_max` = ?,
    `rz_listing_type` = ?,
    `rz_lng` = ?,
    `rz_lat` = ?,
    `rz_listing_region` = ?,
    `rz_unit_type` = ?,
    `rz_status` = ?,
    `rz_booking_type` = ?,
    `rz_post_address1` = ?,
    `rz_post_address2` = ?,
    `rz_multi_units` = ?,
    `rz_gallery` = ?,
    `rz_unit_source_url` = ?,
    `rz_amenities_list` = ?
    WHERE `post_id` = ? ";
foreach ($post_data as $key => $value) {
    file_put_contents(LOG_DIR . '/transfer-postmeta.log', 'Post ID - ' . $key . ' > ', FILE_APPEND);
    $check_query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_units` WHERE `post_id` = ? LIMIT 1");
    $check_query->execute([$key]);
    $is_duplicate = $check_query->fetchColumn();
    $terms = get_the_terms($key, 'rz_regions');
    $term = array_shift($terms);
    $term_slug = $term->slug;
    // exit();
    $rz_search = (isset($value['rz_search']) && $value['rz_search'] !== NULL) ? $value['rz_search'] : 0;
    $rz_ranking = (isset($value['rz_ranking']) && $value['rz_ranking'] !== NULL) ? $value['rz_ranking'] : 0;
    $value['rz_bedroom'] = str_replace(['-', '–', '—', '―'], '–', $value['rz_bedroom']);
    if (stripos($value['rz_bedroom'], '–') === false) {
        $rz_bed_min = intval($value['rz_bedroom']);
        $rz_bed_max = intval($value['rz_bedroom']);
    } else {
        $rz_beds = explode('–', $value['rz_bedroom']);
        $rz_bed_min = intval($rz_beds[0]);
        $rz_bed_max = intval($rz_beds[1]);
    }
    $value['rz_bathrooms'] = str_replace(['-', '–', '—', '―'], '–', $value['rz_bathrooms']);
    if (stripos($value['rz_bathrooms'], '–') === false) {
        $rz_bath_min = intval($value['rz_bathrooms']);
        $rz_bath_max = intval($value['rz_bathrooms']);
    } else {
        $rz_baths = explode('–', $value['rz_bathrooms']);
        $rz_bath_min = intval($rz_baths[0]);
        $rz_bath_max = intval($rz_baths[1]);
    }
    $value['rz_sqft'] = str_replace(['-', '–', '—', '―'], '–', $value['rz_sqft']);
    if (stripos($value['rz_sqft'], '–') === false) {
        $rz_sqft_min = intval($value['rz_sqft']);
        $rz_sqft_max = intval($value['rz_sqft']);
    } else {
        $rz_sqfts = explode('–', $value['rz_sqft']);
        $rz_sqft_min = intval($rz_sqfts[0]);
        $rz_sqft_max = intval($rz_sqfts[1]);
    }
    $value['rz_price'] = str_replace(['-', '–', '—', '―'], '–', $value['rz_price']);
    file_put_contents(LOG_DIR . '/transfer-postmeta.log', 'rz_price - ' . $value['rz_price'] . ' | ', FILE_APPEND);
    if (stripos($value['rz_price'], '–') === false) {
        $rz_source_price_min = intval($value['rz_price']);
        $rz_source_price_max = intval($value['rz_price']);
    } else {
        $rz_source_prices = explode('–', $value['rz_price']);
        // file_put_contents(LOG_DIR . '/transfer-postmeta.log', print_r($rz_source_prices, true), FILE_APPEND);
        $rz_source_price_min = intval($rz_source_prices[0]);
        file_put_contents(LOG_DIR . '/transfer-postmeta.log', 'rz_source_price_min - ' . $rz_source_price_min . ' | ', FILE_APPEND);
        $rz_source_price_max = intval($rz_source_prices[1]);
        file_put_contents(LOG_DIR . '/transfer-postmeta.log', 'rz_source_price_max - ' . $rz_source_price_max . ' | ', FILE_APPEND);
    }
    $prices_per_night = calculate_prices_per_night($rz_source_price_min, $premium_to_vendor, $rz_bed_min);
    $price_per_night_min = $prices_per_night[1];
    $prices_per_night = calculate_prices_per_night($rz_source_price_max, $premium_to_vendor, $rz_bed_max);
    $price_per_night_max = $prices_per_night[0];
    // Todo check region - $value['rz_listing_region']
    $good_type = in_array($value['rz_listing_type'], $listing_type);
    if ($good_type) {
        if ($is_duplicate == 0) {
            $add_query = $wp_db->pdo->prepare($add_sql);
            $add_query->execute([
                $key,
                $rz_search,
                $rz_ranking,
                $rz_bed_min,
                $rz_bed_max,
                $rz_bath_min,
                $rz_bath_max,
                $rz_sqft_min,
                $rz_sqft_max,
                $rz_source_price_min,
                $rz_source_price_max,
                $price_per_night_min,
                $price_per_night_max,
                $value['rz_listing_type'],
                floatval($value['rz_location__lng']),
                floatval($value['rz_location__lat']),
                $term_slug,
                trim($value['rz_unit_type']),
                trim($value['rz_status']),
                trim($value['rz_booking_type']),
                trim($value['rz_post_address1']),
                trim($value['rz_post_address2']),
                $value['rz_multi_units'],
                $value['rz_gallery'],
                $value['rz_apartment_uri'],
                $value['rz_amenities_list']
            ]);
            $wp_post_id = $wp_db->pdo->lastInsertId();
        } else {
            $update_query = $wp_db->pdo->prepare($update_sql);
            $update_query->execute([
                $rz_search,
                $rz_ranking,
                $rz_bed_min,
                $rz_bed_max,
                $rz_bath_min,
                $rz_bath_max,
                $rz_sqft_min,
                $rz_sqft_max,
                $rz_source_price_min,
                $rz_source_price_max,
                $price_per_night_min,
                $price_per_night_max,
                $value['rz_listing_type'],
                floatval($value['rz_location__lng']),
                floatval($value['rz_location__lat']),
                $term_slug,
                trim($value['rz_unit_type']),
                trim($value['rz_status']),
                trim($value['rz_booking_type']),
                trim($value['rz_post_address1']),
                trim($value['rz_post_address2']),
                $value['rz_multi_units'],
                $value['rz_gallery'],
                $value['rz_apartment_uri'],
                $value['rz_amenities_list'],
                $key
            ]);
            $wp_post_id = $wp_db->pdo->lastInsertId();
        }
    }
    // exit();
}
echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/transfer-postmeta.log', '[' . date('Y-m-d H:i:s') . ']  END >>> ' . PHP_EOL, FILE_APPEND);
/*
function get_prices()
{
    $prices['Furniture - base'] = array();
    $prices['Furniture - base']['1 bds'] = 750.00;
    $prices['Furniture - base']['2 bds'] = 900.00;
    $prices['Furniture - base']['3 bds'] = 1250.00;
    $prices['Furniture - base']['4+ bds'] = 1500.00;
    $prices['Furniture - Upgraded'] = array();
    $prices['Furniture - Upgraded']['1 bds'] = 900.00;
    $prices['Furniture - Upgraded']['2 bds'] = 1080.00;
    $prices['Furniture - Upgraded']['3 bds'] = 1500.00;
    $prices['Furniture - Upgraded']['4+ bds'] = 1800.00;
    $prices['Furniture - luxury'] = array();
    $prices['Furniture - luxury']['1 bds'] = 1080.00;
    $prices['Furniture - luxury']['2 bds'] = 1296.00;
    $prices['Furniture - luxury']['3 bds'] = 1800.00;
    $prices['Furniture - luxury']['4+ bds'] = 2160.00;
    $prices['Electric'] = array();
    $prices['Electric']['1 bds'] = 150.00;
    $prices['Electric']['2 bds'] = 185.00;
    $prices['Electric']['3 bds'] = 215.00;
    $prices['Electric']['4+ bds'] = 250.00;
    $prices['Gas'] = array();
    $prices['Gas']['1 bds'] = 50.00;
    $prices['Gas']['2 bds'] = 65.00;
    $prices['Gas']['3 bds'] = 75.00;
    $prices['Gas']['4+ bds'] = 90.00;
    $prices['Internet'] = array();
    $prices['Internet']['1 bds'] = 100.00;
    $prices['Internet']['2 bds'] = 100.00;
    $prices['Internet']['3 bds'] = 100.00;
    $prices['Internet']['4+ bds'] = 100.00;
    $prices['Cable'] = array();
    $prices['Cable']['1 bds'] = 75.00;
    $prices['Cable']['2 bds'] = 75.00;
    $prices['Cable']['3 bds'] = 75.00;
    $prices['Cable']['4+ bds'] = 75.00;
    $prices['Garbage, Sewage, Pest & Water'] = array();
    $prices['Garbage, Sewage, Pest & Water']['1 bds'] = 60.00;
    $prices['Garbage, Sewage, Pest & Water']['2 bds'] = 70.00;
    $prices['Garbage, Sewage, Pest & Water']['3 bds'] = 85.00;
    $prices['Garbage, Sewage, Pest & Water']['4+ bds'] = 100.00;
    $prices['Housekeeping'] = array();
    $prices['Housekeeping']['1 bds'] = 125.00;
    $prices['Housekeeping']['2 bds'] = 175.00;
    $prices['Housekeeping']['3 bds'] = 225.00;
    $prices['Housekeeping']['4+ bds'] = 250.00;
    $prices['3rd Party Guarantor'] = array();
    $prices['3rd Party Guarantor']['1 bds'] = 300.00;
    $prices['3rd Party Guarantor']['2 bds'] = 300.00;
    $prices['3rd Party Guarantor']['3 bds'] = 300.00;
    $prices['3rd Party Guarantor']['4+ bds'] = 300.00;
    $prices['Damages'] = array();
    $prices['Damages']['1 bds'] = 50.00;
    $prices['Damages']['2 bds'] = 50.00;
    $prices['Damages']['3 bds'] = 50.00;
    $prices['Damages']['4+ bds'] = 50.00;
    $prices['Setup (one time fee)'] = array();
    $prices['Setup (one time fee)']['1 bds'] = 150.00;
    $prices['Setup (one time fee)']['2 bds'] = 200.00;
    $prices['Setup (one time fee)']['3 bds'] = 300.00;
    $prices['Setup (one time fee)']['4+ bds'] = 400.00;
    $prices['Corp App/ Admin Fee (one time fee)'] = array();
    $prices['Corp App/ Admin Fee (one time fee)']['1 bds'] = 200.00;
    $prices['Corp App/ Admin Fee (one time fee)']['2 bds'] = 200.00;
    $prices['Corp App/ Admin Fee (one time fee)']['3 bds'] = 200.00;
    $prices['Corp App/ Admin Fee (one time fee)']['4+ bds'] = 200.00;
    $prices['Background Check (one time fee)'] = array();
    $prices['Background Check (one time fee)']['1 bds'] = 150.00;
    $prices['Background Check (one time fee)']['2 bds'] = 150.00;
    $prices['Background Check (one time fee)']['3 bds'] = 150.00;
    $prices['Background Check (one time fee)']['4+ bds'] = 150.00;
    $prices['Non-Refundable Pet Fee (one time fee)'] = array();
    $prices['Non-Refundable Pet Fee (one time fee)']['1 bds'] = 0;
    $prices['Non-Refundable Pet Fee (one time fee)']['2 bds'] = 0;
    $prices['Non-Refundable Pet Fee (one time fee)']['3 bds'] = 0;
    $prices['Non-Refundable Pet Fee (one time fee)']['4+ bds'] = 0;
    $prices['Pet Fee (conditional on # of pets)'] = array();
    $prices['Pet Fee (conditional on # of pets)']['1 bds'] = 0;
    $prices['Pet Fee (conditional on # of pets)']['2 bds'] = 0;
    $prices['Pet Fee (conditional on # of pets)']['3 bds'] = 0;
    $prices['Pet Fee (conditional on # of pets)']['4+ bds'] = 0;
    $prices['Inspection (one time fee)'] = array();
    $prices['Inspection (one time fee)']['1 bds'] = 200.00;
    $prices['Inspection (one time fee)']['2 bds'] = 200.00;
    $prices['Inspection (one time fee)']['3 bds'] = 200.00;
    $prices['Inspection (one time fee)']['4+ bds'] = 200.00;

    return $prices;
}
*/
function calculate_price_per_night_max($rz_price, $bed_cnt = 1)
{
    $prices = get_prices();
    $index = '1 bds';
    if ($bed_cnt > 3) {
        $index = '4+ bds';
    } else {
        $index = $bed_cnt . ' bds';
    }
    $rz_price *= 1.8;
    $rz_price *= 1.2;

    $temp_price = 0;
    $temp_price += $prices['Furniture - base'][$index];
    $temp_price += $prices['Electric'][$index];
    $temp_price += $prices['Gas'][$index];
    $temp_price += $prices['Internet'][$index];
    $temp_price += $prices['Cable'][$index];
    $temp_price += $prices['Garbage, Sewage, Pest & Water'][$index];
    $temp_price += $prices['Housekeeping'][$index];
    $temp_price += $prices['3rd Party Guarantor'][$index];
    $temp_price += $prices['Damages'][$index];

    $rz_price += $temp_price;
    $rz_price /= 30.5;

    $temp_price2 = 0;
    $temp_price2 += $prices['Setup (one time fee)'][$index];
    $temp_price2 += $prices['Corp App/ Admin Fee (one time fee)'][$index];
    $temp_price2 += $prices['Background Check (one time fee)'][$index];
    $temp_price2 += $prices['Inspection (one time fee)'][$index];
    $temp_price2 /= 30.5;

    $rz_price += $temp_price2;
    $rz_price = round($rz_price);

    return $rz_price;
}

function calculate_prices_per_night($rz_price, $premium_to_vendor, $bed_cnt = 1)
{
    $prices = get_prices();
    $index = '1 bds';
    if ($bed_cnt > 3) {
        $index = '4+ bds';
    } else {
        $index = $bed_cnt . ' bds';
    }

    $furniture_base = 0;
    $furniture_base = !empty($prices['Furniture - base'][$index]) ? $prices['Furniture - base'][$index] : 0;

    $utilities = 0;
    $utilities += $prices['Electric'][$index];
    $utilities += $prices['Gas'][$index];
    $utilities += $prices['Internet'][$index];
    $utilities += $prices['Cable'][$index];
    $utilities += $prices['Garbage, Sewage, Pest & Water'][$index];

    $cleaning = $prices['Housekeeping'][$index];

    $other = 0;
    $other += $prices['3rd Party Guarantor'][$index];
    $other += $prices['Damages'][$index];

    //1-Time Fees (Setup + Corp App/Admin Fee + Background Check + Inspection)
    $one_time_fees = 0;
    $one_time_fees += $prices['Setup (one time fee)'][$index];
    $one_time_fees += $prices['Corp App/ Admin Fee (one time fee)'][$index];
    $one_time_fees += $prices['Background Check (one time fee)'][$index];
    $one_time_fees += $prices['Inspection (one time fee)'][$index];

    $temp_price_max1 = $rz_price * $premium_to_vendor[0] * 1.2;
    $temp_price_min1 = $rz_price * end($premium_to_vendor) * 1.2;
    $temp_price2 = $furniture_base + $utilities + $cleaning + $other;
    $temp_price3 = $one_time_fees;

    $prices = [];
    $prices[0] = ($temp_price_max1 + $temp_price2) / 29.5;
    $prices[1] = ($temp_price_min1 + $temp_price2) / 29.5;
    $prices[0] = round($prices[0] + $temp_price3 / 29.5);
    $prices[1] = round($prices[1] + $temp_price3 / 364);

    // var_dump($prices);

    return $prices;
}

/*
function calculate_price($rz_price, $bed_cnt = 1)
{
    $prices = get_prices();
    $index = '1 bds';
    if ($bed_cnt > 3) {
        $index = '4+ bds';
    } else {
        $index = $bed_cnt . ' bds';
    }

    $temp_price2 = 0;
    $temp_price2 += $prices['Setup (one time fee)'][$index];
    $temp_price2 += $prices['Corp App/ Admin Fee (one time fee)'][$index];
    $temp_price2 += $prices['Background Check (one time fee)'][$index];
    $temp_price2 += $prices['Inspection (one time fee)'][$index];
    $temp_price2 /= 30.5;
    $rz_price -= $temp_price2;

    $rz_price *= 30.5;
    $temp_price = 0;
    $temp_price += $prices['Furniture - base'][$index];
    $temp_price += $prices['Electric'][$index];
    $temp_price += $prices['Gas'][$index];
    $temp_price += $prices['Internet'][$index];
    $temp_price += $prices['Cable'][$index];
    $temp_price += $prices['Garbage, Sewage, Pest & Water'][$index];
    $temp_price += $prices['Housekeeping'][$index];
    $temp_price += $prices['3rd Party Guarantor'][$index];
    $temp_price += $prices['Damages'][$index];
    $rz_price -= $temp_price;

    $rz_price /= 1.2;
    $rz_price /= 1.8;
    $rz_price = floor($rz_price);
    return $rz_price;
}
*/