<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//Fork settings
/*
pcntl_async_signals(true);
pcntl_signal(SIGTERM, 'signalHandler'); // Termination ('kill' was called)
pcntl_signal(SIGHUP, 'signalHandler'); // Terminal log-out
pcntl_signal(SIGINT, 'signalHandler'); // Interrupted (Ctrl-C is pressed)
*/

// Saving parent pid
file_put_contents('parentPid.out', getmypid());

use App\Classes\MySQL;

var_dump(memory_get_usage());
require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));
var_dump(memory_get_usage());

// Clear log files
$f = fopen(LOG_DIR . '/fix-post-amenities-to-taxonomies.log', 'w');
fclose($f);

// Start transfer
echo date("Y-m-d H:i:s") . " Start fixing post amenities to taxonomies";
file_put_contents(LOG_DIR . '/fix-post-amenities-to-taxonomies.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

$listing_type = '25769';
$wp_db = new MySQL('wp', 'local');
$total_posts = $wp_db->countPostsRZListing($listing_type);
echo 'Total posts - ' . $total_posts . PHP_EOL;
$pages = intdiv($total_posts, 100);
echo 'Total pages - ' . $pages . PHP_EOL;
$total_removed_posts = 0;
$removed_posts = 0;
for ($i = 0; $i <= $pages; $i++) {
    echo 'Page number - ' . $i . PHP_EOL;
    $start = $i * 100 - $removed_posts;
    $posts = $wp_db->getPostsRZListing($listing_type, $start, 100);
    foreach ($posts as $post) {
        $post_amenities = [];
        echo $post->id . ' | ';
        $rz_amenities = $wp_db->getAllMetaByPostByMetakey($post->id, 'rz_amenities');
        file_put_contents(LOG_DIR . '/fix-post-amenities-to-taxonomies.log', 'Post ID - ' . $post->id . ' | Amenities - ' . count($rz_amenities), FILE_APPEND);
        // Removing meta fields
        delete_post_meta($post->id, 'rz_city');
        delete_post_meta($post->id, 'rz_location');
        delete_post_meta($post->id, 'rz_location__address');
        delete_post_meta($post->id, 'rz_priority');
        delete_post_meta($post->id, 'rz_priority_custom');
        delete_post_meta($post->id, 'rz_priority_selection');
        delete_post_meta($post->id, 'rz_reservation_length_max');
        delete_post_meta($post->id, 'rz_reservation_length_min');
        delete_post_meta($post->id, 'rz_state');
        delete_post_meta($post->id, 'rz_street_line_1');
        delete_post_meta($post->id, 'rz_street_line_2');
        delete_post_meta($post->id, 'rz_zip');
        delete_post_meta($post->id, 'rz_bed');
        delete_post_meta($post->id, 'rz_amenities');
        if (!empty($rz_amenities)) {
            add_post_meta($post->id, 'rz_amenities_list', json_encode($rz_amenities), true);
            wp_set_post_terms($post->id, $rz_amenities, 'rz_amenities');
        } else {
            $rz_amenities_list = $wp_db->getAllMetaByPostByMetakey($post->id, 'rz_amenities_list');
            // var_dump(json_decode($rz_amenities_list[0]));
        }
    }
}
file_put_contents(LOG_DIR . '/fix-post-amenities-to-taxonomies.log', '[' . date('Y-m-d H:i:s') . ']  END >>> ', FILE_APPEND);
