<?php
/*
Check all amenities
*******************************************
1. Getting all rz_amenities taxonomy terms
2. Fixing rz_amenities taxonomy terms slugs (creating & updating rz_amenities slug from rz_amenities name)
3. Getting all on_premise_features (unit amenities) FROM properties table
4. Creating amenities assoc array with slugs
5. Start fixing amenities of posts with listing type 25769
6. Start fixing amenities of posts with listing type 380
*/
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
// Clear log files
$f = fopen(LOG_DIR . '/fix-post-amenities.log', 'w');
fclose($f);
file_put_contents(LOG_DIR . '/fix-post-amenities.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ' . PHP_EOL, FILE_APPEND);
$add_to_wp = false;


//Query our MySQL table
$parsing_db = new MySQL('parsing', 'local');


// ToDo - check for exist unit amenity taxonomy
// 1. Getting all rz_amenities taxonomy terms
file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 1. Getting all rz_amenities taxonomy terms' . PHP_EOL, FILE_APPEND);
require_once(realpath('../../wp-load.php'));
$terms = get_terms([
    'taxonomy' => 'rz_amenities',
    'hide_empty' => false,
]);
file_put_contents(LOG_DIR . '/fix-post-amenities.log', 'Count all rz_amenities taxonomy terms - ' . count($terms) . PHP_EOL, FILE_APPEND);


// 2. Fixing rz_amenities taxonomy terms slugs (creating & updating rz_amenities slug from rz_amenities name)
file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 2. Fixing rz_amenities taxonomy terms slugs (creating & updating rz_amenities slug from rz_amenities name)' . PHP_EOL, FILE_APPEND);
if ($terms) {
    foreach ($terms as $term) {
        $amenity_slug = str_replace('--', '-', str_replace('--', '-', str_replace(' ', '-', preg_replace('/[^ a-z 0-9 \-\d]/ui', '', strtolower($term->name)))));
        wp_update_term($term->term_id, 'rz_amenities', [
            'slug' => $amenity_slug
        ]);
    }
}


// 3. Getting all on_premise_features (unit amenities) FROM properties table
file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 3. Getting all on_premise_features (unit amenities) FROM properties table' . PHP_EOL, FILE_APPEND);
$query = $parsing_db->pdo->prepare("SELECT `on_premise_features` FROM `properties` WHERE `on_premise_features` != '' AND `on_premise_features` IS NOT NULL ORDER BY `on_premise_features` ASC");
$query->execute();
$rows = $query->fetchAll();
$unit_amenities = [];
foreach ($rows as $row) {
    $on_premise_features = $row->on_premise_features;
    $decoded_premise_services = json_decode($on_premise_features, true);
    $premise_services = [];
    foreach ($decoded_premise_services as $key => $value) {
        $premise_services = array_merge($premise_services, $value);
    }
    $unit_amenities = array_merge($unit_amenities, $premise_services);
    $unit_amenities = array_unique($unit_amenities, SORT_STRING);
}
$unit_amenities = array_unique($unit_amenities, SORT_STRING);
sort($unit_amenities);
file_put_contents(LOG_DIR . '/fix-post-amenities.log', 'Total unit amenities - ' . count($unit_amenities) . PHP_EOL, FILE_APPEND);


// 4. Creating amenities assoc array with slugs
file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 4. Creating amenities assoc array with slugs & term ids' . PHP_EOL, FILE_APPEND);
$terms = get_terms([
    'taxonomy' => 'rz_amenities',
    'hide_empty' => false,
]);

$full_unit_amenities = [];
$units_checked = 0;
$units_added = 0;
foreach ($unit_amenities as $unit_amenity) {
    $amenity_slug = str_replace('--', '-', str_replace('--', '-', str_replace(' ', '-', preg_replace('/[^ a-z 0-9 \-\d]/ui', '', strtolower($unit_amenity)))));
    $term_index = array_search($amenity_slug, array_column($terms, 'slug'));
    if ($term_index !== false) {
        $units_checked++;
        $full_unit_amenities[] = [
            'amenity_name'  => $unit_amenity,
            'amenity_slug'  => $amenity_slug,
            'term_id'       => $terms[$term_index]->term_id
        ];
    } else {
        $term_index = insertNewTerm($unit_amenity, $amenity_slug);
        if ($term_index !== false) {
            $units_added++;
            $full_unit_amenities[] = [
                'amenity_name'  => $unit_amenity,
                'amenity_slug'  => $amenity_slug,
                'term_id'       => $terms[$term_index]->term_id
            ];
        }
    }
}
file_put_contents(LOG_DIR . '/fix-post-amenities.log', 'Unit amenities checked - ' . $units_checked . ' | Unit amenities added - ' . $units_added . PHP_EOL, FILE_APPEND);


// ToDo - create community amenities taxonomy
// Community
/*
$query = $parsing_db->pdo->prepare("SELECT `on_premise_services` FROM `properties` WHERE `on_premise_services` != ''");
$query->execute();
$rows = $query->fetchAll();
$community_amenities = [];
foreach ($rows as $row) {
    $on_premise_services = $row->on_premise_services;
    $decoded_premise_services = json_decode($on_premise_services);
    // var_dump($row);
    foreach ($decoded_premise_services as $key => $value) {
        foreach ($value as $data) {
            array_push($community_amenities, $data);
        }
    }
}
$community_amenities = array_unique($community_amenities, SORT_STRING);
$community_amenities = array_diff($community_amenities, $unit_amenities);
sort($community_amenities);
*/


// 5. Start fixing amenities of posts with listing type 25769
file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 5. Start fixing amenities of posts with listing type 25769' . PHP_EOL, FILE_APPEND);
$listing_type = '25769';
$wp_db = new MySQL('wp', 'local');
$total_posts = $wp_db->countPostsRZListing($listing_type);
file_put_contents(LOG_DIR . '/fix-post-amenities.log', 'Total post ids with listing type ' . $listing_type . ': ' . $total_posts . PHP_EOL, FILE_APPEND);
$limit = 1000;
$pages = intdiv($total_posts, $limit);
for ($i = 0; $i <= $pages; $i++) {
    file_put_contents(LOG_DIR . '/fix-post-amenities.log', 'Page# ' . $i . ' | ', FILE_APPEND);
    $start = $i * $limit;
    $sql_query = "SELECT wp.id FROM `wp_posts` wp LEFT JOIN `wp_postmeta` wppm ON wp.id = wppm.post_id WHERE wp.post_type = 'rz_listing' AND wppm.meta_key = 'rz_listing_type' AND wppm.meta_value = ? ORDER BY wp.id ASC LIMIT ?,?";
    $query = $wp_db->pdo->prepare($sql_query);
    $query->execute([$listing_type, $start, $limit]);
    $rows = $query->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach ($rows as $row) {
        // file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . 'Post id - ' . $row, FILE_APPEND);
        $post_amenities = getPostAmenities($row);
        $post_amenities_terms = [];
        foreach ($post_amenities as $post_amenity) {
            $term_index = array_search($post_amenity, array_column($full_unit_amenities, 'amenity_name'));
            $post_amenities_terms[] = $full_unit_amenities[$term_index]['term_id'];
        }
        // file_put_contents(LOG_DIR . '/fix-post-amenities.log', json_encode($post_amenities_terms) . PHP_EOL, FILE_APPEND);
        // Removing meta fields
        delete_post_meta($row, 'rz_city');
        delete_post_meta($row, 'rz_location');
        delete_post_meta($row, 'rz_location__address');
        delete_post_meta($row, 'rz_priority');
        delete_post_meta($row, 'rz_priority_custom');
        delete_post_meta($row, 'rz_priority_selection');
        delete_post_meta($row, 'rz_reservation_length_max');
        delete_post_meta($row, 'rz_reservation_length_min');
        delete_post_meta($row, 'rz_state');
        delete_post_meta($row, 'rz_street_line_1');
        delete_post_meta($row, 'rz_street_line_2');
        delete_post_meta($row, 'rz_zip');
        delete_post_meta($row, 'rz_bed');
        if (!empty($post_amenities)) {
            delete_post_meta($row, 'rz_amenities');
            delete_post_meta($row, 'rz_amenities_list');
            add_post_meta($row, 'rz_amenities_list', json_encode($post_amenities_terms), true);
            wp_set_post_terms($row, $post_amenities_terms, 'rz_amenities');
        } else {
            file_put_contents(LOG_DIR . '/fix-post-amenities.log', 'No amenities for Post ID - ' . $row . PHP_EOL, FILE_APPEND);
        }
    }
}
file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . 'Posts to remove from wp_posts table - ' . count($posts_to_remove) . PHP_EOL, FILE_APPEND);


// 6. Start fixing amenities of posts with listing type 380
file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 5. Start fixing amenities of posts with listing type 25769' . PHP_EOL, FILE_APPEND);
$listing_type = '380';
$wp_db = new MySQL('wp', 'local');
$total_posts = $wp_db->countPostsRZListing($listing_type);
file_put_contents(LOG_DIR . '/fix-post-amenities.log', 'Total post ids with listing type ' . $listing_type . ': ' . $total_posts . PHP_EOL, FILE_APPEND);
$limit = 1000;
$pages = intdiv($total_posts, $limit);
for ($i = 0; $i <= $pages; $i++) {
    file_put_contents(LOG_DIR . '/fix-post-amenities.log', 'Page# ' . $i . ' | ', FILE_APPEND);
    $start = $i * $limit;
    $sql_query = "SELECT wp.id FROM `wp_posts` wp LEFT JOIN `wp_postmeta` wppm ON wp.id = wppm.post_id WHERE wp.post_type = 'rz_listing' AND wppm.meta_key = 'rz_listing_type' AND wppm.meta_value = ? ORDER BY wp.id ASC LIMIT ?,?";
    $query = $wp_db->pdo->prepare($sql_query);
    $query->execute([$listing_type, $start, $limit]);
    $rows = $query->fetchAll(PDO::FETCH_COLUMN, 0);
    foreach ($rows as $row) {
        file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . 'Post id - ' . $row, FILE_APPEND);
        $post_amenities_terms = $wp_db->getAllMetaByPostByMetakey($post->id, 'rz_amenities');
        file_put_contents(LOG_DIR . '/fix-post-amenities.log', json_encode($post_amenities_terms) . PHP_EOL, FILE_APPEND);
        // Removing meta fields
        // delete_post_meta($row, 'rz_city');
        delete_post_meta($row, 'rz_location');
        delete_post_meta($row, 'rz_location__address');
        delete_post_meta($row, 'rz_priority');
        delete_post_meta($row, 'rz_priority_custom');
        delete_post_meta($row, 'rz_priority_selection');
        // delete_post_meta($row, 'rz_reservation_length_max');
        // delete_post_meta($row, 'rz_reservation_length_min');
        // delete_post_meta($row, 'rz_state');
        delete_post_meta($row, 'rz_street_line_1');
        delete_post_meta($row, 'rz_street_line_2');
        // delete_post_meta($row, 'rz_zip');
        delete_post_meta($row, 'rz_bed');
        if (!empty($post_amenities_terms)) {
            delete_post_meta($row, 'rz_amenities');
            delete_post_meta($row, 'rz_amenities_list');
            add_post_meta($row, 'rz_amenities_list', json_encode($post_amenities_terms), true);
            wp_set_post_terms($row, $post_amenities_terms, 'rz_amenities');
        } else {
            file_put_contents(LOG_DIR . '/fix-post-amenities.log', 'No amenities for Post ID - ' . $row . PHP_EOL, FILE_APPEND);
        }
    }
}
file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . 'Posts to remove from wp_posts table - ' . count($posts_to_remove) . PHP_EOL, FILE_APPEND);


echo date("Y-m-d H:i:s") . " End............................................." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . ']  END >>>> ' . PHP_EOL, FILE_APPEND);


function insertNewTerm($amenity_name, $amenity_slug)
{
    $insert_res = wp_insert_term(
        $amenity_name,  // новый термин
        'rz_amenities', // таксономия
        array(
            'description' => '',
            'slug'        => $amenity_slug,
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

function getPostAmenities($post_id)
{
    $parsing_db = new MySQL('parsing', 'local');
    $query = $parsing_db->pdo->prepare(
        "SELECT count(*) FROM `properties` WHERE post_id = ? LIMIT 1"
    );
    $query->execute([$post_id]);
    $count_result = $query->fetchColumn();
    if ($count_result > 0) {
        $query = $parsing_db->pdo->prepare("SELECT `id` FROM `properties` WHERE `post_id` = ?");
        $query->execute([$post_id]);
        $property_ids = $query->fetchAll(PDO::FETCH_COLUMN, 0);
        $property_id = $property_ids[0];
        $post_amenities = getAmenitiesFromProperties($property_id);
        // Todo what to do if more than one record with same post_id } elseif ($count_result > 1) {
    } else {
        $query = $parsing_db->pdo->prepare("SELECT `property_id` FROM `availability` WHERE `post_id` = ? ORDER BY `post_id` ASC");
        $query->execute([$post_id]);
        $property_ids = $query->fetchAll(PDO::FETCH_COLUMN, 0);
        $property_id = $property_ids[0];
        $post_amenities = getAmenitiesFromProperties($property_id);
    }

    return $post_amenities;
}

function getAmenitiesFromProperties($property_id)
{
    $unit_amenities = [];
    $parsing_db = new MySQL('parsing', 'local');
    $query = $parsing_db->pdo->prepare("SELECT `on_premise_features` FROM `properties` WHERE `id` = ?");
    $query->execute([$property_id]);
    $on_premise_features = $query->fetchAll(PDO::FETCH_COLUMN, 0);
    $decoded_premise_services = json_decode($on_premise_features[0], true);
    $premise_services = [];
    foreach ($decoded_premise_services as $key => $value) {
        $premise_services = array_merge($premise_services, $value);
    }
    $unit_amenities = array_merge($unit_amenities, $premise_services);
    $unit_amenities = array_unique($unit_amenities, SORT_STRING);

    return $unit_amenities;
}
