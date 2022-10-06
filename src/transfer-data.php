<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

require_once __DIR__ . '/bootstrap.php';
require_once '../../wp-load.php';

// Start transfer
echo date("Y-m-d H:i:s") . " Start transfer parsed data";
file_put_contents(LOG_DIR . '/transfer-data.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

// Transfer-amenities
$create_xlsx = false;

//Query our MySQL table
$parsing_db = new MySQL('parsing', 'local');

// Apartment amenities
$query = $parsing_db->pdo->prepare("SELECT `on_premise_features` FROM `properties` WHERE `on_premise_features` != ''");
$query->execute();
$rows = $query->fetchAll();
$apartment_amenities = [];
foreach($rows as $row) {
    $on_premise_features = $row->on_premise_features;
    $decoded_premise_services = json_decode($on_premise_features);
    // var_dump($row);
    foreach($decoded_premise_services as $key=>$value) {
        foreach($value as $data) {
            array_push($apartment_amenities,$data);
        }
    }
}
$apartment_amenities = array_unique($apartment_amenities,SORT_STRING);
sort($apartment_amenities);

if($create_xlsx) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $counter = 0;
    foreach($apartment_amenities as $amenity) {
        $counter++;
        $cell = 'A'.$counter;
        $sheet->setCellValue($cell, var_export($amenity, true));
    }
    $writer = new Xlsx($spreadsheet);
    $writer->save('export_apartment_amenities.xlsx');
}


// Community amenities
$query = $parsing_db->pdo->prepare("SELECT `on_premise_services` FROM `properties` WHERE `on_premise_services` != ''");
$query->execute();
$rows = $query->fetchAll();
$community_amenities = [];
foreach($rows as $row) {
    $on_premise_services = $row->on_premise_services;
    $decoded_premise_services = json_decode($on_premise_services);
    // var_dump($row);
    foreach($decoded_premise_services as $key=>$value) {
        foreach($value as $data) {
            array_push($community_amenities,$data);
        }
    }
}
$community_amenities = array_unique($community_amenities,SORT_STRING);
$community_amenities = array_diff($community_amenities,$apartment_amenities);
sort($community_amenities);

if($create_xlsx) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $counter = 0;
    foreach($community_amenities as $amenity) {
        $counter++;
        $cell = 'A'.$counter;
        $sheet->setCellValue($cell, var_export($amenity, true));
    }
    $writer = new Xlsx($spreadsheet);
    $writer->save('export_community_amenities.xlsx');
}

$amenities_counter = 0;
//Query our MySQL table
$wp_db = new MySQL('wp', 'local');
foreach($apartment_amenities as $amenity) {
    $query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_terms` WHERE `name` = ? LIMIT 1");
    $query->execute([$amenity]);
    $isDuplicate = $query->fetchColumn();
    // $amenity_slug = str_replace(' ', '_', trim(mb_strtolower($amenity)));
    if (!$isDuplicate) {    
        // echo $amenity . PHP_EOL;
        $amenity_slug = str_replace(' ', '_', preg_replace("/[^0-9a-z]/","_",trim(mb_strtolower($amenity))));
        if(mb_substr($amenity_slug,-1) == '_') {
            $amenity_slug = mb_substr($amenity_slug,0,-1);
        }
        // echo $amenity_slug . PHP_EOL;
        $slugDuplicate = checkSlug($amenity_slug);
        while($slugDuplicate) {
            $amenity_slug = $amenity_slug . '1';
            $slugDuplicate = checkSlug($amenity_slug);
        }
    } 

    if (!$isDuplicate) {    
        $query = $wp_db->pdo->prepare("INSERT INTO `wp_terms` (`name`,`slug`) VALUES (?,?)");
        $query->execute([$amenity,$amenity_slug]);

        $query = $wp_db->pdo->prepare("SELECT `term_id` FROM `wp_terms` WHERE `slug` = ? LIMIT 1");
        $query->execute([$amenity_slug]);
        $prop = $query->fetch();
        if($prop->term_id) {
            $taxonomy = 'rz_amenities';
            $description = '';
            $query = $wp_db->pdo->prepare("INSERT INTO `wp_term_taxonomy` (`term_id`,`taxonomy`,`description`) VALUES (?,?,?)");
            $query->execute([$prop->term_id,$taxonomy,$description]);
            $prop = $query->fetch();        
        }
        $amenities_counter++;
        file_put_contents(LOG_DIR . '/amenities.log', $amenity . ' | ', FILE_APPEND);
    }
}
echo " \033[31mAdded amenities: " . $amenities_counter . "\033[0m";
file_put_contents(LOG_DIR . '/transfer-data.log', ' Added amenities: ' . $amenities_counter, FILE_APPEND);
// Transfer amenities END

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
/*
//Fork settings
pcntl_async_signals(true);

pcntl_signal(SIGTERM, 'signalHandler'); // Termination ('kill' was called)
pcntl_signal(SIGHUP, 'signalHandler'); // Terminal log-out
pcntl_signal(SIGINT, 'signalHandler'); // Interrupted (Ctrl-C is pressed)

// Saving parent pid
file_put_contents('parentPid.out', getmypid());
*/

// Getting all amenities
$apartment_amenities_list = [];
$wp_db = new MySQL('wp', 'local');
$apartment_amenities_rows = $wp_db->listRzAmenities();
foreach($apartment_amenities_rows as $apartment_amenity_row) {
    $apartment_amenities_list[$apartment_amenity_row->term_id] = $apartment_amenity_row->name;
}

// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
$all_availability = $parsing_db->getAvailability();
echo " \033[34mNew units - " . count($all_availability) . "\033[0m";
file_put_contents(LOG_DIR . '/transfer-data.log', ' | New units - ' . count($all_availability), FILE_APPEND);

foreach ($all_availability as $availability) {
    // Apartment amenities
    $apartment_amenities = [];
    $decoded_premise_services = json_decode($availability->on_premise_features);
    $availability_address = $availability->address;
    $availability_address_lc = replace_string($availability_address);        

    $wpImageArray = [];
    $image_urls = $availability->av_image_urls;
    // echo $availability->av_id;
    $decoded_image_urls = json_decode($image_urls);
    // var_dump($decoded_image_urls);
    if(is_array($decoded_image_urls) && count($decoded_image_urls) > 0) {
        foreach($decoded_image_urls as $key=>$value) {
            $re = '`^.*/`m';
            $subst = '';        
            /* IMAGE NAME CHECKING */
            $orig_full_filename = preg_replace($re, $subst, parse_url($value, PHP_URL_PATH));
            echo ' | orig_full_filename - ' . $orig_full_filename;
            $orig_fileextension = getExtension($orig_full_filename);
            echo ' | orig_fileextension - ' . $orig_fileextension; 
            $orig_filename = substr(str_replace($orig_fileextension,'',$orig_full_filename),0,-1);
            echo ' | ' . $orig_filename;
            $filename_path = __DIR__ . '/images/' . $orig_filename . '.' . $orig_fileextension;
            $is_file_exist = file_exists($filename_path);
            $filename_counter = 1;
            while($is_file_exist) {
                $orig_filename = $orig_filename . $filename_counter;
                echo ' | ' . $orig_filename . PHP_EOL;
                $filename_path = __DIR__ . '/images/' . $orig_filename . '.' . $orig_fileextension;
                $is_file_exist = isFileExist($filename_path);
                $filename_counter++;
            }
            /* IMAGE NAME CHECKING END */
            $unit_source = $availability->source;
            if($unit_source == 'rentprogress.com') {
                cropImage($filename_path,100,82);
            }
            // echo $filename . PHP_EOL;
            // removeExif($filename_path);
            file_put_contents($filename_path, file_get_contents($value));
            $moveToWP = moveToWp($filename_path,$availability_address);
            if($moveToWP) {
                $wpImageId = (object)array('id' => (string)$moveToWP);
                array_push($wpImageArray,$wpImageId);
            } else {
                file_put_contents(LOG_DIR . '/transfer-data.log', ' | Error transferring WP - ' . $filename_path, FILE_APPEND);
            }
        }
    }
    $rz_gallery = json_encode($wpImageArray);
    clearImages();

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
        'rz_zip'=>$availability->zip5_cd,
        'rz_gallery'=>$rz_gallery
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
    file_put_contents(LOG_DIR . '/wp_posts.log', $wp_post_id . ' | ', FILE_APPEND);
    // echo 'Added post - ' . $wp_post_id . ' | ';
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

function getExtension($filename) {
    return substr(strrchr($filename, '.'), 1);
}

function isFileExist($filename) {
    return file_exists($filename);
}

function removeExif($incoming_file) {
    $img = new Imagick(realpath($incoming_file));
    $profiles = $img->getImageProfiles("icc", true);
    $img->stripImage();
    if(!empty($profiles)) {
       $img->profileImage("icc", $profiles['icc']);
    }
}

function cropImage($image,$crop_width,$crop_height) {
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

function moveToWp ($image_url,$alt_text) {
    // $image_url = 'adress img';
    try {
        $upload_dir = wp_upload_dir();
        $image_data = file_get_contents( $image_url );
        $filename = basename( $image_url );
        if ( wp_mkdir_p( $upload_dir['path'] ) ) {
            $file = $upload_dir['path'] . '/' . $filename;
        }
        else {
            $file = $upload_dir['basedir'] . '/' . $filename;
        }
        file_put_contents( $file, $image_data );
        $wp_filetype = wp_check_filetype( $filename, null );
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name( $filename ),
            'post_content' => '',
            'post_status' => 'inherit'
        );
        $attach_id = wp_insert_attachment( $attachment, $file );
        // echo 'attach_id - ' . $attach_id . PHP_EOL;
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt_text );
        return $attach_id;
    } catch(Exception $e) {
        echo($e->getMessage());
        return false;
    }
}

function clearImages() {
    if (file_exists(__DIR__ . '/images/')) {
        foreach (glob(__DIR__ . '/images/*') as $file) {
            unlink($file);
        }
    }
}

function checkSlug($slug_string) {
    echo $slug_string . PHP_EOL;
    $wp_db = new MySQL('wp', 'local');
    $query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_terms` WHERE `slug` = ? LIMIT 1");
    $query->execute([$slug_string]);
    return $query->fetchColumn();   
}
