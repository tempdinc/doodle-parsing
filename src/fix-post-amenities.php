<?php
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

// Apartment
$query = $parsing_db->pdo->prepare("SELECT `on_premise_features` FROM `properties` WHERE `on_premise_features` != ''");
$query->execute();
$rows = $query->fetchAll();
$apartment_amenities = [];
foreach ($rows as $row) {
    $on_premise_features = $row->on_premise_features;
    $decoded_premise_services = json_decode($on_premise_features);
    // var_dump($row);
    foreach ($decoded_premise_services as $key => $value) {
        foreach ($value as $data) {
            array_push($apartment_amenities, $data);
        }
    }
}
$apartment_amenities = array_unique($apartment_amenities, SORT_STRING);
sort($apartment_amenities);
$full_apartment_amenities = [];
foreach ($apartment_amenities as $apartment_amenity) {
    $amenity_slug = str_replace('--', '-', str_replace('--', '-', str_replace(' ', '-', preg_replace('/[^ a-z 0-9 \-\d]/ui', '', strtolower($apartment_amenity)))));
    $full_apartment_amenities[] = [
        'amenity_name' => $apartment_amenity,
        'amenity_slug' => $amenity_slug
    ];
}
require_once(realpath('../../wp-load.php'));
$terms = get_terms([
    'taxonomy' => 'rz_amenities',
    'hide_empty' => false,
]);

if ($terms) {
    foreach ($terms as $term) {
        $amenity_slug = str_replace('--', '-', str_replace('--', '-', str_replace(' ', '-', preg_replace('/[^ a-z 0-9 \-\d]/ui', '', strtolower($term->name)))));
        file_put_contents(LOG_DIR . '/fix-post-amenities.log', ' | ' . $term->name . ' > ' . $term->slug, FILE_APPEND);
        wp_update_term($term->term_id, 'rz_amenities', [
            'slug' => $amenity_slug
        ]);
    }
}

$terms = get_terms([
    'taxonomy' => 'rz_amenities',
    'hide_empty' => false,
]);

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
$community_amenities = array_diff($community_amenities, $apartment_amenities);
sort($community_amenities);
*/

$wp_db = new MySQL('wp', 'local');
// Getting all amenities
$apartment_amenities_list = [];
$apartment_amenities_rows = $wp_db->listRzAmenities();
foreach ($apartment_amenities_rows as $apartment_amenity_row) {
    $amenity_slug = str_replace('--', '-', str_replace('--', '-', str_replace(' ', '-', preg_replace('/[^ a-z 0-9 \-\d]/ui', '', strtolower($apartment_amenity_row->name)))));
    $apartment_amenities_list[$apartment_amenity_row->term_id] = $amenity_slug;
}

foreach ($full_apartment_amenities as $full_apartment_amenity) {
    $term_id = array_search(strtolower($full_apartment_amenity['amenity_slug']), $apartment_amenities_list);
    if ($term_id !== false) {
        $amenity_id = $term_id;
    } else {
        $amenity_id = insertNewTerm($full_apartment_amenity['amenity_name'], $full_apartment_amenity['amenity_slug']);
    }
    echo $full_apartment_amenity['amenity_slug'] . ' > ';
    echo $amenity_id . ' | ';
}

// Start fixing amenities
echo 'Start fixing amenities WP..' . PHP_EOL;
// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
$total_properties = $parsing_db->countRecordsWithPosts();
// echo 'Total properties - ' . $total_properties . PHP_EOL;
$pages = intdiv($total_properties, 100);
file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . PHP_EOL . 'Total properties - ' . $total_properties . ' | Total pages - ' . $pages . PHP_EOL, FILE_APPEND);
for ($i = 0; $i <= $pages; $i++) {
    $start = 100 * $i;
    file_put_contents(LOG_DIR . '/fix-post-amenities.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . ']  Page # ' . $i . ' | Start - ' . $start . PHP_EOL, FILE_APPEND);
    $new_properties = $parsing_db->getRecordsWithPosts($start, 100);
    foreach ($new_properties as $property) {
        // Check availability of current propery
        $all_availability = $parsing_db->getAllAvailabilityWithPostByProperty($property->id);
        $availability_counter = count($all_availability);

        // Apartment amenities
        $decoded_premise_services = json_decode($property->on_premise_features);

        if (!empty($decoded_premise_services)) {
            $all_terms = [];
            foreach ($decoded_premise_services as $key => $value) {
                foreach ($value as $data) {
                    $amenity_slug = str_replace('--', '-', str_replace('--', '-', str_replace(' ', '-', preg_replace('/[^ a-z 0-9 \-\d]/ui', '', strtolower($data)))));
                    $term_id = array_search($amenity_slug, $apartment_amenities_list);
                    if ($term_id !== false) {
                        $term_id = intval($term_id);
                        $all_terms[] = $term_id;
                    }
                }
            }
            wp_set_post_terms($property->post_id, $all_terms, 'rz_amenities');
            // file_put_contents(LOG_DIR . '/fix-post-amenities.log', ' | Property Post ID - ' . $property->post_id, FILE_APPEND);
        }

        if ($availability_counter > 1) {
            foreach ($all_availability as $availability) {
                if (!empty($decoded_premise_services)) {
                    wp_set_post_terms($availability->post_id, $all_terms, 'rz_amenities');
                    // file_put_contents(LOG_DIR . '/fix-post-amenities.log', ' | A Post ID - ' . $availability->post_id, FILE_APPEND);
                }
            }
        }
    }
    file_put_contents(LOG_DIR . '/fix-post-amenities.log', ' | Property Post ID - ' . $property->post_id, FILE_APPEND);
}
echo date("Y-m-d H:i:s") . " End............................................." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-post-amenities.log', '[' . date('Y-m-d H:i:s') . ']  END >>>> ' . PHP_EOL, FILE_APPEND);
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
