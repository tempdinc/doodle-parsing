<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/bootstrap.php';
require_once '../../wp-load.php';

// Start transfer
echo date("Y-m-d H:i:s") . " Start remove from WP ";
file_put_contents(LOG_DIR . '/remove-data.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
$all_availability = $parsing_db->getAvailabilityWithPost();
echo " \033[34mUnits with post - " . count($all_availability) . "\033[0m";
file_put_contents(LOG_DIR . '/remove-data.log', ' | Units with post - ' . count($all_availability), FILE_APPEND);

$wp_db = new MySQL('wp', 'local');
foreach ($all_availability as $availability) {
    echo 'post_id' . $availability->post_id . ' - ';
    $query = $wp_db->pdo->prepare("SELECT `meta_value` FROM `wp_postmeta` WHERE `meta_key` = 'rz_gallery' AND `post_id` = ?");
    $query->execute([$availability->post_id]);
    $rows = $query->fetchAll();
    foreach($rows as $row) {
        $image_ids = json_decode($row->meta_value);
        foreach($image_ids as $image_id) {
            // echo $image_id->id . ' | ';
            deleteAttachmentWP($image_id->id);
            // echo $deletion_result;
        }
        echo PHP_EOL;
    }
    deletePostWP($availability->post_id);
}

function deleteAttachmentWP($post_id) {
    return wp_delete_attachment( $post_id, true );
}

function deletePostWP($post_id) {
    return wp_delete_post( $post_id, true );
}

echo " >>> " . date("Y-m-d H:i:s") ." - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/remove-data.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
