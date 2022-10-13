<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once '../../wp-load.php';
// Clear log files
$f = fopen(LOG_DIR . '/fix-duplicate-posts.log', 'w');
fclose($f);
// Start transfer
echo date("Y-m-d H:i:s") . " Start fixing duplicates WP ";
file_put_contents(LOG_DIR . '/fix-duplicate.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

$parsing_db = new MySQL('parsing', 'local');

$listing_type = '25769';
$wp_db = new MySQL('wp', 'local');
$posts = $wp_db->getPostsRZListing($listing_type);
echo 'Total posts - ' . count($posts) . PHP_EOL;
foreach ($posts as $post) {
    file_put_contents(LOG_DIR . '/fix-duplicate-posts.log', ' [' . $post->id . ']  | ', FILE_APPEND);
    $query = $parsing_db->pdo->prepare("SELECT * FROM `availability` WHERE `post_id` = ?");
    $query->execute([$post->id]);
    $availability = $query->fetchAll();
    if ($availability == NULL) {
        file_put_contents(LOG_DIR . '/fix-duplicate-posts.log', ' NO AVAILABILITY >>> [' . $post->id . ']  <<< NO AVAILABILITY ', FILE_APPEND);
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
file_put_contents(LOG_DIR . '/fix-duplicate.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
