<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));
// Clear log files
$f = fopen(LOG_DIR . '/test-prices.log', 'w');
fclose($f);
file_put_contents(LOG_DIR . '/test-prices.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ' . PHP_EOL, FILE_APPEND);

$listing_type = '25769';
$parsing_db = new MySQL('parsing', 'local');
$wp_db = new MySQL('wp', 'local');
$total_posts = $wp_db->countPostsRZListing($listing_type);
echo 'Total posts - ' . $total_posts . PHP_EOL;
$pages = intdiv($total_posts, 100);
echo 'Total pages - ' . $pages . PHP_EOL;
for ($i = 0; $i <= $pages; $i++) {
    $posts = $wp_db->getPostsRZListing($listing_type, $i * 100, 100);
    echo 'Total posts - ' . count($posts) . PHP_EOL;
    foreach ($posts as $post) {
        echo $post->id;
        $prices = get_post_meta($post->id, 'price_per_day');
        $availability = $parsing_db->getAvailabilityByPostId($post->id);
        var_dump($availability);
        file_put_contents(LOG_DIR . '/test-prices.log', ' [' . $post->id . ']  | ' . $prices[0] . PHP_EOL, FILE_APPEND);
        exit();
    }
}
echo date("Y-m-d H:i:s") . " End............................................." . PHP_EOL;
file_put_contents(LOG_DIR . '/test-prices.log', '[' . date('Y-m-d H:i:s') . ']  END >>>> ' . PHP_EOL, FILE_APPEND);
