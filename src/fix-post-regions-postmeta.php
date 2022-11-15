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

echo date("Y-m-d H:i:s") . " Transfer regions - ";
file_put_contents(LOG_DIR . '/fix-post-regions.log', '[' . date('Y-m-d H:i:s') . '] Transfer regions - ', FILE_APPEND);

$citiesDB = file_get_contents(__DIR__ . '/cities.json');
$citiesDB = json_decode($citiesDB, true);
$regions = [];

foreach ($citiesDB as $states) {
    foreach ($states as $code => $citiesArray) {
        foreach ($citiesArray as $city) {
            $city_new = str_replace(' ', '-', strtolower($city));
            $code_new = str_replace(' ', '-', strtolower($code));
            $city_up = strtoupper($city);
            $code_up = strtoupper($code);
            $regions[] = [
                'name' => $city_up . ', ' . $code_up,
                'slug' => $city_new . '-' . $code_new
            ];
        }
    }
}

foreach ($regions as $region) {
    $result = insertNewTerm($region['name'], $region['slug']);
    echo $result;
    if ($result) {
        file_put_contents(LOG_DIR . '/fix-post-regions.log', ' region - ' . $region['name'] . ' | ' . $region['slug'] . PHP_EOL, FILE_APPEND);
    } else {
        file_put_contents(LOG_DIR . '/fix-post-regions.log', ' region - ' . $region['name'] . ' | ' . $region['slug'] . ' - NOT ADDED!!!' . PHP_EOL, FILE_APPEND);
    }
}

file_put_contents(LOG_DIR . '/fix-post-regions.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);

// Start transfer
echo date("Y-m-d H:i:s") . " Start fixing post regions ";
file_put_contents(LOG_DIR . '/fix-post-regions.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ' . PHP_EOL, FILE_APPEND);

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
        $term_name = $rz_regions[$rz_regions_key]['name'];
        $term_slug = $rz_regions[$rz_regions_key]['slug'];
        // var_dump($term_id);
        foreach ($region_cities as $region_city) {
            $region_city_up = strtoupper($region_city);
            $rz_full_regions[] = [
                'term_id' => $term_id,
                'name' => $region_city_up,
                'slug' => $term_slug
            ];
            file_put_contents(LOG_DIR . '/fix-post-regions.log', ' >>> [' . $term_id . '] - ' . $term_name . ' | ' . $region_city_up . ' | ' . $term_slug . PHP_EOL, FILE_APPEND);
        }
    }
}
// var_dump($rz_full_regions);
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
        $city_new = trim(strtoupper($city_meta));
        $code_new = trim(strtoupper($state_meta));
        $city_name = $city_new . ', ' . $code_new;
        file_put_contents(LOG_DIR . '/fix-post-regions.log', ' [' . $post->id . '] | ' . $city_name . ' | ', FILE_APPEND);
        $key = array_search($city_name, array_column($rz_full_regions, 'name'));
        if ($key !== false) {
            echo $post->id;
            echo ' | ' . $city_name;
            echo ' | ' . $rz_full_regions[$key]['name'];
            file_put_contents(LOG_DIR . '/fix-post-regions.log', $rz_full_regions[$key]['name'] . ' | ' . $rz_full_regions[$key]['term_id'], FILE_APPEND);
            wp_set_post_terms($post->id, [$rz_full_regions[$key]['term_id']], 'rz_regions');
            // $postmeta_response = add_post_meta($post->id,'rz_listing_region', $rz_full_regions[$key]['slug'], true);
            $postmeta_response = update_post_meta($post->id, 'rz_listing_region', $rz_full_regions[$key]['slug']);
            if (!$postmeta_response) {
                $postmeta_response = add_post_meta($post->id, 'rz_listing_region', $rz_full_regions[$key]['slug'], true);
            }
            // $postmeta_response = addPostMeta($post->id, 'rz_listing_region', $rz_full_regions[$key]['slug'], true);
            if ($postmeta_response !== false) {
                echo ' - OK ';
            } else {
                echo " \033[34m - NOT OK\033[0m ";
            }
        }
        $rz_listing_type = $wp_db->getAllMetaByPostByMetakey($post->id, 'rz_listing_type');
        if($rz_listing_type == '380' || $rz_listing_type == 380) {
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
        echo PHP_EOL;
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