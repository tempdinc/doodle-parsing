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
echo date("Y-m-d H:i:s") . " Start fixing post no parsing WP ";
file_put_contents(LOG_DIR . '/fix-post-noparsing.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

$parsing_db = new MySQL('parsing', 'local');

$listing_type = '25769';
$wp_db = new MySQL('wp', 'local');
$total_posts = $wp_db->countPostsRZListing($listing_type);
echo 'Total posts - ' . $total_posts . PHP_EOL;
$pages = intdiv($total_posts, 100);
echo 'Total pages - ' . $pages . PHP_EOL;
$removed_posts = 0;
for ($i = 0; $i <= $pages; $i++) {
    $start = $i*100 - $removed_posts;
    $posts = $wp_db->getPostsRZListing($listing_type, $start, 100);
    $removed_posts = 0;
    echo 'Page number - ' . $i . PHP_EOL;
    foreach ($posts as $post) {
        // file_put_contents(LOG_DIR . '/fix-post-noparsing.log', ' [' . $post->id . ']  | ', FILE_APPEND);
        $query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE `post_id` = ? LIMIT 1");
        $query->execute([$post->id]);
        $availability = $query->fetchColumn();
        if ($availability === NULL || $availability === 0) {
            $query = $parsing_db->pdo->prepare("SELECT count(*) FROM `properties` WHERE `post_id` = ? LIMIT 1");
            $query->execute([$post->id]);
            $property = $query->fetchColumn();
            if ($property === NULL || $property === 0) {
                file_put_contents(LOG_DIR . '/fix-post-noparsing.log', ' NO AVAILABILITY >>> [' . $post->id . ']  <<< NO AVAILABILITY ', FILE_APPEND);
                echo " No availability for post id: \033[34m" . $post->id . "\033[0m" . ' | '  . PHP_EOL;
                $post_data = [
                    'ID' => $post->id,
                    'post_status' => 'draft'
                ];
                // wp_update_post($post_data);
                $removed_posts++;
                wp_delete_post($post->id, true);
            } else {
                // echo " Post id: " . $post->id . " EXIST" . PHP_EOL;
            }
        } else {
            // echo " Post id: " . $post->id . " EXIST" . PHP_EOL;
        }
    }
}
echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-post-noparsing.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
