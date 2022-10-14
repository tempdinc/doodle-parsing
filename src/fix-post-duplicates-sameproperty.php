<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once '../../wp-load.php';

// Start transfer
echo date("Y-m-d H:i:s") . " Start fixing duplicates WP ";
file_put_contents(LOG_DIR . '/fix-duplicates.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

// $wp_db = new MySQL('wp', 'local');

$parsing_db = new MySQL('parsing', 'local');
$unique_properties = $parsing_db->getAllUniquePropertyID();
foreach ($unique_properties as $property) {
    $all_availability = $parsing_db->getAllAvailabilityWithPostByProperty($property->property_id);
    if (count($all_availability) > 1) {
        echo 'Property ID - ' . $property->property_id;
        foreach ($all_availability as $key => $availability) {
            if ($key != 0) {
                echo ' | Post ID - ' . $availability->post_id;
                $post_data = [
                    'ID' => $availability->post_id,
                    'post_status' => 'draft'
                ];
                wp_update_post($post_data);
                $query = $parsing_db->pdo->prepare("UPDATE `availability` SET `post_id` = NULL WHERE `id` = ?");
                $query->execute([$availability->id]);
                // $query = $parsing_db->pdo->prepare("UPDATE `properties` SET `post_id` = ? AND `image_urls` = ? WHERE `id` = ?");
                // $query->execute([$availability->post_id, $availability->image_urls, $property->property_id]);
            } else {
                echo 'post_id' . $availability->post_id . ' | property_id ' . $property->property_id . PHP_EOL;
                $query = $parsing_db->pdo->prepare("UPDATE `properties` SET `post_id` = ? AND `image_urls` = ? WHERE `id` = ?");
                $query->execute([$availability->post_id, $availability->image_urls, $property->property_id]);
            }
        }
        echo ' | ' . count($all_availability) . PHP_EOL;
    } else {
        echo 'post_id' . $all_availability[0]->post_id . ' | property_id ' . $property->property_id . PHP_EOL;
        $query = $parsing_db->pdo->prepare("UPDATE `properties` SET `post_id` = ? AND `image_urls` = ? WHERE `id` = ?");
        $query->execute([$all_availability[0]->post_id, $all_availability[0]->image_urls, $property->property_id]);
    }
}

echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-duplicates.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
