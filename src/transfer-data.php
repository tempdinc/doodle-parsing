<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';

// $meta_keys = ['rz_location__address', 'rz_guest_price', 'rz_guests', '_edit_lock', 'rz_location__lng', 'rz_location__lat', 'rz_reservation_length_max', 'rz_reservation_length_min', 'rz_addons', 'rz_extra_pricing', 'rz_price_seasonal', 'rz_long_term_month', 'rz_long_term_week', 'rz_security_deposit', 'rz_price_weekend', 'price_per_day', 'rz_price', 'rz_instant', 'rz_priority_custom', 'rz_priority_selection', '_edit_last', 'rz_priority', 'property_id', 'rz_priority', 'inline_featured_image'];

// $meta_keys_value = ['', '', '', '', '', '', '0', '0', '[]', '[]', '[]', '', '', '', '', '', '', '', '0', 'featured', '0', '1', '', '0', '0'];

$meta_keys = [
    '_edit_last'=>'',
    '_edit_lock'=>'',
    'inline_featured_image'=>'0',
    'post_content'=>'This home is priced to rent and won\'t be around for long. Apply now, while the current residents are preparing to move out.',
    'rz_addons'=>'[]',
    'rz_apartment_uri'=>'',
    'rz_bathrooms'=>'1',
    'rz_bed'=>'1',
    'rz_bedroom'=>'1',
    'rz_booking_type'=>'Request booking',
    'rz_checkin'=>'',
    'rz_checkout'=>'',
    'rz_city'=>'Fort Worth',
    'rz_extra_pricing'=>'[]',
    'rz_featured_benefit'=>'',
    'rz_furniture'=>'',
    'rz_gallery'=>'',
    'rz_guest_price'=>'',
    'rz_guests'=>'',
    'rz_house-rules-summary'=>'',
    'rz_instant'=>'',
    'rz_listing_category'=>'',
    'rz_listing_region'=>'',
    'rz_listing_type'=>'25769',
    'rz_location'=>'',
    'rz_location'=>'',
    'rz_location'=>'',
    'rz_location'=>'-95.712891',
    'rz_location'=>'37.09024',
    'rz_location'=>'Fort Worth, TX, US',
    'rz_location__address'=>'Fort Worth, TX, US',
    'rz_location__geo_city'=>'',
    'rz_location__geo_city_alt'=>'',
    'rz_location__geo_country'=>'',
    'rz_location__lat'=>'37.09024',
    'rz_location__lng'=>'-95.712891',
    'rz_long_term_month'=>'',
    'rz_long_term_week'=>'',
    'rz_neighborhood'=>'',
    'rz_post_address1'=>'',
    'rz_post_address2'=>'',
    'rz_price'=>'',
    'rz_price_seasonal'=>'[]',
    'rz_price_weekend'=>'',
    'rz_priority'=>'0',
    'rz_priority_custom'=>'0',
    'rz_priority_selection'=>'normal',
    'rz_reservation_length_max'=>'0',
    'rz_reservation_length_min'=>'0',
    'rz_security_deposit'=>'',
    'rz_sqft'=>'',
    'rz_state'=>'TX',
    'rz_status'=>'',
    'rz_street_line_1'=>'5848 Parkview Hills Ln',
    'rz_street_line_2'=>'',
    'rz_the-space'=>'',
    'rz_things-to-know'=>'',
    'rz_verif'=>'',
    'rz_zip'=>'76179'
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
echo date("Y-m-d H:i:s") . " Start transfer units ";
file_put_contents(LOG_DIR . '/transfer-data.log', '[' . date('Y-m-d H:i:s') . ']  Start transfer units ', FILE_APPEND);

// Getting all amenities
$apartment_amenities_list = [];
$wp_db = new MySQL('wp', 'local');
$apartment_amenities_rows = $wp_db->listRzAmenities();
foreach($apartment_amenities_rows as $apartment_amenity_row) {
    $apartment_amenities_list[$apartment_amenity_row->term_id] = $apartment_amenity_row->name;
}
// var_dump($apartment_amenities_list);
// echo array_search('Sauna', $apartment_amenities_list);
// exit();

// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
// $new_properties = $parsing_db->getAllNewRecords('properties');
// var_dump($new_properties);
$all_availability = $parsing_db->getAvailability();
echo 'New units - ' . count($all_availability);
file_put_contents(LOG_DIR . '/transfer-data.log', 'New units - ' . count($all_availability), FILE_APPEND);

foreach ($all_availability as $availability) {
    // Apartment amenities
    $apartment_amenities = [];
    $decoded_premise_services = json_decode($availability->on_premise_features);
    /*
    foreach($decoded_premise_services as $key=>$value) {
        foreach($value as $data) {
            $term_id = array_search($data, $apartment_amenities_list);
            $apartment_amenities += ['rz_amenities' => $term_id];
        }
    }
    */
    // echo ' property_id - ' . $availability->id . ' - ';
    // $all_availability = $parsing_db->getAvailability($availability->id);
    // echo ' all_availability - ' . count($all_availability) . ' | ';
    $availability_address = $availability->address;
    $availability_address_lc = replace_string($availability_address);        

    // foreach ($all_availability as $availability) {
        $date = date('Y-m-d H:i:s');
        $gmdate = gmdate('Y-m-d H:i:s');
        $unit_description = ($availability->building_desc !== NULL && $availability->building_desc != '') ? clear_description($availability->building_desc) : 'This home is priced to rent and won\'t be around for long. Apply now, while the current residents are preparing to move out.';
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
            $unit_description,
            $availability_address,
            $unit_description,
            'draft',
            'closed',
            'closed',
            '',
            $availability_address_lc,
            '',
            '',
            $date,
            $gmdate,
            '',
            0,
            'https://tempdbookindev.wpengine.com/listing/' . $availability_address_lc,
            0,
            'rz_listing',
            '',
            0
        ]);
        // Now insert beds / baths / sqft / price
        $wp_post_id = $wp_db->pdo->lastInsertId();

        $new_property_meta = [];
        $np_rz_location__address = $availability->city . ', ' . $availability->state_cd . ', US';
        $np_rz_location__state_country = $availability->state_cd . ', US';
        $np_rz_location__address_line1 = $availability->addr_line_2;
        $np_rz_location__address_line2 = $availability->city . ', ' . $availability->state_cd . ' ' . $availability->zip5_cd;
        $bath_count = trim(preg_replace("/[a-zA-Z]/", "", $availability->av_bathroom_cnt)); // rz_bathrooms
        $bed_count = trim(preg_replace("/[a-zA-Z]/", "", $availability->av_bedroom_cnt)); // rz_bed
        $sqft = trim(preg_replace("/[a-zA-Z]/", "", $availability->av_home_size_sq_ft)); // rz_sqft

        // array_push($new_property_meta, $np_rz_location__address, $meta_keys_value[1], $meta_keys_value[2], $meta_keys_value[3], $availability->longitude, $availability->latitude, $meta_keys_value[6], $meta_keys_value[7], $meta_keys_value[8], $meta_keys_value[9], $meta_keys_value[10], $meta_keys_value[11], $meta_keys_value[12], $meta_keys_value[13], $meta_keys_value[14], $meta_keys_value[15], $meta_keys_value[16], $meta_keys_value[17], $meta_keys_value[18], $meta_keys_value[19], $meta_keys_value[20]);
        $new_property_meta = [
            '_edit_last'=>'',
            '_edit_lock'=>'',
            'inline_featured_image'=>'0',       
            'post_content'=>$unit_description,
            'rz_addons'=>'[]',
            'rz_apartment_uri'=> $availability->link,            
            'rz_bathrooms'=> $bath_count,
            'rz_bed'=> $bed_count,
            'rz_bedroom'=> $bed_count,
            'rz_booking_type'=>'Request booking',
            'rz_checkin'=>'',
            'rz_checkout'=>'',
            'rz_city'=> $availability->city,
            'rz_extra_pricing'=>'[]',
            'rz_featured_benefit'=>'',
            'rz_furniture'=>'',
            'rz_gallery'=>'',
            'rz_guest_price'=>'',
            'rz_guests'=>'',
            'rz_house-rules-summary'=>'',
            'rz_instant'=>'',
            'rz_listing_category'=>'',
            'rz_listing_region'=>'',
            'rz_listing_type'=>'25769',
            'rz_location'=>'',
            'rz_location'=>'',
            'rz_location'=>'',
            'rz_location'=>$availability->longitude,
            'rz_location'=>$availability->latitude,
            'rz_location'=>$np_rz_location__address,
            'rz_location__address'=>$np_rz_location__address,
            'rz_location__geo_city'=>'',
            'rz_location__geo_city_alt'=>'',
            'rz_location__geo_country'=>'',
            'rz_location__lat'=>$availability->latitude,
            'rz_location__lng'=>$availability->longitude,
            'rz_long_term_month'=>'',
            'rz_long_term_week'=>'',
            'rz_neighborhood'=>'',
            'rz_post_address1'=>$np_rz_location__address_line1,
            'rz_post_address2'=>$np_rz_location__address_line2,
            'rz_price'=>'',
            'rz_price_seasonal'=>'[]',
            'rz_price_weekend'=>'',
            'rz_priority'=>'0',
            'rz_priority_custom'=>'0',
            'rz_priority_selection'=>'normal',
            'rz_reservation_length_max'=>'0',
            'rz_reservation_length_min'=>'0',
            'rz_security_deposit'=>'',
            'rz_sqft'=>$sqft,
            'rz_state'=>$availability->state_cd,
            'rz_status'=>'Now',
            'rz_street_line_1'=>$np_rz_location__address_line1,
            'rz_street_line_2'=>$np_rz_location__address_line2,
            'rz_the-space'=>'',
            'rz_things-to-know'=>'',
            'rz_verif'=>'',
            'rz_zip'=>$availability->zip5_cd
        ];
        $full_property_meta =  array_merge($new_property_meta, $apartment_amenities);
        if (isset($wp_post_id) && $wp_post_id != 0) {
            foreach ($full_property_meta as $key => $value) {
                $query = $wp_db->pdo->prepare("INSERT INTO `wp_postmeta` (`post_id`,`meta_key`,`meta_value`) VALUES (?, ?, ?)");
                $query->execute([$wp_post_id, $key, $value]);
            }
            if(!empty($decoded_premise_services)) {
                foreach($decoded_premise_services as $key=>$value) {
                    foreach($value as $data) {
                        $term_id = array_search($data, $apartment_amenities_list);
                        if($term_id) {
                            $query = $wp_db->pdo->prepare("INSERT INTO `wp_postmeta` (`post_id`,`meta_key`,`meta_value`) VALUES (?, ?, ?)");
                            $query->execute([$wp_post_id, 'rz_amenities', $term_id]);                        
                        }
                    }
                }            
            }
        }
        $query = $parsing_db->pdo->prepare("UPDATE `availability` SET `post_id` = ? WHERE `id` = ?");
        $query->execute([$wp_post_id, $availability->av_id]);
        // echo 'Added post - ' . $wp_post_id . ' | ';
        // exit();
    // }        
}

echo " >>> " . date("Y-m-d H:i:s") ." - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/transfer-data.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);

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
    $string = str_replace('.com','-com',$string);
    $search_strings = array('rentprogress','zillow','apartments-com','hotpads','contact','phone','@','Progress','call','Application Coordinator','Managed By','Website','Email','email','Leasing Specialists');
    $array_strings = explode('.',$string);
    foreach($array_strings as $key=>$value) {
        foreach($search_strings as $search_string) {
            if(stripos($value,$search_string)) {
                unset($array_strings[$key]);
            }
        }
    }
    return implode('.',$array_strings);
    // $vowels = array(" ", ".", ",", "$", ";", ":", "%", "*", "(", ")", "+", "=", "_", "|", "#", "@", "!", "`", "~", "^", "&");
    // return mb_strtolower(str_replace($vowels, '-', $string));
}