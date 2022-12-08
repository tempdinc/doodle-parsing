<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));

// Start transfer
echo date("Y-m-d H:i:s") . " Start fixing rentprogress WP ";
file_put_contents(LOG_DIR . '/fix-rentprogress.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
$all_availability = $parsing_db->getAvailabilityWithPostWithPropertySourceRentprogress();
echo " \033[34mUnits with post - " . count($all_availability) . "\033[0m";
file_put_contents(LOG_DIR . '/fix-rentprogress.log', ' | Units with post - ' . count($all_availability), FILE_APPEND);

$wp_db = new MySQL('wp', 'local');
foreach ($all_availability as $availability) {
    // echo 'post_id' . $availability->post_id . ' - ';
    $np_rz_location__address = $availability->city . ', ' . $availability->state_cd . ', US';
    $np_rz_location__state_country = $availability->state_cd . ', US';
    if (isset($availability->addr_line_1) && $availability->addr_line_1 !== NULL && $availability->addr_line_1 != '' && isset($availability->addr_line_2) && $availability->addr_line_2 !== NULL && $availability->addr_line_2 != '') {
        $np_rz_location__address_line1 = $availability->addr_line_1 . ' - ' . $availability->addr_line_2;
    } elseif (isset($availability->addr_line_1) && $availability->addr_line_1 !== NULL && $availability->addr_line_1 != '') {
        $np_rz_location__address_line1 = $availability->addr_line_1;
    } else {
        $np_rz_location__address_line1 = $availability->addr_line_2;
    }
    $np_rz_location__address_line2 = $availability->city . ', ' . $availability->state_cd . ' ' . $availability->zip5_cd;
    file_put_contents(LOG_DIR . '/fix-rentprogress.log', ' | Post - ' . $availability->av_post_id, FILE_APPEND);
    $query = $wp_db->pdo->prepare("DELETE FROM `wp_postmeta` WHERE `post_id` = ? AND `meta_key` = ?");
    $query->execute([$availability->av_post_id, 'rz_post_address1']);
    $query->execute([$availability->av_post_id, 'rz_post_address2']);
    $query->execute([$availability->av_post_id, 'rz_street_line_1']);
    $query->execute([$availability->av_post_id, 'rz_street_line_2']);
    $query = $wp_db->pdo->prepare("INSERT INTO `wp_postmeta` (`post_id`,`meta_key`,`meta_value`) VALUES (?,?,?)");
    $query->execute([$availability->av_post_id, 'rz_post_address1', $np_rz_location__address_line1]);
    $query->execute([$availability->av_post_id, 'rz_post_address2', $np_rz_location__address_line2]);
    $query->execute([$availability->av_post_id, 'rz_street_line_1', $np_rz_location__address_line1]);
    $query->execute([$availability->av_post_id, 'rz_street_line_2', $np_rz_location__address_line2]);
}

echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-rentprogress.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
