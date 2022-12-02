<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once '../../wp-load.php';

// Clear log files
$f = fopen(LOG_DIR . '/new-transfer-data.log', 'w');
fclose($f);
$f = fopen(LOG_DIR . '/fix-post-regions.log', 'w');
fclose($f);
//Query our MySQL table
$parsing_db = new MySQL('parsing', 'local');

// Apartment amenities
$apartment_amenities = [];
$total_rows = $parsing_db->count('properties', [['field' => "on_premise_features", 'compare' => "!=", 'value' => "''"]]);
$pages = intdiv($total_rows, 100);
echo 'Apartment amenities total pages - ' . $pages . PHP_EOL;
for ($i = 0; $i <= $pages; $i++) {
    echo 'page - ' . $i;
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
                array_push($apartment_amenities, $data);
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
echo 'Community amenities total pages - ' . $pages . PHP_EOL;
for ($i = 0; $i <= $pages; $i++) {
    echo 'page - ' . $i;
    $start = $i * 100;
    try {
        $query = $parsing_db->pdo->prepare("SELECT `on_premise_services` FROM `properties` WHERE `on_premise_features` != '' LIMIT $start,100");
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
                array_push($community_amenities, $data);
            }
        }
    }
}
$community_amenities = array_unique($community_amenities, SORT_STRING);
$community_amenities = array_diff($community_amenities, $apartment_amenities);
sort($community_amenities);

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
    file_put_contents(LOG_DIR . '/amenities.log', $amenity . ' > ' . $response . ' | ', FILE_APPEND);
}
echo " \033[31mAdded amenities: " . $amenities_counter . "\033[0m";

file_put_contents(LOG_DIR . '/new-transfer-data.log', ' Added amenities: ' . $amenities_counter, FILE_APPEND);
// Transfer amenities END
echo 'done2';
// Getting all amenities
$apartment_amenities_list = [];
$wp_db = new MySQL('wp', 'local');
$apartment_amenities_rows = $wp_db->listRzAmenities();
foreach ($apartment_amenities_rows as $apartment_amenity_row) {
    $apartment_amenities_list[$apartment_amenity_row->term_id] = $apartment_amenity_row->name;
}

$meta_keys = ['rz_location__address', 'rz_guest_price', 'rz_guests', '_edit_lock', 'rz_location__lng', 'rz_location__lat', 'rz_reservation_length_max', 'rz_reservation_length_min', 'rz_addons', 'rz_extra_pricing', 'rz_price_seasonal', 'rz_long_term_month', 'rz_long_term_week', 'rz_security_deposit', 'rz_price_weekend', 'price_per_day', 'rz_price', 'rz_instant', 'rz_priority_custom', 'rz_priority_selection', '_edit_last', 'rz_priority', 'property_id', 'rz_priority', 'inline_featured_image'];

$meta_keys_value = ['', '', '', '', '', '', '0', '0', '[]', '[]', '[]', '', '', '', '', '', '', '', '0', 'featured', '0', '1', '', '0', '0'];

$meta_keys = [
    'rz_orig_url',
    'rz_priority',
    '_edit_last',
    'rz_priority_selection',
    'rz_priority_custom',
    'rz_instant',
    'rz_price',
    'rz_price_weekend',
    'rz_security_deposit',
    'rz_long_term_week',
    'rz_long_term_month',
    'rz_price_seasonal',
    'rz_extra_pricing',
    'rz_addons',
    'rz_reservation_length_min',
    'rz_reservation_length_max',
    'rz_location__lat',
    'rz_location__lng',
    'rz_booking_pending',
    'rz_booking_booked',
    '_edit_lock',
    'rz_guests',
    'rz_guest_price',
    'rz_location__address',
    'rz_location__geo_country',
    'rz_location__geo_city',
    'rz_location__geo_city_alt',
    'price_per_day',
    'inline_featured_image',
    'rz_input_4',
    'rz_input_6',
    'rz_input_8',
    'rz_input_9',
    'rz_input_10',
    'rz_input_11',
    'rz_input_12',
    'rz_input_14',
    'rz_input_15',
    'rz_input_16',
    'rz_input_17',
    'rz_input_18',
    'rz_input_19',
    'rz_input_33',
    'rz_input_36',
    'rz_furniture',
    'rz_verif',
    'rz_booking_type',
    'rz_post_address1',
    'rz_post_address2',
    'post_content',
    'rz_the-space',
    'rz_listing_category',
    'rz_things-to-know',
    'rz_neighborhood',
    'rz_gallery',
    'rz_listing_region',
    'rz_location',
    'rz_location',
    'rz_location',
    'rz_location',
    'rz_location',
    'rz_location',
    'rz_street_line_1',
    'rz_city',
    'rz_state',
    'rz_zip',
    'rz_bed',
    'rz_bedroom',
    'rz_bathrooms',
    'rz_sqft',
    'rz_house-rules-summary',
    'rz_checkin',
    'rz_checkout',
    'rz_status',
    'rz_featured_benefit',
    'rz_listing_type'
];
$meta_keys_value = [
    '',
    '1',
    '10',
    'featured',
    '0', // rz_priority_custom
    '', // rz_instant
    '', // rz_price
    '', // rz_price_weekend
    '',
    '',
    '',
    '[]',
    '[]',
    '[]',
    '0',
    '0',
    '',
    '',
    '',
    '',
    '',
    '', // rz_guests
    '',
    '', // rz_location__address
    '', // rz_location__geo_country
    '', // rz_location__geo_city
    '', // rz_location__geo_city_alt
    '', // price_per_day
    '0', // inline_featured_image
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    '', // rz_booking_type
    '', // rz_post_address1
    '', // rz_post_address2
    '', // post_content
    '', // rz_the-space
    '', // rz_listing_category
    '',
    '',
    '[]', // rz_gallery
    '',
    '', // rz_location
    '', // rz_location
    '', // rz_location
    '', // rz_location
    '', // rz_location
    '', // rz_location
    '', // rz_street_line_1
    '', // rz_city
    '', // rz_state
    '', // rz_zip
    '', // rz_bed
    '', // rz_bedroom
    '', // rz_bathrooms
    '', // rz_sqft
    '', // rz_house-rules-summary
    '', // rz_checkin
    '', // rz_checkout
    '', // rz_status
    '', // rz_featured_benefit
    '21233' // rz_listing_type
];
//Fork settings
pcntl_async_signals(true);

pcntl_signal(SIGTERM, 'signalHandler'); // Termination ('kill' was called)
pcntl_signal(SIGHUP, 'signalHandler'); // Terminal log-out
pcntl_signal(SIGINT, 'signalHandler'); // Interrupted (Ctrl-C is pressed)

// Saving parent pid
file_put_contents('parentPid.out', getmypid());

// Start transfer
echo date("Y-m-d H:i:s") . " Start post regions WP ";
file_put_contents(LOG_DIR . '/fix-post-regions.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

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

$regionsDB = file_get_contents(__DIR__ . '/regions.json');
$regionsDB = json_decode($regionsDB, true);

$rz_full_regions = [];
foreach ($regionsDB as $regionDB) {
    foreach ($regionDB as $region_key => $region_cities) {
        $rz_regions_key = array_search($region_key, array_column($rz_regions, 'name'));
        $term_id = $rz_regions[$rz_regions_key]['term_id'];
        $term_name = $rz_regions[$rz_regions_key]['name'];
        $term_slug = $rz_regions[$rz_regions_key]['slug'];
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

// NEW FULL REGIONS
file_put_contents(LOG_DIR . '/get-regions.log', '[' . date('Y-m-d H:i:s') . '] Get regions - ', FILE_APPEND);
// Clear log files
$f = fopen(LOG_DIR . '/new-regions.json', 'w');
fclose($f);

echo "Init regions.. ";

$old_regions = [];

$regionsDB = file_get_contents(__DIR__ . '/regions.json');
$regionsDB = json_decode($regionsDB, true);

$full_regions = [];
foreach ($regionsDB as $regionDB) {
    foreach ($regionDB as $region_key => $region_cities) {
        $temp_city = [];
        foreach ($region_cities as $region_city) {
            $region_city_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($region_city)));
            $temp_city[] = [
                'city_name' => $region_city,
                'city_slug' => $region_city_slug
            ];
        }
        $full_regions[$region_key] = $temp_city;
    }
}
file_put_contents('old-regions.json', json_encode($full_regions));

$old_citiesDB = file_get_contents(__DIR__ . '/new-regions.json');
$old_regions = json_decode($old_citiesDB, true);
$old_regions_key = array_keys($old_regions);

foreach ($full_regions as $full_region => $full_cities) {
    $array_search = array_search($full_region, $old_regions_key);
    if ($array_search !== false && $old_regions[$full_region] !== NULL) {
        $old_regions[$full_region] = array_merge($old_regions[$full_region], $full_cities);
        $old_regions[$full_region] = array_unique($old_regions[$full_region], SORT_REGULAR);
        array_multisort(array_column($old_regions[$full_region], 'city_slug'), SORT_ASC, $old_regions[$full_region]);
    } else {
        $old_regions[$full_region] = $full_cities;
    }
}

file_put_contents('old-regions.json', json_encode($old_regions));

$citiesDB = file_get_contents(__DIR__ . '/cities.json');
$citiesDB = json_decode($citiesDB, true);
$counter = 0;
$rz_full_regions = [];
foreach ($citiesDB as $states) {
    foreach ($states as $code => $citiesArray) {
        foreach ($citiesArray as $city) {

            $region = strtoupper($city) . ', ' . strtoupper($code);
            $region_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($region)));
            echo $region_slug . ' | ';
            $rz_regions_key = array_search($region_slug, array_column($rz_regions, 'slug'));
            $term_id = $rz_regions[$rz_regions_key]['term_id'];
            $term_name = $rz_regions[$rz_regions_key]['name'];
            $term_slug = $rz_regions[$rz_regions_key]['slug'];

            $key = array_search($region, $old_regions_key);

            $city = str_replace(' ', '-', strtolower($city));
            $code = strtolower($code);
            $base_link = 'https://rentprogress.com/bin/progress-residential/property-search.market-' . urlencode($city . '-' . $code) . '.json';
            // echo $base_link;
            $marketDB = file_get_contents($base_link);
            $marketDB = json_decode($marketDB, true);
            $new_cities = [];
            foreach ($marketDB as $markets) {
                foreach ($markets as $market) {
                    $new_city = $market['city'] . ', ' . $market['state'];
                    $new_city_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($new_city)));

                    $region_city_up = strtoupper($new_city);
                    $rz_full_regions[] = [
                        'term_id'       => $term_id,
                        'city_name'     => $region_city_up,
                        'city_slug'     => $new_city_slug,
                        'region_name'   => $region,
                        'region_slug'   => $term_slug
                    ];

                    $new_cities[] = [
                        'city_name' => $new_city,
                        'city_slug' => $new_city_slug
                    ];
                    $counter++;
                }
            }

            if ($key !== false && $old_regions[$region] !== NULL) {
                $new_cities = array_merge($old_regions[$region], $new_cities);
                $new_cities = array_unique($new_cities, SORT_REGULAR);
                array_multisort(array_column($new_cities, 'city_slug'), SORT_ASC, $new_cities);
            }
            $old_regions[$region] = $new_cities;
        }
    }
}

file_put_contents('new-regions.json', json_encode($old_regions));
file_put_contents(LOG_DIR . '/get-regions.log', ' total added cities - ' . $counter . PHP_EOL . ' END >>> ' . date('Y-m-d H:i:s'), FILE_APPEND);


// NEW FULL REGIONS END
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

file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | rz_listing_category WP - ' . $rz_listing_category . PHP_EOL, FILE_APPEND);

// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
$total_properties = $parsing_db->countAllNewRecords();
echo 'Total properties - ' . $total_properties . PHP_EOL;
$pages = intdiv($total_properties, 100);
echo 'Total pages - ' . $pages . PHP_EOL;
for ($i = 0; $i <= $pages; $i++) {
    $new_properties = $parsing_db->getAllNewRecords(0, 100);
    foreach ($new_properties as $property) {
        echo $property->id . ' | '; // ToDo - check multiple rooms

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
        // $current_city_slug = str_replace(' ', '-', $city_low . ' ' . $state_low);
        $current_city_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', $city_low . ' ' . $state_low));
        $key = array_search($current_city_slug, array_column($rz_full_regions, 'city_slug'));
        if ($key !== false) {
            $rz_region_id = $rz_full_regions[$key]['term_id'];
            $region_slug = $rz_full_regions[$key]['region_slug'];
            echo 'rz_region_id - ' . $rz_region_id;
            $custom_tax = array(
                'rz_regions' => array(
                    $rz_region_id
                )
            );
        }
        echo $current_city_slug . ' | ';
        echo 'region_slug - ' . $region_slug;

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
        $apartment_amenities = [];
        $decoded_premise_services = json_decode($property->on_premise_features);

        // Image gallery
        $wpImageArray = [];
        $image_urls = $property->image_urls;
        // echo $availability->av_id;
        $decoded_image_urls = json_decode($image_urls);
        // var_dump($decoded_image_urls);
        if (is_array($decoded_image_urls) && count($decoded_image_urls) > 0) {
            foreach ($decoded_image_urls as $key => $value) {
                $re = '`^.*/`m';
                $subst = '';
                /* IMAGE NAME CHECKING */
                $orig_full_filename = preg_replace($re, $subst, parse_url($value, PHP_URL_PATH));
                $orig_fileextension = getExtension($orig_full_filename);
                $orig_filename = substr(str_replace($orig_fileextension, '', $orig_full_filename), 0, -1);
                $filename_path = __DIR__ . '/images/' . $orig_filename . '.' . $orig_fileextension;
                $is_file_exist = file_exists($filename_path);
                $filename_counter = 1;
                while ($is_file_exist) {
                    $orig_filename = $orig_filename . $filename_counter;
                    // echo ' | ' . $orig_filename . PHP_EOL;
                    $filename_path = __DIR__ . '/images/' . $orig_filename . '.' . $orig_fileextension;
                    $is_file_exist = isFileExist($filename_path);
                    $filename_counter++;
                }
                $file_get = file_get_contents($value);
                if ($file_get !== false) {
                    file_put_contents($filename_path, $file_get);
                    /* IMAGE NAME CHECKING END */
                    if ($unit_source == 'rentprogress.com') {
                        cropImage($filename_path, 100, 82);
                    }
                    $moveToWP = moveToWp($filename_path, $property_address);
                    if ($moveToWP) {
                        $wpImageId = (object)array('id' => (string)$moveToWP);
                        array_push($wpImageArray, $wpImageId);
                    } else {
                        file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | Error image transferring WP - ' . $filename_path . PHP_EOL, FILE_APPEND);
                    }
                } else {
                    file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | Error image transferring WP - ' . $filename_path . PHP_EOL, FILE_APPEND);
                }
            }
        }

        // Checking for images of post
        $is_gallery_empty = (count($wpImageArray) == 0) ? true : false;
        $rz_gallery = json_encode($wpImageArray);
        clearImages();

        if(!$is_gallery_empty && $region_slug != '') {
            // Создаем массив данных новой записи
            $post_data = array(
                'post_title'    => $property_address,
                'post_name'     => $property_address,
                'post_content'  => $property_description,
                'post_excerpt'  => $property_description,
                'post_status'   => 'publish',
                'post_author'   => 62,
                'post_type'     => 'rz_listing'
            );
            // Вставляем запись в базу данных
            $main_post_insert_result = wp_insert_post($post_data);
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
                                add_post_meta($main_post_insert_result, 'rz_amenities', $term_id, true) or update_post_meta($main_post_insert_result, 'rz_amenities', $term_id);
                            }
                        }
                    }
                }
            }

            $all_availability = $parsing_db->getAvailabilityNowByProperty($property->id);
            $availability_counter = count($all_availability);
            echo ' | availability_counter - ' . $availability_counter;
            if ($availability_counter > 0 && isset($all_availability[0]->listing_price) && !$is_gallery_empty) {
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

                                echo ' | rz_unit_type - ' . $rz_unit_type . ' | property address - ' . $property_address . ' | unit_post_insert_result - ' . $unit_post_insert_result;
                                file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | rz_unit_type - ' . $rz_unit_type . ' | property address - ' . $property_address . ' | unit_post_insert_result - ' . $unit_post_insert_result . PHP_EOL, FILE_APPEND);

                                $new_property_meta = [];

                                $bath_count = trim(preg_replace("/[a-zA-Z]/", "", $availability->bathroom_cnt)); // rz_bathrooms
                                if (intval($bath_count) != 0) $availability_baths[] = $bath_count;
                                $bed_count = trim(preg_replace("/[a-zA-Z]/", "", $availability->bedroom_cnt)); // rz_bed
                                if (intval($bed_count) != 0) $availability_beds[] = $bed_count;
                                $sqft = trim(preg_replace("/\D/", "", $availability->home_size_sq_ft)); // rz_sqft
                                if (intval($sqft) != 0) $availability_sqft[] = $sqft;

                                $listing_price = clearPrice($availability->listing_price);
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
                    echo ' | rz_unit_type - ' . $rz_unit_type . ' | property address - ' . $property_address . ' | main_post_insert_result - ' . $main_post_insert_result . ' | property->id - ' . $property->id . PHP_EOL;
                    file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | rz_unit_type - ' . $rz_unit_type . ' | property address - ' . $property_address . ' | main_post_insert_result - ' . $main_post_insert_result . ' | property->id - ' . $property->id . PHP_EOL, FILE_APPEND);
                    $query = $parsing_db->pdo->prepare("UPDATE `properties` SET post_id = ? WHERE id = ?");
                    $query->execute([$main_post_insert_result, $property->id]);
                } else {
                    $rz_unit_type = 'single';
                    $rz_search = 1;
                    $rz_multi_units = [];

                    echo ' | rz_unit_type - ' . $rz_unit_type . ' | property address - ' . $property_address . ' | main_post_insert_result - ' . $main_post_insert_result . ' | property->id - ' . $property->id . PHP_EOL;
                    file_put_contents(LOG_DIR . '/new-transfer-data.log', ' | rz_unit_type - ' . $rz_unit_type . ' | property address - ' . $property_address . ' | main_post_insert_result - ' . $main_post_insert_result . ' | property->id - ' . $property->id . PHP_EOL, FILE_APPEND);

                    if ($main_post_insert_result && $main_post_insert_result != 0) {

                        $new_property_meta = [];

                        $bath_count = trim(preg_replace("/[a-zA-Z]/", "", $all_availability[0]->bathroom_cnt)); // rz_bathrooms
                        $bed_count = trim(preg_replace("/[a-zA-Z]/", "", $all_availability[0]->bedroom_cnt)); // rz_bed
                        $sqft = trim(preg_replace("/\D/", "", $availability->home_size_sq_ft)); // rz_sqft

                        $listing_price = clearPrice($all_availability[0]->listing_price);

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
            }
        }
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

function moveToWp($image_url, $alt_text)
{
    // $image_url = 'adress img';
    try {
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents($image_url);
        $filename = basename($image_url);
        if (wp_mkdir_p($upload_dir['path'])) {
            $file = $upload_dir['path'] . '/' . $filename;
        } else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }
        file_put_contents($file, $image_data);
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $file);
        // echo 'attach_id - ' . $attach_id . PHP_EOL;
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

function checkSlug($slug_string)
{
    echo $slug_string . PHP_EOL;
    $wp_db = new MySQL('wp', 'local');
    $query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_terms` WHERE slug = ? LIMIT 1");
    $query->execute([$slug_string]);
    return $query->fetchColumn();
}

function clearPrice($price)
{
    $price_array = explode('$', trim($price));
    $new_price = end($price_array);
    return preg_replace('/[^0-9]/', '', $new_price);
}
