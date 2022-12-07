<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

//Fork settings
pcntl_async_signals(true);

pcntl_signal(SIGTERM, 'signalHandler'); // Termination ('kill' was called)
pcntl_signal(SIGHUP, 'signalHandler'); // Terminal log-out
pcntl_signal(SIGINT, 'signalHandler'); // Interrupted (Ctrl-C is pressed)

// Saving parent pid
file_put_contents('parentPid.out', getmypid());

use App\Classes\MySQL;

var_dump(memory_get_usage());
require_once __DIR__ . '/bootstrap.php';
require_once '../../wp-load.php';
var_dump(memory_get_usage());
// Clear log files
$f = fopen(LOG_DIR . '/new-transfer-data.log', 'w');
fclose($f);
$f = fopen(LOG_DIR . '/fix-post-regions.log', 'w');
fclose($f);
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
$amenities_counter = 0;
//Query our MySQL table
$wp_db = new MySQL('wp', 'local');
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
file_put_contents(LOG_DIR . '/new-transfer-data.log', ' Added amenities: ' . $amenities_counter, FILE_APPEND);
// Transfer amenities END

// Getting all amenities
$apartment_amenities_list = [];
$apartment_amenities_rows = $wp_db->listRzAmenities();
foreach ($apartment_amenities_rows as $apartment_amenity_row) {
    $apartment_amenities_list[$apartment_amenity_row->term_id] = $apartment_amenity_row->name;
}

// NEW REGIONS START
$terms = get_terms([
    'taxonomy' => 'rz_regions',
    'hide_empty' => false,
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
    $term_id = $rz_regions[$rz_regions_key]['term_id'];
    $term_name = $rz_regions[$rz_regions_key]['name'];
    $term_slug = $rz_regions[$rz_regions_key]['slug'];
    $rz_full_regions[] = [
        'term_id'       => $term_id,
        'city_name'     => $rz_full_city['city_name'],
        'city_slug'     => $rz_full_city['city_slug'],
        'region_name'   => $rz_full_city['region_name'],
        'region_slug'   => $rz_full_city['region_slug']
    ];
}
// NEW REGIONS END

// Checking for existing rz_listing_category multiunit
$rz_category_terms = get_terms([
    'taxonomy' => 'rz_listing_category',
    'hide_empty' => false,
]);

$rz_listing_category = '0';
foreach ($rz_category_terms as $rz_category_term) {
    if ($rz_category_term->slug == 'multiunit') {
        $rz_listing_category = $rz_category_term->term_id;
    }
}
if ($rz_listing_category == '0') {
    $insert_res = wp_insert_term('Apartment House', 'rz_listing_category', array(
        'description' => '',
        'parent'      => 0,
        'slug'        => 'multiunit',
    ));
    $rz_listing_category = $insert_res['term_id'];
}

// Start transfer
echo 'Start transfer properties to WP..' . PHP_EOL;
file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | rz_listing_category WP - ' . $rz_listing_category, FILE_APPEND);

// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
$total_properties = $parsing_db->countAllNewRecords();
echo 'Total properties - ' . $total_properties . PHP_EOL;
file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | Total properties - ' . $total_properties . PHP_EOL, FILE_APPEND);
$pages = intdiv($total_properties, 100);
for ($i = 0; $i <= $pages; $i++) {
    $new_properties = $parsing_db->getAllNewRecords(0, 100);
    foreach ($new_properties as $property) {
        // Check availability of current propery
        $all_availability = $parsing_db->getAvailabilityNowByProperty($property->id);
        $availability_counter = count($all_availability);

        $date = date('Y-m-d H:i:s');
        $gmdate = gmdate('Y-m-d H:i:s');

        $property_description = '';
        if ($property->building_desc !== NULL) {
            $property_description = $property->building_desc;
        } elseif ($property->property_info !== NULL) {
            $property_description = $property->property_info;
        }

        $property_address = sanitize_text_field($property->address); // Used for post title
        $np_rz_location__address = $property->city . ', ' . $property->state_cd . ', US';
        $np_rz_location__state_country = $property->state_cd . ', US';
        $np_rz_location__address_line1 = (isset($property->addr_line_1) && $property->addr_line_1 !== NULL && $property->addr_line_1 != '') ? $property->addr_line_1 . ' - ' . $property->addr_line_2 : $property->addr_line_2;
        $np_rz_location__address_line2 = $property->city . ', ' . $property->state_cd . ' ' . $property->zip5_cd;

        // region
        $region_slug = '';
        $city_low = strtolower($property->city);
        $state_low = strtolower($property->state_cd);
        $current_city_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', $city_low . ' ' . $state_low));
        $key = array_search($current_city_slug, array_column($rz_full_regions, 'city_slug'));
        if ($key !== false) {
            $rz_region_id = $rz_full_regions[$key]['term_id'];
            $region_slug = $rz_full_regions[$key]['region_slug'];
            $custom_tax = array(
                'rz_regions' => array(
                    $rz_region_id
                )
            );
        }
        // Adding ranking
        $unit_source = $property->source;
        $rz_ranking = '0';
        switch ($unit_source) {
            case 'rentprogress.com':
                $rz_ranking = '2';
                break;
            case 'apartments.com':
                $rz_ranking = '1';
                break;
        }

        $unit_description = ($property->building_desc !== NULL && $property->building_desc != '') ? clear_description($property->building_desc) : 'This home is priced to rent and won\'t be around for long. Apply now, while the current residents are preparing to move out.';

        // Apartment amenities
        $decoded_premise_services = json_decode($property->on_premise_features);

        $is_gallery_empty = true;
        if ($availability_counter > 0 && isset($all_availability[0]->listing_price)) {
            // Image gallery
            $wpImageArray = [];
            $image_urls = $property->image_urls;
            $decoded_image_urls = json_decode($image_urls);
            if (is_array($decoded_image_urls) && count($decoded_image_urls) > 0) {
                foreach ($decoded_image_urls as $key => $value) {
                    $moveToWP = moveToWp($value, $property_address, $unit_source);
                    // file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | moveToWP RESULT - ' . $moveToWP . PHP_EOL, FILE_APPEND);
                    if ($moveToWP) {
                        $wpImageId = (object)array('id' => (string)$moveToWP);
                        array_push($wpImageArray, $wpImageId);
                    } else {
                        file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | Error image transferring WP - ' . $filename_path . PHP_EOL, FILE_APPEND);
                    }
                    /*
                    exit();

                    $filename_path = __DIR__ . '/images/' . $orig_filename . '.' . $orig_fileextension;
                    $is_file_exist = file_exists($filename_path);
                    $filename_counter = 1;
                    while ($is_file_exist) {
                        $orig_filename = $orig_filename . $filename_counter;
                        $filename_path = __DIR__ . '/images/' . $orig_filename . '.' . $orig_fileextension;
                        $is_file_exist = isFileExist($filename_path);
                        $filename_counter++;
                    }
                    $file_get = file_get_contents($value);

                    if ($file_get !== false) {
                        $put_result = file_put_contents($filename_path, $file_get);
                        file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | PUT RESULT - ' . $put_result . PHP_EOL, FILE_APPEND);
                        if ($unit_source == 'rentprogress.com') {
                            cropImage($filename_path, 100, 82);
                        }

                        $moveToWP = moveToWp($filename_path, $property_address);
                        file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | moveToWP RESULT - ' . $moveToWP . PHP_EOL, FILE_APPEND);
                        if ($moveToWP) {
                            $wpImageId = (object)array('id' => (string)$moveToWP);
                            array_push($wpImageArray, $wpImageId);
                        } else {
                            file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | Error image transferring WP - ' . $filename_path . PHP_EOL, FILE_APPEND);
                        }
                    } else {
                        file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | Error image transferring WP - ' . $filename_path . PHP_EOL, FILE_APPEND);
                    }
                    */
                }
            }
            // Checking for images of post
            $is_gallery_empty = (count($wpImageArray) == 0) ? true : false;
            $rz_gallery = json_encode($wpImageArray);
            // clearImages();
        }

        if (!$is_gallery_empty && $region_slug != '' && $availability_counter > 0 && isset($all_availability[0]->listing_price)) {
            $tax_input = array(
                'rz_regions' => array($rz_region_id)
            );
            // Создаем массив данных новой записи
            $post_data = array(
                'post_title'    => $property_address,
                'post_name'     => $property_address,
                'post_content'  => $property_description,
                'post_excerpt'  => $property_description,
                'post_status'   => 'publish',
                'post_author'   => 62,
                'post_type'     => 'rz_listing',
                // 'tax_input'     => $tax_input
            );
            // Вставляем запись в базу данных
            $main_post_insert_result = wp_insert_post(wp_slash($post_data), true);
            if (is_wp_error($main_post_insert_result)) {
                echo $main_post_insert_result->get_error_message();
            }
            if ($main_post_insert_result && $main_post_insert_result != 0) {
                wp_set_post_terms($main_post_insert_result, [$rz_region_id], 'rz_regions');

                $new_property_meta = [
                    'post_content' => $unit_description,
                    'rz_apartment_uri' => $property->link,
                    'rz_booking_type' => 'Request booking',
                    'rz_city' => $property->city,
                    'rz_listing_type' => '25769',
                    'rz_location' => $property->longitude,
                    'rz_location' => $property->latitude,
                    'rz_location' => $np_rz_location__address,
                    'rz_location__address' => $np_rz_location__address,
                    'rz_location__lat' => $property->latitude,
                    'rz_location__lng' => $property->longitude,
                    'rz_post_address1' => $np_rz_location__address_line1,
                    'rz_post_address2' => $np_rz_location__address_line2,
                    'rz_priority' => '0',
                    'rz_priority_custom' => '0',
                    'rz_priority_selection' => 'normal',
                    'rz_reservation_length_max' => '0',
                    'rz_reservation_length_min' => '0',
                    'rz_state' => $property->state_cd,
                    'rz_status' => 'Now',
                    'rz_street_line_1' => $np_rz_location__address_line1,
                    'rz_street_line_2' => $np_rz_location__address_line2,
                    'rz_zip' => $property->zip5_cd,
                    'rz_gallery' => $rz_gallery,
                    'rz_ranking' => $rz_ranking,
                    'rz_listing_region' => $region_slug
                ];
                foreach ($new_property_meta as $key => $value) {
                    add_post_meta($main_post_insert_result, $key, $value, true) or update_post_meta($main_post_insert_result, $key, $value);
                }

                if (!empty($decoded_premise_services)) {
                    foreach ($decoded_premise_services as $key => $value) {
                        foreach ($value as $data) {
                            $term_id = array_search($data, $apartment_amenities_list);
                            if ($term_id) {
                                // add_post_meta($main_post_insert_result, 'rz_amenities', $term_id, true) or update_post_meta($main_post_insert_result, 'rz_amenities', $term_id);
                                add_post_meta($main_post_insert_result, 'rz_amenities', $term_id, false);
                            }
                        }
                    }
                }
            }
            if ($availability_counter > 1) {
                $availability_prices = [];
                $availability_price_per_day = [];
                $availability_beds = [];
                $availability_baths = [];
                $availability_sqft = [];
                $availability_posts = [];
                foreach ($all_availability as $availability) {
                    if ($main_post_insert_result && $main_post_insert_result != 0) {
                        $unit_post_insert_result = wp_insert_post($post_data);
                        if ($unit_post_insert_result && $unit_post_insert_result != 0) {
                            $post_id = $unit_post_insert_result;
                            wp_set_post_terms($post_id, [$rz_region_id], 'rz_regions');

                            $rz_unit_type = 'multisingle';
                            $rz_search = 0;
                            $availability_posts[] = $unit_post_insert_result;

                            // echo ' | rz_unit_type - ' . $rz_unit_type . ' | property address - ' . $property_address . ' | unit_post_insert_result - ' . $unit_post_insert_result;
                            // file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | rz_unit_type - ' . $rz_unit_type . ' | property address - ' . $property_address . ' | unit_post_insert_result - ' . $unit_post_insert_result . PHP_EOL, FILE_APPEND);

                            $new_property_meta = [];

                            // Checking for range - end(explode
                            $availability_bathroom_cnt = $availability->bathroom_cnt;
                            $exploded_bathroom_cnt = explode('–', $availability_bathroom_cnt);
                            $bathroom_count = trim(preg_replace("/[a-zA-Z]/", "", end($exploded_bathroom_cnt)));
                            $bath_count = ($bathroom_count == 0 || $bathroom_count == '') ? 0 : $bathroom_count;
                            $availability_baths[] = $bath_count; // rz_bathrooms

                            $availability_bedroom_cnt = $availability->bedroom_cnt;
                            $exploded_bedroom_cnt = explode('–', $availability_bedroom_cnt);
                            $bedroom_count = trim(preg_replace("/[a-zA-Z]/", "", end($exploded_bedroom_cnt)));
                            $bed_count = ($bedroom_count == 0 || $bedroom_count == '') ? 0 : $bedroom_count;
                            $availability_beds[] = $bed_count;

                            $availability_home_size_sq_ft = $availability->home_size_sq_ft;
                            $exploded_home_size_sq_ft = explode('–', $availability_home_size_sq_ft);
                            $home_size_sq_ft = trim(preg_replace("/\D/", "", end($exploded_home_size_sq_ft)));
                            $sqft = ($home_size_sq_ft == 0 || $home_size_sq_ft == '') ? 'n/d' : $home_size_sq_ft; // rz_sqft
                            $availability_sqft[] = $sqft;

                            $availability_listing_price = $availability->listing_price;
                            $exploded_listing_price = explode('–', $availability_listing_price);
                            $listing_price = clearPrice(end($exploded_listing_price));
                            if (intval($listing_price) != 0) $availability_prices[] = $listing_price;

                            $new_property_meta = [
                                'post_content' => $unit_description,
                                'rz_apartment_uri' => $property->link,
                                'rz_bathrooms' => $bath_count,
                                'rz_bed' => $bed_count,
                                'rz_bedroom' => $bed_count,
                                'rz_booking_type' => 'Request booking',
                                'rz_city' => $property->city,
                                'rz_listing_type' => '25769',
                                'rz_location' => $property->longitude,
                                'rz_location' => $property->latitude,
                                'rz_location' => $np_rz_location__address,
                                'rz_location__address' => $np_rz_location__address,
                                'rz_location__lat' => $property->latitude,
                                'rz_location__lng' => $property->longitude,
                                'rz_post_address1' => $np_rz_location__address_line1,
                                'rz_post_address2' => $np_rz_location__address_line2,
                                'rz_price' => $listing_price,
                                'rz_priority' => '0',
                                'rz_priority_custom' => '0',
                                'rz_priority_selection' => 'normal',
                                'rz_reservation_length_max' => '0',
                                'rz_reservation_length_min' => '0',
                                'rz_sqft' => $sqft,
                                'rz_state' => $property->state_cd,
                                'rz_status' => 'Now',
                                'rz_street_line_1' => $np_rz_location__address_line1,
                                'rz_street_line_2' => $np_rz_location__address_line2,
                                'rz_zip' => $property->zip5_cd,
                                'rz_gallery' => $rz_gallery,
                                'rz_ranking' => $rz_ranking,
                                'rz_listing_region' => $region_slug,
                                'rz_unit_type' => $rz_unit_type,
                                'rz_search' => $rz_search
                            ];
                            foreach ($new_property_meta as $key => $value) {
                                add_post_meta($post_id, $key, $value, true) or update_post_meta($post_id, $key, $value);
                            }

                            if (!empty($decoded_premise_services)) {
                                foreach ($decoded_premise_services as $key => $value) {
                                    foreach ($value as $data) {
                                        $term_id = array_search($data, $apartment_amenities_list);
                                        if ($term_id) {
                                            add_post_meta($post_id, 'rz_amenities', $term_id, true) or update_post_meta($post_id, 'rz_amenities', $term_id);
                                        }
                                    }
                                }
                            }
                            if ($listing_price != 0) {
                                $price_per_day = get_custom_price($post_id);
                                if (intval($price_per_day) != 0) $availability_prices_per_day[] = $price_per_day;
                                if (!add_post_meta($post_id, 'price_per_day', $price_per_day, true)) {
                                    update_post_meta($post_id, 'price_per_day', $price_per_day);
                                }
                            }

                            $query = $parsing_db->pdo->prepare("UPDATE `availability` SET post_id = ? WHERE id = ?");
                            $query->execute([$post_id, $availability->id]);
                        }
                    }
                }
                $rz_unit_type = 'multi';
                $rz_search = 1;

                $max_price = max($availability_prices);
                $min_price = min($availability_prices);
                $availability_price = ($max_price == $min_price) ? $max_price : $min_price . '-' . $max_price;
                $max_price_per_day = max($availability_prices_per_day);
                $min_price_per_day = min($availability_prices_per_day);
                $availability_price_per_day = ($max_price_per_day == $min_price_per_day) ? $max_price_per_day : $min_price_per_day . '-' . $max_price_per_day;

                $max_bed = max($availability_beds);
                $min_bed = min($availability_beds);
                $availability_bed = ($max_bed == $min_bed) ? $max_bed : $min_bed . '-' . $max_bed;

                $max_bath = max($availability_baths);
                $min_bath = min($availability_baths);
                $availability_bath = ($max_bath == $min_bath) ? $max_bath : $min_bath . '-' . $max_bath;

                $max_sqft = max($availability_sqft);
                $min_sqft = min($availability_sqft);
                $availability_sqft = ($max_sqft == $min_sqft) ? $max_sqft : $min_sqft . '-' . $max_sqft;

                $new_property_meta = [
                    'post_content' => $unit_description,
                    'rz_apartment_uri' => $property->link,
                    'rz_price' => $availability_price,
                    'price_per_day' => $availability_price_per_day,
                    'rz_bathrooms' => $availability_bath,
                    'rz_bed' => $availability_bed,
                    'rz_bedroom' => $availability_bed,
                    'rz_sqft' => $availability_sqft,
                    'rz_unit_type' => $rz_unit_type,
                    'rz_search' => $rz_search,
                    'rz_multi_units' => json_encode($availability_posts, JSON_PRETTY_PRINT),
                    'rz_listing_category' => $rz_listing_category
                ];
                foreach ($new_property_meta as $key => $value) {
                    add_post_meta($main_post_insert_result, $key, $value, true) or update_post_meta($main_post_insert_result, $key, $value);
                }
                // file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | rz_unit_type - ' . $rz_unit_type . ' | property address - ' . $property_address . ' | main_post_insert_result - ' . $main_post_insert_result . ' | property->id - ' . $property->id . PHP_EOL, FILE_APPEND);
                $query = $parsing_db->pdo->prepare("UPDATE `properties` SET post_id = ? WHERE id = ?");
                $query->execute([$main_post_insert_result, $property->id]);
            } else {
                $rz_unit_type = 'single';
                $rz_search = 1;
                $rz_multi_units = [];

                // file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | rz_unit_type - ' . $rz_unit_type . ' | property address - ' . $property_address . ' | main_post_insert_result - ' . $main_post_insert_result . ' | property->id - ' . $property->id . PHP_EOL, FILE_APPEND);

                if ($main_post_insert_result && $main_post_insert_result != 0) {
                    $new_property_meta = [];
                    // Checking for range - end(explode
                    $all_availability_bathroom_cnt = $all_availability[0]->bathroom_cnt;
                    $exploded_availability_bathroom_cnt = explode('–', $all_availability_bathroom_cnt);
                    $bathroom_count = trim(preg_replace("/[a-zA-Z]/", "", end($exploded_availability_bathroom_cnt)));
                    $bath_count = ($bathroom_count == 0 || $bathroom_count == '') ? 0 : $bathroom_count; // rz_bathrooms

                    $all_availability_bedroom_cnt = $all_availability[0]->bedroom_cnt;
                    $exploded_availability_bedroom_cnt = explode('–', $all_availability_bedroom_cnt);
                    $bedroom_count = trim(preg_replace("/[a-zA-Z]/", "", end($exploded_availability_bedroom_cnt)));
                    $bed_count = ($bedroom_count == 0 || $bedroom_count == '') ? 0 : $bedroom_count; // rz_bed

                    $all_availability_home_size_sq_ft = $all_availability[0]->home_size_sq_ft;
                    $exploded_availability_home_size_sq_ft = explode('–', $all_availability_home_size_sq_ft);
                    $home_size_sq_ft = trim(preg_replace("/\D/", "", end($exploded_availability_home_size_sq_ft)));
                    $sqft = ($home_size_sq_ft == 0 || $home_size_sq_ft == '') ? 'n/d' : $home_size_sq_ft; // rz_sqft

                    $all_availability_listing_price = $all_availability[0]->listing_price;
                    $exploded_availability_listing_price = explode('–', $all_availability_listing_price);
                    $listing_price = clearPrice(end($exploded_availability_listing_price));

                    $new_property_meta = [
                        'post_content' => $unit_description,
                        'rz_apartment_uri' => $property->link,
                        'rz_bathrooms' => $bath_count,
                        'rz_bed' => $bed_count,
                        'rz_bedroom' => $bed_count,
                        'rz_price' => $listing_price,
                        'rz_sqft' => $sqft,
                        'rz_unit_type' => $rz_unit_type,
                        'rz_search' => $rz_search,
                        'rz_multi_units' => $rz_multi_units
                    ];
                    foreach ($new_property_meta as $key => $value) {
                        add_post_meta($main_post_insert_result, $key, $value, true) or update_post_meta($main_post_insert_result, $key, $value);
                    }
                    if ($listing_price != 0) {
                        $price_per_day = get_custom_price($main_post_insert_result);
                        if (!add_post_meta($main_post_insert_result, 'price_per_day', $price_per_day, true)) {
                            update_post_meta($main_post_insert_result, 'price_per_day', $price_per_day);
                        }
                    }

                    $query = $parsing_db->pdo->prepare("UPDATE `availability` SET post_id = ? WHERE id = ?");
                    $query->execute([$main_post_insert_result, $all_availability[0]->id]);
                    $query = $parsing_db->pdo->prepare("UPDATE `properties` SET post_id = ? WHERE id = ?");
                    $query->execute([$main_post_insert_result, $property->id]);
                }
            }
        } else {
            deleteProperty($property->id);
        }
        // exit();
    }
}
echo 'End.............................................' . PHP_EOL;

// Stop signals handler
function signalHandler($signal)
{
    global $queue;
    unset($queue);
    exit;
}

function replace_string($string)
{
    $vowels = array(" ", ".", ",", "$", ";", ":", "%", "*", "(", ")", "+", "=", "_", "|", "#", "@", "!", "`", "~", "^", "&");
    return mb_strtolower(str_replace($vowels, '-', $string));
}

function clear_description($string)
{
    $string = str_replace('.com', '-com', $string);
    $search_strings = array('rentprogress', 'zillow', 'apartments-com', 'hotpads', 'contact', 'phone', '@', 'Progress', 'call', 'Application Coordinator', 'Managed By', 'Website', 'Email', 'email', 'Leasing Specialists', 'credit', 'click');
    $array_strings = explode('.', $string);
    foreach ($array_strings as $key => $value) {
        foreach ($search_strings as $search_string) {
            if (stripos($value, $search_string)) {
                unset($array_strings[$key]);
            }
        }
    }
    return implode('.', $array_strings);
    // $vowels = array(" ", ".", ",", "$", ";", ":", "%", "*", "(", ")", "+", "=", "_", "|", "#", "@", "!", "`", "~", "^", "&");
    // return mb_strtolower(str_replace($vowels, '-', $string));
}

function getExtension($filename)
{
    return substr(strrchr($filename, '.'), 1);
}

function isFileExist($filename)
{
    return file_exists($filename);
}

function cropImage($image, $crop_width, $crop_height)
{
    $im = imagecreatefromjpeg($image);
    $image_width = imagesx($im);
    $image_height = imagesy($im);
    $new_image_width = round($image_width * $crop_width * 0.01);
    $new_image_height = round($image_height * $crop_height * 0.01);
    $im2 = imagecrop($im, ['x' => 0, 'y' => 0, 'width' => $new_image_width, 'height' => $new_image_height]);
    if ($im2 !== FALSE) {
        imagejpeg($im2, $image);
        imagedestroy($im2);
    }
    imagedestroy($im);
}
function moveToWp($image_url, $alt_text, $unit_source)
{
    $re = '`^.*/`m';
    $subst = '';
    /* IMAGE NAME CHECKING */
    $orig_full_filename = preg_replace($re, $subst, parse_url($image_url, PHP_URL_PATH));
    $orig_fileextension = getExtension($orig_full_filename);
    $orig_filename = substr(str_replace($orig_fileextension, '', $orig_full_filename), 0, -1);

    $upload_dir = wp_upload_dir();
    $filename_path = $upload_dir['path'] . $orig_filename . '.' . $orig_fileextension;
    $is_file_exist = file_exists($filename_path);
    $filename_counter = 1;
    while ($is_file_exist) {
        $orig_filename = $orig_filename . $filename_counter;
        $filename_path = $upload_dir['path'] . $orig_filename . '.' . $orig_fileextension;
        $is_file_exist = isFileExist($filename_path);
        $filename_counter++;
    }
    try {
        $image_data = file_get_contents($image_url);
        /*
        $filename = basename($image_url);
        if (wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }
        */
        file_put_contents($filename_path, $image_data);
        if ($unit_source == 'rentprogress.com') {
            cropImage($filename_path, 100, 82);
        }
        $wp_filetype = wp_check_filetype($filename_path, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($orig_filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $filename_path);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $filename_path);
        wp_update_attachment_metadata($attach_id, $attach_data);
        update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
        return $attach_id;
    } catch (Exception $e) {
        echo ($e->getMessage());
        return false;
    }
}
function moveToWpOld($image_url, $alt_text)
{
    // $image_url = 'adress img';
    try {
        $upload_dir = wp_upload_dir();
        // $image_data = file_get_contents($image_url);
        $filename = basename($image_url);
        if (wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }
        // file_put_contents($file, $image_data);
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $file);
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $file);
        wp_update_attachment_metadata($attach_id, $attach_data);
        update_post_meta($attach_id, '_wp_attachment_image_alt', $alt_text);
        return $attach_id;
    } catch (Exception $e) {
        echo ($e->getMessage());
        return false;
    }
}

function clearImages()
{
    if (file_exists(__DIR__ . '/images/')) {
        foreach (glob(__DIR__ . '/images/*') as $file) {
            unlink($file);
        }
    }
}

function clearPrice($price)
{
    $price_array = explode('$', trim($price));
    $new_price = end($price_array);
    return preg_replace('/[^0-9]/', '', $new_price);
}

function deleteProperty($propertyId)
{
    file_put_contents(LOG_DIR . '/new-transfer-data.log', ' Delete property: ' . $propertyId, FILE_APPEND);
    $parsing_db = new MySQL('parsing', 'local');
    try {
        $query = $parsing_db->pdo->prepare("DELETE FROM `properties` WHERE id = ?");
        $query->execute([$propertyId]);
    } catch (\Exception $ex) {
        return $ex->getMessage();
    }
    try {
        $query = $parsing_db->pdo->prepare("DELETE FROM `availability` WHERE property_id = ?");
        $query->execute([$propertyId]);
    } catch (\Exception $ex) {
        return $ex->getMessage();
    }
}
