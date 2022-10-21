<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once '../../wp-load.php';
// Clear log files
$f = fopen(LOG_DIR . '/fix-post-regions.log', 'w');
fclose($f);
// Start transfer
echo date("Y-m-d H:i:s") . " Start fixing duplicates WP ";
file_put_contents(LOG_DIR . '/fix-regions.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

// $parsing_db = new MySQL('parsing', 'local');

$terms = get_terms([
    'taxonomy' => 'rz_regions',
    'hide_empty' => false,
]);

$rz_regions = [];
foreach ($terms as $term) {
    $rz_regions[] = [
        'term_id' => $term->term_id,
        'name' => $term->name
    ];
}
var_dump($rz_regions);

$wp_db = new MySQL('wp', 'local');
$query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_posts` WHERE `post_type` = ? LIMIT 1");
$query->execute(['rz_listing']);
$total_rz_listing = $query->fetchColumn();
$pages = intdiv($total_rz_listing, 1000);
for ($i = 0; $i <= $pages; $i++) {
    $posts = $wp_db->getAllPostsRZListing($i * 1000, 1000);
    echo 'Total posts - ' . count($posts) . PHP_EOL;
    foreach ($posts as $post) {
        file_put_contents(LOG_DIR . '/fix-post-regions.log', ' [' . $post->id . ']  | ', FILE_APPEND);
        // var_dump($post);  
        $city_meta = $wp_db->getAllMetaByPostByMetakey($post->id, 'rz_city');
        if (is_array($city_meta)) {
            $city_meta = $city_meta[0];
        } else {
            $city_meta = $city_meta;
        }

        $state_meta = $wp_db->getAllMetaByPostByMetakey($post->id, 'rz_state');
        if (is_array($state_meta)) {
            $state_meta = $state_meta[0];
        } else {
            $state_meta = $state_meta;
        }
        $city_new = str_replace(' ', '-', strtoupper($city_meta));
        $code_new = str_replace(' ', '-', strtoupper($state_meta));
        $city_name = $city_new . ', ' . $code_new;
        $key = array_search($city_name, array_column($rz_regions, 'name'));
        if ($key !== false) {
            echo $post->id;
            echo ' | ' . $city_name;
            echo ' | ' . $rz_regions[$key]['name'] . PHP_EOL;
            wp_set_post_terms($post->id, [$rz_regions[$key]['term_id']], 'rz_regions');
        }
    }
}
echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-regions.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
