<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));

// Start transfer
echo date("Y-m-d H:i:s") . " Start publishing WP posts";
file_put_contents(LOG_DIR . '/publish-posts.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);
$post_status = 'draft';

//Query our MySQL table
$wp_db = new MySQL('wp', 'local');

$query = $wp_db->pdo->prepare("SELECT * FROM `wp_posts` WHERE `post_status` = ? AND `post_type` = 'rz_listing' ORDER BY `wp_posts`.`ID` DESC");
$query->execute([$post_status]);
$rows = $query->fetchAll();
$post_counter = 0;
foreach ($rows as $row) {
    $post_id = $row->ID;
    $post_name = $row->post_name;
    echo 'Post ID - ' . $post_id . ' | ';
    $rz_price = checkPrice($post_id);
    if ($rz_price != 0) {
        $price_per_day = get_custom_price($post_id);
        if (!add_post_meta($post_id, 'price_per_day', $price_per_day, true)) {
            update_post_meta($post_id, 'price_per_day', $price_per_day);
        }
        publishToWp($post_name, $post_id);
        $post_counter++;
        // exit();
    }
}

file_put_contents(LOG_DIR . '/publish-posts.log', ' Added posts: ' . $post_counter, FILE_APPEND);

function publishToWp($post_name, $post_id)
{
    // $image_url = 'adress img';
    try {
        $new_slug = wp_unique_post_slug($post_name, $post_id, 'publish', 'rz_listing', 0);
        $new_post_data = [
            'ID' => $post_id,
            'post_status' => 'publish',
            'post_autor' => 46,
            'post_name' => $new_slug
        ];
        // wp_publish_post( $post_id );
        $response = wp_update_post(wp_slash($new_post_data));
        return $response;
    } catch (Exception $e) {
        echo ($e->getMessage());
        return false;
    }
}

function checkPrice($post_id)
{
    $wp_db = new MySQL('wp', 'local');
    $query = $wp_db->pdo->prepare("SELECT meta_value FROM `wp_postmeta` WHERE `post_id` = ? AND `meta_key` = 'rz_price'");
    $query->execute([$post_id]);
    $rows = $query->fetchAll();
    $rz_price = 0;
    foreach ($rows as $row) {
        if (isset($row->meta_value) && $row->meta_value != NULL && $row->meta_value != 0) {
            $rz_price = $row->meta_value;
        }
    }
    return $rz_price;
}
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

function get_per_day_price($count = 1, $rz_price)
{
    $prices = get_prices();
    $index = '1 bds';
    if ($count > 3) {
        $index = '4+ bds';
    } else {
        $index = $count . ' bds';
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

function calculate_price($count = 1, $rz_price)
{
    $prices = get_prices();
    $index = '1 bds';
    if ($count > 3) {
        $index = '4+ bds';
    } else {
        $index = $count . ' bds';
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

function get_custom_price($listing_id, $Premium_to_Vendor = 1.8)
{

    $prices = get_prices();
    $bedrooms_count = get_post_meta($listing_id, 'rz_bed', true);
    $initial_price = $rz_price = get_post_meta($listing_id, 'rz_price', true);
    $rz_price *= $Premium_to_Vendor;
    $rz_price *= 1.2;

    $index = '1 bds';

    if ($bedrooms_count) {
        if ($bedrooms_count > 3) {
            $index = '4+ bds';
        } else {
            $index = $bedrooms_count . ' bds';
        }
    }

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

    $price = number_format($rz_price, 2);

    $log_filename = $_SERVER['DOCUMENT_ROOT'] . '/wp-content/themes/brikk-child/get_custom_price.txt';

    wph_log_data_global($log_filename, array(
        '$prices[\'Furniture - base\'][$index]' => $prices['Furniture - base'][$index],
        '$prices[\'Electric\'][$index]' => $prices['Electric'][$index],
        '$prices[\'Gas\'][$index]' => $prices['Gas'][$index],
        '$prices[\'Internet\'][$index]' => $prices['Internet'][$index],
        '$prices[\'Cable\'][$index]' => $prices['Cable'][$index],
        '$prices[\'Garbage, Sewage, Pest & Water\'][$index]' => $prices['Garbage, Sewage, Pest & Water'][$index],
        '$prices[\'Housekeeping\'][$index]' => $prices['Housekeeping'][$index],
        '$prices[\'3rd Party Guarantor\'][$index]' => $prices['3rd Party Guarantor'][$index],
        '$prices[\'Damages\'][$index]' => $prices['Damages'][$index],
        '$temp_price' => $temp_price,

        '$prices[\'Setup (one time fee)\'][$index]' => $prices['Setup (one time fee)'][$index],
        '$prices[\'Corp App/ Admin Fee (one time fee)' => $prices['Corp App/ Admin Fee (one time fee)'][$index],
        '$prices[\'Background Check (one time fee)\'][$index]' => $prices['Background Check (one time fee)'][$index],
        '$prices[\'Inspection (one time fee)\'][$index]' => $prices['Inspection (one time fee)'][$index],
        '$temp_price2' => $temp_price2,

        '$rz_price' => $rz_price,
    ), true, true);
    $rz_price = round($rz_price);
    return $rz_price;
}
*/