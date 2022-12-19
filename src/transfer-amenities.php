<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;
// use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/bootstrap.php';
// Clear log files
$f = fopen(LOG_DIR . '/transfer-amenities.log', 'w');
fclose($f);

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
// file_put_contents(LOG_DIR . '/transfer-amenities.log', print_r($full_apartment_amenities), FILE_APPEND);
require_once(realpath('../../wp-load.php'));
$terms = get_terms([
    'taxonomy' => 'rz_amenities',
    'hide_empty' => false,
]);
file_put_contents(LOG_DIR . '/transfer-amenities.log', print_r($terms, true));

if ($terms) {
    foreach ($terms as $term) {
        $amenity_slug = str_replace('--', '-', str_replace('--', '-', str_replace(' ', '-', preg_replace('/[^ a-z 0-9 \-\d]/ui', '', strtolower($term->name)))));
        file_put_contents(LOG_DIR . '/transfer-amenities.log', $term->name . ' | ' . $term->slug . PHP_EOL, FILE_APPEND);
        wp_update_term($term->term_id, 'rz_amenities', [
            'slug' => $amenity_slug
        ]);
    }
}
/*
wp_delete_term(361, 'rz_amenities');
wp_delete_term(299, 'rz_amenities');
wp_delete_term(358, 'rz_amenities');
wp_delete_term(315, 'rz_amenities');
wp_delete_term(345, 'rz_amenities');
wp_delete_term(360, 'rz_amenities');
wp_delete_term(319, 'rz_amenities');
wp_delete_term(305, 'rz_amenities');
wp_delete_term(340, 'rz_amenities');
wp_delete_term(325, 'rz_amenities');
wp_delete_term(338, 'rz_amenities');
wp_delete_term(326, 'rz_amenities');
wp_delete_term(352, 'rz_amenities');
*/
$terms = get_terms([
    'taxonomy' => 'rz_amenities',
    'hide_empty' => false,
]);
file_put_contents(LOG_DIR . '/transfer-amenities.log', print_r($terms, true));
// $amenity_id = insertNewTerm('UNDEFINED REGION', 'undefined');
/*
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$counter = 0;
foreach($apartment_amenities as $amenity) {
    $counter++;
    $cell = 'A'.$counter;
    $sheet->setCellValue($cell, var_export($amenity, true));
    // if($counter > 2) exit;
}
$writer = new Xlsx($spreadsheet);
$writer->save('export_apartment_amenities.xlsx');
*/
// Community
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
/*
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$counter = 0;
foreach($community_amenities as $amenity) {
    $counter++;
    $cell = 'A'.$counter;
    $sheet->setCellValue($cell, var_export($amenity, true));
    // if($counter > 2) exit;
}
$writer = new Xlsx($spreadsheet);
$writer->save('export_community_amenities.xlsx');
*/
$wp_db = new MySQL('wp', 'local');
// Getting all amenities
$apartment_amenities_list = [];
$apartment_amenities_rows = $wp_db->listRzAmenities();
foreach ($apartment_amenities_rows as $apartment_amenity_row) {
    $amenity_slug = str_replace('--', '-', str_replace('--', '-', str_replace(' ', '-', preg_replace('/[^ a-z 0-9 \-\d]/ui', '', strtolower($apartment_amenity_row->name)))));
    $apartment_amenities_list[$apartment_amenity_row->term_id] = $amenity_slug;
}
var_dump($apartment_amenities_list);
foreach ($full_apartment_amenities as $full_apartment_amenity) {
    $term_id = array_search(strtolower($full_apartment_amenity['amenity_slug']), $apartment_amenities_list);
    if ($term_id !== false) {
        // add_post_meta($property->post_id, 'rz_amenities', $term_id, false);
        $amenity_id = $term_id;
    } else {
        $amenity_id = insertNewTerm($full_apartment_amenity['amenity_name'], $full_apartment_amenity['amenity_slug']);
    }
    echo $full_apartment_amenity['amenity_slug'] . ' > ';
    echo $amenity_id . ' | ';
}

if ($add_to_wp) {

    foreach ($apartment_amenities as $amenity) {
        $query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_terms` WHERE `name` = ? LIMIT 1");
        $query->execute([$amenity]);
        $isDuplicate = $query->fetchColumn();
        if (!$isDuplicate) {
            $amenity_slug = str_replace('--', '-', str_replace('--', '-', str_replace(' ', '-', preg_replace('/[^ a-z 0-9 \-\d]/ui', '', strtolower($amenity)))));
            if (mb_substr($amenity_slug, -1) == '_') {
                $amenity_slug = mb_substr($amenity_slug, 0, -1);
            }
            $slugDuplicate = checkSlug($amenity_slug);
            while ($slugDuplicate) {
                $amenity_slug = $amenity_slug . '1';
                $slugDuplicate = checkSlug($amenity_slug);
            }
        }

        if (!$isDuplicate) {
            $query = $wp_db->pdo->prepare("INSERT INTO `wp_terms` (`name`,`slug`) VALUES (?,?)");
            $query->execute([$amenity, $amenity_slug]);

            $query = $wp_db->pdo->prepare("SELECT `term_id` FROM `wp_terms` WHERE `slug` = ? LIMIT 1");
            $query->execute([$amenity_slug]);
            $prop = $query->fetch();
            if ($prop->term_id) {
                $taxonomy = 'rz_amenities';
                $description = '';
                $query = $wp_db->pdo->prepare("INSERT INTO `wp_term_taxonomy` (`term_id`,`taxonomy`,`description`) VALUES (?,?,?)");
                $query->execute([$prop->term_id, $taxonomy, $description]);
                $prop = $query->fetch();
            }
        }
    }
}

function checkSlug($slug_string)
{
    echo $slug_string . PHP_EOL;
    $wp_db = new MySQL('wp', 'local');
    $query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_terms` WHERE `slug` = ? LIMIT 1");
    $query->execute([$slug_string]);
    return $query->fetchColumn();
}
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
