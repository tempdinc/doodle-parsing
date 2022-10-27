<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';

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

echo "Transfer properties to WP - ";

// Start transfer
echo 'Start..' . PHP_EOL;

// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
$new_properties = $parsing_db->getAllNewRecords('properties');
// var_dump($new_properties);
echo 'New properties - ' . count($new_properties);
foreach ($new_properties as $property) {
    echo $property->id . ' | '; // ToDo - check multiple rooms
    $date = date('Y-m-d H:i:s');
    $gmdate = gmdate('Y-m-d H:i:s');
    $property_description = '';
    if($property->building_desc !== NULL) {
        $property_description = $property->building_desc; 
    } elseif ($property->property_info !== NULL) {
        $property_description = $property->property_info; 
    }
    $property_address = $property->address;
    $property_address_lc = replace_string($property_address);
    $all_availability = $parsing_db->getAvailability($property->id);
    $availability_counter = count($all_availability);
    echo 'availability_counter - ' . $availability_counter;
    exit();
    $wp_db = new MySQL('wp', 'local');
    foreach ($all_availability as $availability) {
        $query = $wp_db->pdo->prepare("INSERT INTO `wp_posts` (
            `post_date`,
            `post_date_gmt`,
            `post_content`,
            `post_title`,
            `post_excerpt`,
            `post_status`,
            `comment_status`,
            `ping_status`,
            `post_password`,
            `post_name`,
            `to_ping`,
            `pinged`,
            `post_modified`,
            `post_modified_gmt`,
            `post_content_filtered`,
            `post_parent`,
            `guid`,
            `menu_order`,
            `post_type`,
            `post_mime_type`,
            `comment_count`
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $query->execute([
            $date,
            $gmdate,
            $property_description,
            $property_address,
            $property_description,
            'draft',
            'closed',
            'closed',
            '',
            $property_address_lc,
            '',
            '',
            $date,
            $gmdate,
            '',
            0,
            'https://tempdbookindev.wpengine.com/listing/' . $property_address_lc,
            0,
            'rz_listing',
            '',
            0
        ]);
        // Now insert beds / baths / sqft / price
        $wp_post_id = $wp_db->pdo->lastInsertId();
        $new_property_meta = [];
        $np_rz_location__address = $property->city . ', ' . $property->state_cd . ', US';
        $np_rz_location__state_country = $property->state_cd . ', US';
        $np_rz_location__address_line1 = $property->addr_line_2;
        $np_rz_location__address_line2 = $property->city . ', ' . $property->state_cd . ' ' . $property->zip5_cd;
        // array_push($new_property_meta, $np_rz_location__address, $meta_keys_value[1], $meta_keys_value[2], $meta_keys_value[3], $property->longitude, $property->latitude, $meta_keys_value[6], $meta_keys_value[7], $meta_keys_value[8], $meta_keys_value[9], $meta_keys_value[10], $meta_keys_value[11], $meta_keys_value[12], $meta_keys_value[13], $meta_keys_value[14], $meta_keys_value[15], $meta_keys_value[16], $meta_keys_value[17], $meta_keys_value[18], $meta_keys_value[19], $meta_keys_value[20]);
        array_push(
            $new_property_meta,
            $property->link,            
            '1',
            '10',
            'featured',
            '0', // rz_priority_custom
            '', // rz_instant
            $availability->listing_price, // rz_price
            '', // rz_price_weekend
            '',
            '',
            '',
            '[]',
            '[]',
            '[]',
            '0',
            '0',
            $property->latitude, // rz_location__lat
            $property->longitude, // rz_location__lng
            '', // rz_booking_pending
            '', // rz_booking_booked
            '', // _edit_lock
            '', // rz_guests
            '', // rz_guest_price
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
            'Request booking', // rz_booking_type
            $np_rz_location__address_line1, // rz_post_address1
            $np_rz_location__address_line2, // rz_post_address2
            $property->building_desc, // post_content
            '', // rz_the-space
            '87', // rz_listing_category
            '',
            '',
            '[]', // rz_gallery
            '',
            $np_rz_location__address, // rz_location
            $property->latitude, // rz_location
            $property->longitude, // rz_location
            'US', // rz_location
            $np_rz_location__address, // rz_location
            $np_rz_location__state_country, // rz_location
            $np_rz_location__address_line1, // rz_street_line_1
            $property->city, // rz_city
            $property->state_cd, // rz_state
            $property->zip5_cd, // rz_zip
            trim(preg_replace("/[a-zA-Z]/", "", $availability->bedroom_cnt)), // rz_bed
            trim(preg_replace("/[a-zA-Z]/", "", $availability->bedroom_cnt)), // rz_bedroom
            trim(preg_replace("/[a-zA-Z]/", "", $availability->bathroom_cnt)), // rz_bathrooms
            trim(preg_replace("/[a-zA-Z]/", "", $availability->home_size_sq_ft)), // rz_sqft
            '', // rz_house-rules-summary
            '', // rz_checkin
            '', // rz_checkout
            '', // rz_status
            '', // rz_featured_benefit
            '21233' // rz_listing_type
        );
        if (isset($wp_post_id) && $wp_post_id != 0) {
            foreach ($meta_keys as $key => $value) {
                $query = $wp_db->pdo->prepare("INSERT INTO `wp_postmeta` (`post_id`,`meta_key`,`meta_value`) VALUES (?, ?, ?)");
                $query->execute([$wp_post_id, $value, $new_property_meta[$key]]);
            }
        }
        $query = $parsing_db->pdo->prepare("UPDATE `properties` SET `post_id` = ? WHERE `id` = ?");
        $query->execute([$wp_post_id, $property->id]);
        echo 'Added post - ' . $wp_post_id . ' | ';
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