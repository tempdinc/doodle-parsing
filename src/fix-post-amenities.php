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
var_dump(memory_get_usage());

// Clear log files
$f = fopen(LOG_DIR . '/fix-amenities.log', 'w');
fclose($f);
$f = fopen(LOG_DIR . '/fix-post-regions.log', 'w');
fclose($f);

echo date("Y-m-d H:i:s") . " Start - ";
//Query our MySQL table
$parsing_db = new MySQL('parsing', 'local');
var_dump(memory_get_usage());
// Apartment amenities
$apartment_amenities = [];
$total_rows = $parsing_db->count('properties', [['field' => "on_premise_features", 'compare' => "!=", 'value' => "''"]]);
$pages = intdiv($total_rows, 100);
echo 'Apartment amenities total pages - ' . $pages . PHP_EOL;
for ($i = 0; $i <= $pages; $i++) {
    $start = $i * 100;
    try {
        $query = $parsing_db->pdo->prepare("SELECT `on_premise_features` FROM `properties` WHERE `on_premise_features` != '' LIMIT $start,100");
        $query->execute();
        $rows = $query->fetchAll();
    } catch (\Exception $ex) {
        die($ex->getMessage());
    }
    foreach ($rows as $row) {
        $on_premise_features = $row->on_premise_features;
        $decoded_premise_services = json_decode($on_premise_features);
        foreach ($decoded_premise_services as $key => $value) {
            foreach ($value as $data) {
                $search_key = array_search($data, $apartment_amenities);
                if ($search_key === false) {
                    array_push($apartment_amenities, $data);
                }
            }
        }
    }
}
$apartment_amenities = array_unique($apartment_amenities, SORT_STRING);
sort($apartment_amenities);
// Community amenities
$community_amenities = [];
$total_rows = $parsing_db->count('properties', [['field' => "on_premise_services", 'compare' => "!=", 'value' => "''"]]);
$pages = intdiv($total_rows, 100);
for ($i = 0; $i <= $pages; $i++) {
    $start = $i * 100;
    try {
        $query = $parsing_db->pdo->prepare("SELECT `on_premise_services` FROM `properties` WHERE `on_premise_services` != '' LIMIT $start,100");
        $query->execute();
        $rows = $query->fetchAll();
    } catch (\Exception $ex) {
        die($ex->getMessage());
    }
    foreach ($rows as $row) {
        $on_premise_services = $row->on_premise_services;
        $decoded_premise_services = json_decode($on_premise_services);
        // var_dump($row);
        foreach ($decoded_premise_services as $key => $value) {
            foreach ($value as $data) {
                $search_key = array_search($data, $community_amenities);
                if ($search_key === false) {
                    array_push($community_amenities, $data);
                }
            }
        }
        unset($decoded_premise_services);
    }
}
$community_amenities = array_unique($community_amenities, SORT_STRING);
$community_amenities = array_diff($community_amenities, $apartment_amenities);
sort($community_amenities);
var_dump(memory_get_usage());
require_once(realpath('../../wp-load.php'));

$amenities_counter = 0;
//Query our MySQL table
$wp_db = new MySQL('wp', 'local');
/*
foreach ($apartment_amenities as $amenity) {
    $insert_res = wp_insert_term($amenity, 'rz_amenities', array(
        'parent'      => 0,
    ));
    if (is_wp_error($insert_res)) {
        $response = $insert_res->get_error_message();
    } else {
        $response = $insert_res['term_id'];
        $amenities_counter++;
    }
}
echo " \033[31mAdded amenities: " . $amenities_counter . "\033[0m" . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-amenities.log', ' Added amenities: ' . $amenities_counter, FILE_APPEND);
// Transfer amenities END
*/
// Getting all amenities
$apartment_amenities_list = [];
$apartment_amenities_rows = $wp_db->listRzAmenities();
foreach ($apartment_amenities_rows as $apartment_amenity_row) {
    $apartment_amenities_list[$apartment_amenity_row->term_id] = $apartment_amenity_row->name;
}

// Start fixing amenities
echo 'Start fixing amenities WP..' . PHP_EOL;
// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
$total_properties = $parsing_db->countRecordsWithPosts();
echo 'Total properties - ' . $total_properties . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-amenities.log', ' | Total properties - ' . $total_properties . PHP_EOL, FILE_APPEND);
$pages = intdiv($total_properties, 100);
for ($i = 0; $i <= $pages; $i++) {
    $new_properties = $parsing_db->getRecordsWithPosts(0, 100);
    foreach ($new_properties as $property) {
        // Check availability of current propery
        $all_availability = $parsing_db->getAllAvailabilityWithPostByProperty($property->id);
        $availability_counter = count($all_availability);

        // Apartment amenities
        $decoded_premise_services = json_decode($property->on_premise_features);

        if (!empty($decoded_premise_services)) {
            foreach ($decoded_premise_services as $key => $value) {
                foreach ($value as $data) {
                    $term_id = array_search($data, $apartment_amenities_list);
                    if ($term_id) {
                        add_post_meta($property->post_id, 'rz_amenities', $term_id, false);
                    }
                }
            }
        }

        if ($availability_counter > 0) {
            foreach ($all_availability as $availability) {
                if (!empty($decoded_premise_services)) {
                    foreach ($decoded_premise_services as $key => $value) {
                        foreach ($value as $data) {
                            $term_id = array_search($data, $apartment_amenities_list);
                            if ($term_id) {
                                add_post_meta($availability->post_id, 'rz_amenities', $term_id, false);
                            }
                        }
                    }
                }
            }
        }
    }
}
echo date("Y-m-d H:i:s") . " End............................................." . PHP_EOL;
