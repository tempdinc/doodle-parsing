<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));

$f = fopen(LOG_DIR . '/remove-data.log', 'w');
fclose($f);

// Start transfer
echo date("Y-m-d H:i:s") . " Start remove from WP ";
file_put_contents(LOG_DIR . '/remove-data.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);
/*
// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
$all_availability = $parsing_db->getAvailabilityWithPost();
echo " \033[34mUnits with post - " . count($all_availability) . "\033[0m";
file_put_contents(LOG_DIR . '/remove-data.log', ' | Units with post - ' . count($all_availability), FILE_APPEND);
*/
$wp_db = new MySQL('wp', 'local');
$listing_type = '25769';
$wp_db = new MySQL('wp', 'local');
$total_posts = $wp_db->countPostsRZListing($listing_type);
echo 'Total posts - ' . $total_posts . PHP_EOL;
$pages = intdiv($total_posts, 100);
echo 'Total pages - ' . $pages . PHP_EOL;
for ($i = 0; $i <= $pages; $i++) {
    echo PHP_EOL . 'Page - ' . $i . ' | ';
    $all_availability = $wp_db->getPostsRZListing($listing_type, 0, 100);
    foreach ($all_availability as $availability) {
        echo ' post id - ' . $availability->id . ' | ';
        // echo 'post_id' . $availability->post_id . ' - ';
        file_put_contents(LOG_DIR . '/remove-data.log', ' | Post - ' . $availability->id, FILE_APPEND);
        $query = $wp_db->pdo->prepare("SELECT 'meta_value' FROM `wp_postmeta` WHERE 'meta_key' = 'rz_gallery' AND 'post_id' = ?");
        $query->execute([$availability->id]);
        $rows = $query->fetchAll();
        foreach ($rows as $row) {
            $image_ids = json_decode($row->meta_value);
            if (isset($image_ids) && count($image_ids) > 0) {
                foreach ($image_ids as $image_id) {
                    // echo $image_id->id . ' | ';
                    deleteAttachmentWP($image_id->id);
                    // echo $deletion_result;
                }
                // echo PHP_EOL;
            }
        }
        deletePostWP($availability->id);
    }
}

function deleteAttachmentWP($post_id)
{
    return wp_delete_attachment($post_id, true);
}

function deletePostWP($post_id)
{
    return wp_delete_post($post_id, true);
}

echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/remove-data.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
