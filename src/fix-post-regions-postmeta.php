<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));

// Clear log files
$f = fopen(LOG_DIR . '/fix-post-regions.log', 'w');
fclose($f);

echo date("Y-m-d H:i:s") . " Start fix regions - ";
file_put_contents(LOG_DIR . '/fix-post-regions.log', '[' . date('Y-m-d H:i:s') . '] Transfer regions | ', FILE_APPEND);

// NEW REGIONS START
$terms = get_terms([
    'taxonomy'  => 'rz_regions',
    'hide_empty' => false
]);

$rz_regions = [];
foreach ($terms as $term) {
    $rz_regions[] = [
        'term_id'   => $term->term_id,
        'name'      => $term->name,
        'slug'      => $term->slug
    ];
}

$rz_full_cities = file_get_contents(__DIR__ . '/new-cities.json');
$rz_full_cities = json_decode($rz_full_cities, true);
$rz_full_regions = [];
foreach ($rz_full_cities as $rz_full_city) {
    $rz_regions_key = array_search($rz_full_city['region_slug'], array_column($rz_regions, 'slug'));
    if ($rz_regions_key !== false) {
        $term_id = $rz_regions[$rz_regions_key]['term_id'];
        // $term_name = $rz_regions[$rz_regions_key]['name'];
        // $term_slug = $rz_regions[$rz_regions_key]['slug'];
        $rz_full_regions[] = [
            'term_id'       => $term_id,
            'city_name'     => $rz_full_city['city_name'],
            'city_slug'     => $rz_full_city['city_slug'],
            'region_name'   => $rz_full_city['region_name'],
            'region_slug'   => $rz_full_city['region_slug']
        ];
    }
}
unset($rz_regions);
unset($rz_full_cities);
// NEW REGIONS END

$wp_db = new MySQL('wp', 'local');
$query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_posts` WHERE `post_type` = ? LIMIT 1");
$query->execute(['rz_listing']);
$total_rz_listing = $query->fetchColumn();
$pages = intdiv($total_rz_listing, 500);
file_put_contents(LOG_DIR . '/fix-post-regions.log', ' Total posts - ' . $total_rz_listing . PHP_EOL, FILE_APPEND);
echo 'Total posts - ' . $total_rz_listing . PHP_EOL;
for ($i = 0; $i <= $pages; $i++) {
    echo ' | page #' . $i . PHP_EOL;
    unset($posts);
    $posts = $wp_db->getAllPostsRZListing($i * 500, 500);
    foreach ($posts as $post) {
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
        $city_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($city_meta . ' ' . $state_meta)));
        unset($city_meta);
        unset($state_meta);
        $key = array_search($city_slug, array_column($rz_full_regions, 'city_slug'));
        if ($key !== false) {
            $rz_region_id = $rz_full_regions[$key]['term_id'];
        } else {
            $undefined_region = get_term_by('slug', 'undefined', 'rz_regions');
            if ($undefined_region !== false) {
                $rz_region_id = $undefined_region->term_id;
            } else {
                $rz_region_id = insertNewTerm('UNDEFINED REGION', 'undefined');
            }
        }
        $term_region = get_term_by('term_id', $rz_region_id, 'rz_regions');
        $region_slug = $term_region->slug;
        $term_region_id = (int)$rz_region_id;

        wp_set_post_terms($post->id, [$term_region_id], 'rz_regions');
        // $postmeta_response = add_post_meta($post->id,'rz_listing_region', $rz_full_regions[$key]['slug'], true);
        $postmeta_response = update_post_meta($post->id, 'rz_listing_region', $region_slug);
        if ($postmeta_response === false) {
            $postmeta_response = add_post_meta($post->id, 'rz_listing_region', $region_slug, true);
        }
        // $postmeta_response = addPostMeta($post->id, 'rz_listing_region', $rz_full_regions[$key]['slug'], true);
        if ($postmeta_response !== false) {
            file_put_contents(LOG_DIR . '/fix-post-regions.log', ' [' . $post->id . '] | ' . $city_slug . ' | ' . $region_slug . ' - OK!', FILE_APPEND);
        } else {
            file_put_contents(LOG_DIR . '/fix-post-regions.log', ' [' . $post->id . '] | ' . $city_slug . ' | ' . $region_slug . ' - NOT OK!', FILE_APPEND);
        }
        /*
        $rz_listing_type = $wp_db->getAllMetaByPostByMetakey($post->id, 'rz_listing_type');
        if ($rz_listing_type == '380' || $rz_listing_type == 380) {
            $postmeta_response = update_post_meta($post->id, 'rz_ranking', '4');
            if (!$postmeta_response) {
                $postmeta_response = add_post_meta($post->id, 'rz_ranking', '4', true);
            }
            $postmeta_response = update_post_meta($post->id, 'rz_search', '1');
            if (!$postmeta_response) {
                $postmeta_response = add_post_meta($post->id, 'rz_search', '1', true);
            }
            $postmeta_response = update_post_meta($post->id, 'rz_unit_type', 'single');
            if (!$postmeta_response) {
                $postmeta_response = add_post_meta($post->id, 'rz_unit_type', 'single', true);
            }
        }
*/
        file_put_contents(LOG_DIR . '/fix-post-regions.log',  ' | ' . round(memory_get_usage() / 1048576, 2) . '' . ' MB', FILE_APPEND);
        file_put_contents(LOG_DIR . '/fix-post-regions.log', PHP_EOL, FILE_APPEND);
    }
}
echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-post-regions.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);

function insertNewTerm($region_name, $region_slug)
{
    $insert_res = wp_insert_term(
        $region_name,  // новый термин
        'rz_regions', // таксономия
        array(
            'description' => '',
            'slug'        => $region_slug,
            'parent'      => 0
        )
    );

    if (is_wp_error($insert_res)) {
        echo $insert_res->get_error_message();
        return false;
    } else {
        return $insert_res['term_id'];
    }
}

/*
function addPostMeta($post_id, $post_meta, $value)
{
    // return update_metadata('post',$post_id,$post_meta,$value);
    // if (!update_post_meta($post_id, $post_meta, $value)) add_post_meta($post_id, $post_meta, $value, true);
    $wp_db = new MySQL('wp', 'local');
    $query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_postmeta` WHERE post_id = ? AND meta_key = ? LIMIT 1");
    $query->execute([$post_id, $post_meta]);
    $total_meta_key = $query->fetchColumn();
    if ($total_meta_key > 0) {
        $query = $wp_db->pdo->prepare("UPDATE `wp_postmeta` SET meta_value = ? WHERE post_id = ? AND meta_key = ?");
        $response = $query->execute([$value, $post_id, $post_meta]);
    } else {
        $query = $wp_db->pdo->prepare("INSERT INTO `wp_postmeta` (meta_value,post_id,meta_key) VALUES (?,?,?)");
        $response = $query->execute([$value, $post_id, $post_meta]);
    }
    if ($response !== false) {
        return true;
    } else {
        return false;
    }
}
*/