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

$terms = get_terms([
    'taxonomy' => 'rz_regions',
    'hide_empty' => false,
]);

$rz_regions = [];
foreach ($terms as $term) {
    $rz_regions[] = [
        'term_id' => $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug
    ];
}
// var_dump($rz_regions);
// exit();

$regionsDB = file_get_contents(__DIR__ . '/regions.json');
$regionsDB = json_decode($regionsDB, true);

$rz_full_regions = [];
foreach ($regionsDB as $regionDB) {
    // var_dump($value);
    foreach ($regionDB as $region_key => $region_cities) {
        // var_dump($city_key);
        $rz_regions_key = array_search($region_key, array_column($rz_regions, 'name'));
        $term_id = $rz_regions[$rz_regions_key]['term_id'];
        $term_slug = $rz_regions[$rz_regions_key]['slug'];
        // var_dump($term_id);
        foreach ($region_cities as $region_city) {
            $region_city_up = strtoupper($region_city);
            $rz_full_regions[] = [
                'term_id' => $term_id,
                'name' => $region_city_up,
                'slug' => $term_slug
            ];
        }
    }
}
// exit();

$wp_db = new MySQL('wp', 'local');
$query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_posts` WHERE `post_type` = ? LIMIT 1");
$query->execute(['rz_listing']);
$total_rz_listing = $query->fetchColumn();
$pages = intdiv($total_rz_listing, 100);
echo 'Total posts - ' . $total_rz_listing . PHP_EOL;
for ($i = 0; $i <= $pages; $i++) {
    $posts = $wp_db->getAllPostsRZListing($i * 100, 100);
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
        $city_new = strtoupper($city_meta);
        $code_new = strtoupper($state_meta);
        $city_name = $city_new . ', ' . $code_new;
        $key = array_search($city_name, array_column($rz_full_regions, 'name'));
        if ($key !== false) {
            echo $post->id;
            echo ' | ' . $city_name;
            echo ' | ' . $rz_full_regions[$key]['name'];
            file_put_contents(LOG_DIR . '/fix-post-regions.log', ' | ' . $city_name . ' | ' . $rz_full_regions[$key]['name'] . ' | ' . $rz_full_regions[$key]['term_id'] . PHP_EOL, FILE_APPEND);
            wp_set_post_terms($post->id, [$rz_full_regions[$key]['term_id']], 'rz_regions');
            if (!update_post_meta($post->id, 'rz_listing_region', $rz_full_regions[$key]['slug'])) add_post_meta($post->id, 'rz_listing_region', $rz_full_regions[$key]['slug'], true);
        }
    }
}
echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-regions.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
