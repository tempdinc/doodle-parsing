<?php
/*
Удалить все записи Parsed Units отсутствующие в таблицах Parsing & Availability
*/
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once '../../wp-load.php';
// Clear log files
$f = fopen(LOG_DIR . '/fix-post-noparsing.log', 'w');
fclose($f);
// Start transfer
echo date("Y-m-d H:i:s") . " Start fixing WP posts with no parsing " . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-post-noparsing.log', '[' . date('Y-m-d H:i:s') . ']  Start >>>', FILE_APPEND);

$parsing_db = new MySQL('parsing', 'local');
// Getting all post ids from parsing table
$existing_posts = [];

$query = $parsing_db->pdo->prepare("SELECT count(*) FROM `properties` WHERE `post_id` IS NOT NULL AND `post_id` != '' LIMIT 1");
$query->execute();
$total_properties = $query->fetchColumn();
echo 'Total properties - ' . $total_properties . PHP_EOL;
$pages = intdiv($total_properties, 100);
for ($i = 0; $i <= $pages; $i++) {
    $start = $i * 100;
    try {
        $query = $parsing_db->pdo->prepare("SELECT `post_id` FROM `properties` WHERE `post_id` IS NOT NULL AND `post_id` != '' LIMIT $start,100");
        $query->execute();
        $rows = $query->fetchAll(PDO::FETCH_COLUMN, 0);
        $existing_posts = array_merge($existing_posts, $rows);
    } catch (\Exception $ex) {
        die($ex->getMessage());
    }
}

$query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE `post_id` IS NOT NULL AND `post_id` != '' LIMIT 1");
$query->execute();
$total_availabilities = $query->fetchColumn();
echo 'Total availabilities - ' . $total_availabilities . PHP_EOL;
$pages = intdiv($total_availabilities, 100);
for ($i = 0; $i <= $pages; $i++) {
    $start = $i * 100;
    try {
        $query = $parsing_db->pdo->prepare("SELECT `post_id` FROM `availability` WHERE `post_id` IS NOT NULL AND `post_id` != '' LIMIT $start,100");
        $query->execute();
        $rows = $query->fetchAll(PDO::FETCH_COLUMN, 0);
        $existing_posts = array_merge($existing_posts, $rows);
    } catch (\Exception $ex) {
        die($ex->getMessage());
    }
}
echo 'Existing posts unique - ' . count($existing_posts) . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-post-noparsing.log', ' existing_posts unique - ' . count($existing_posts), FILE_APPEND);

$listing_type = '25769';
$wp_db = new MySQL('wp', 'local');
$total_posts = $wp_db->countPostsRZListing($listing_type);
echo 'Total posts - ' . $total_posts . PHP_EOL;
$pages = intdiv($total_posts, 100);
echo 'Total pages - ' . $pages . PHP_EOL;
$total_removed_posts = 0;
$removed_posts = 0;
for ($i = 0; $i <= $pages; $i++) {
    $start = $i * 100 - $removed_posts;
    $posts = $wp_db->getPostsRZListing($listing_type, $start, 100);
    $removed_posts = 0;
    // echo 'Page number - ' . $i . PHP_EOL;
    foreach ($posts as $post) {
        if (!in_array($post->id, $existing_posts)) {
            $removed_posts++;
            wp_delete_post($post->id, true);
            $total_removed_posts++;
        }
    }
}
echo "Total removed post - " . $total_removed_posts . ' >>> ' . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-post-noparsing.log', ' | Total removed post - ' . $total_removed_posts . ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
