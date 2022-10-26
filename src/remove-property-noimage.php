<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once '../../wp-load.php';
// Clear log files
$f = fopen(LOG_DIR . '/remove-property-noimage.log', 'w');
fclose($f);
// Start transfer
echo date("Y-m-d H:i:s") . " Start fixing wrong cities ";
file_put_contents(LOG_DIR . '/remove-property-noimage.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

$parsing_db = new MySQL('parsing', 'local');

$query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE image_urls = '' LIMIT 1");
$query->execute();
$total_availability = $query->fetchColumn();
echo 'Total availability - ' . $total_availability . PHP_EOL;
file_put_contents(LOG_DIR . '/remove-property-noimage.log', ' Total availability - ' . $total_availability, FILE_APPEND);
$pages = intdiv($total_availability, 1000);
for ($j = 0; $j <= $pages; $j++) {
    $query = $parsing_db->pdo->prepare("SELECT id, post_id, property_id FROM `availability` WHERE image_urls = '' LIMIT ?,1000");
    $query->execute([$j * 1000]);
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
            file_put_contents(LOG_DIR . '/remove-property-noimage.log', 'Property id - ' . $availability->property_id . ' | Multi properties - ' . $total_properties . '!!!!!!' . ' | Post id - ' . $availability->post_id . PHP_EOL, FILE_APPEND);
        } else {
            file_put_contents(LOG_DIR . '/remove-property-noimage.log', 'Property id - ' . $availability->property_id . ' | Total properties - ' . $total_properties . ' | Post id - ' . $availability->post_id . PHP_EOL, FILE_APPEND);
            $query = $parsing_db->pdo->prepare("DELETE FROM `availability` WHERE property_id = ?");
            $query->execute([$availability->property_id]);
            $query = $parsing_db->pdo->prepare("DELETE FROM `properties` WHERE id = ?");
            $query->execute([$availability->property_id]);
        }
    }
}

exit();

$listing_type = '25769';
$wp_db = new MySQL('wp', 'local');
$posts = $wp_db->getPostsRZListing($listing_type);
echo 'Total posts - ' . count($posts) . PHP_EOL;
foreach ($posts as $post) {
    file_put_contents(LOG_DIR . '/remove-property-noimage.log', ' [' . $post->id . ']  | ', FILE_APPEND);
    $query = $parsing_db->pdo->prepare("SELECT * FROM `availability` WHERE `post_id` = ?");
    $query->execute([$post->id]);
    $availability = $query->fetchAll();
    if ($availability == NULL) {
        file_put_contents(LOG_DIR . '/remove-property-noimage.log', ' NO AVAILABILITY >>> [' . $post->id . ']  <<< NO AVAILABILITY ', FILE_APPEND);
        echo " No availability for post id: \033[34m" . $post->id . "\033[0m" . ' | '  . PHP_EOL;
        $post_data = [
            'ID' => $post->id,
            'post_status' => 'draft'
        ];
        wp_update_post($post_data);
    } else {
        echo " Post id: " . $post->id . " Availability id: " . $availability[0]->id . PHP_EOL;
    }
}
echo 'Total posts - ' . count($posts) . PHP_EOL;
echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/remove-property-noimage.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
