<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));
// Clear log files
$f = fopen(LOG_DIR . '/remove-property-noimage-noparsing.log', 'w');
fclose($f);
// Start transfer
echo date("Y-m-d H:i:s") . " Start fixing wrong cities ";
file_put_contents(LOG_DIR . '/remove-property-noimage-noparsing.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

$parsing_db = new MySQL('parsing', 'local');

$query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE image_urls = '' LIMIT 1");
$query->execute();
$total_availability = $query->fetchColumn();
echo 'Total availability with no image - ' . $total_availability . PHP_EOL;
file_put_contents(LOG_DIR . '/remove-property-noimage-noparsing.log', ' Total availability - ' . $total_availability, FILE_APPEND);
$pages = intdiv($total_availability, 1000);
$deleted_availability_counter = 0;
for ($j = 0; $j <= $pages; $j++) {
    $counter = $j * 1000 - $deleted_availability_counter;
    $query = $parsing_db->pdo->prepare("SELECT id, post_id, property_id FROM `availability` WHERE image_urls = '' LIMIT $counter,1000");
    $all_availability = $query->fetchAll();

    foreach ($all_availability as $availability) {
        $query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE property_id = ? LIMIT 1");
        $query->execute([$availability->property_id]);
        $total_properties = $query->fetchColumn();
        if (isset($availability->post_id) && $availability->post_id != '') {
            echo ' Post ID - ' . $availability->post_id . PHP_EOL;
            wp_delete_post($availability->post_id, true);
        }
        if ($total_properties > 1) {
            echo 'Property id - ' . $availability->property_id . ' | Total properties - ' . $total_properties . PHP_EOL;
            file_put_contents(LOG_DIR . '/remove-property-noimage-noparsing.log', 'Property id - ' . $availability->property_id . ' | Multi units - ' . $total_properties . '!!!!!!' . ' | Post id - ' . $availability->post_id . PHP_EOL, FILE_APPEND);
            $query = $parsing_db->pdo->prepare("DELETE FROM `availability` WHERE property_id = ?");
            $query->execute([$availability->property_id]);
        } else {
            file_put_contents(LOG_DIR . '/remove-property-noimage-noparsing.log', 'Property id - ' . $availability->property_id . ' | Single unit - ' . $total_properties . ' | Post id - ' . $availability->post_id . PHP_EOL, FILE_APPEND);
            $query = $parsing_db->pdo->prepare("DELETE FROM `availability` WHERE property_id = ?");
            $query->execute([$availability->property_id]);
            $query = $parsing_db->pdo->prepare("DELETE FROM `properties` WHERE id = ?");
            $query->execute([$availability->property_id]);
        }
    }
}

$listing_type = '25769';
$wp_db = new MySQL('wp', 'local');
$total_posts = $wp_db->countPostsRZListing($listing_type);
echo 'Total posts - ' . $total_posts . PHP_EOL;
$pages = intdiv($total_posts, 100);
echo 'Total pages - ' . $pages . PHP_EOL;
$post_deleted_counter = 0;
for ($i = 0; $i <= $pages; $i++) {
    $posts = $wp_db->getPostsRZListing($listing_type, $i * 100 - $post_deleted_counter, 100);
    $post_deleted_counter = 0;
    echo 'Page number - ' . $i . PHP_EOL;
    foreach ($posts as $post) {
        file_put_contents(LOG_DIR . '/remove-property-noimage-noparsing.log', ' [' . $post->id . ']  | ', FILE_APPEND);
        $query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE `post_id` = ? LIMIT 1");
        $query->execute([$post->id]);
        $availability = $query->fetchColumn();
        if ($availability === NULL || $availability === 0) {
            $query = $parsing_db->pdo->prepare("SELECT count(*) FROM `properties` WHERE `post_id` = ? LIMIT 1");
            $query->execute([$post->id]);
            $property = $query->fetchColumn();
            if ($property === NULL || $property === 0) {
                file_put_contents(LOG_DIR . '/remove-property-noimage-noparsing.log', ' NO AVAILABILITY >>> [' . $post->id . ']  <<< NO AVAILABILITY ', FILE_APPEND);
                echo " No availability for post id: \033[34m" . $post->id . "\033[0m" . ' | '  . PHP_EOL;
                $post_data = [
                    'ID' => $post->id,
                    'post_status' => 'draft'
                ];
                // wp_update_post($post_data);
                wp_delete_post($post->id, true);
                $post_deleted_counter += 1;
            } else {
                echo " Post id: " . $post->id . " EXIST" . PHP_EOL;
            }
        } else {
            echo " Post id: " . $post->id . " EXIST" . PHP_EOL;
        }
    }
}
echo 'Total posts - ' . count($posts) . PHP_EOL;
echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/remove-property-noimage-noparsing.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
