<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once 'bootstrap.php';
require_once(realpath('../../wp-load.php'));

// Clear log files
$f = fopen(LOG_DIR . '/quote-add.log', 'w');
fclose($f);

file_put_contents(LOG_DIR . '/quote-add.log', '[' . date('Y-m-d H:i:s') . '] Start >>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>> ' . PHP_EOL, FILE_APPEND);

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
   if ($rz_regions_key !== false) {
      $term_id = $rz_regions[$rz_regions_key]['term_id'];
      // $term_name = $rz_regions[$rz_regions_key]['name'];
      // $term_slug = $rz_regions[$rz_regions_key]['slug'];
      $rz_full_regions[] = [
         'term_id'       => $term_id,
         'city_name'     => $rz_full_city['city_name'],
         'city_slug'     => $rz_full_city['city_slug'],
         'region_name'   => $rz_full_city['region_name'],
         'region_slug'   => $rz_full_city['region_slug']
      ];
   }
}
// NEW REGIONS END

if (isset($_POST['quote_id']) && isset($_POST['quote_title']) && isset($_POST['quote_address'])) {
   $quote_id = $_POST['quote_id'];
   $quote_title = ((isset($_POST['quote_title'])) ? $_POST['quote_title'] : '');
   $quote_description = ((isset($_POST['quote_description'])) ? $_POST['quote_description'] : '');
   $quote_address = ((isset($_POST['quote_address'])) ? $_POST['quote_address'] : '');
   $quote_street = ((isset($_POST['quote_street'])) ? $_POST['quote_street'] : '');
   $quote_city = ((isset($_POST['quote_city'])) ? $_POST['quote_city'] : '');
   $quote_state = ((isset($_POST['quote_state'])) ? $_POST['quote_state'] : '');
   $quote_zip = ((isset($_POST['quote_zip'])) ? $_POST['quote_zip'] : '');
   $quote_baths = ((isset($_POST['quote_baths'])) ? $_POST['quote_baths'] : '');
   $quote_beds = ((isset($_POST['quote_beds'])) ? $_POST['quote_beds'] : '');
   $quote_sqft = ((isset($_POST['quote_sqft'])) ? $_POST['quote_sqft'] : '');
   $quote_price = ((isset($_POST['quote_price'])) ? $_POST['quote_price'] : '');
   $quote_link = ((isset($_POST['quote_link'])) ? $_POST['quote_link'] : '');
   $unit_link = ((isset($_POST['unit_link'])) ? $_POST['unit_link'] : '');
   $quote_images = ((isset($_POST['quote_images'])) ? $_POST['quote_images'] : '');

   $property_address = sanitize_text_field($quote_address); // Used for post title
   $np_rz_location__address = $quote_city . ', ' . $quote_state . ', US';
   $np_rz_location__state_country = $quote_state . ', US';
   $np_rz_location__address_line1 = $quote_street;
   $np_rz_location__address_line2 = $quote_city . ', ' . $quote_state . ' ' . $quote_zip;

   // region
   // $city_low = strtolower($quote_city);
   // $state_low = strtolower($quote_state);
   $city_slug = str_replace(' ', '-', preg_replace('/[^ a-z\d]/ui', '', strtolower($quote_city . ' ' . $quote_state)));
   // $city_slug = str_replace(' ', '-', $city_low . ' ' . $state_low);
   $key = array_search($city_slug, array_column($rz_full_regions, 'city_slug'));
   if ($key !== false) {
      $rz_region_id = $rz_full_regions[$key]['term_id'];
   } else {
      $undefined_region = get_term_by('slug', 'undefined', 'rz_regions');
      if ($undefined_region !== false) {
         $rz_region_id = $undefined_region->term_id;
      } else {
         $rz_region_id = insertNewTerm('UNDEFINED REGION', 'undefined');
      }
   }
   $custom_tax = array(
      'rz_regions' => array(
         $rz_region_id
      )
   );

   file_put_contents(LOG_DIR . '/quote-add.log', $quote_id . ' | ' . $quote_title . ' | ' . $quote_description . ' | ' . $quote_address . ' | ' . $quote_street . ' | ' . $quote_city . ' | ' . $quote_state . ' | ' . $quote_zip . ' | ' . $quote_baths . ' | ' . $quote_beds . ' | ' . $quote_sqft . ' | ' . $quote_price . PHP_EOL, FILE_APPEND);
   file_put_contents(LOG_DIR . '/quote-add.log', $quote_images . PHP_EOL, FILE_APPEND);
   file_put_contents(LOG_DIR . '/quote-add.log', $quote_link . ' | ' . $unit_link . PHP_EOL, FILE_APPEND);
} else {
   $response = ['status_code' => 400, 'message' => 'These fields are required: "quote_id", "quote_title" and "quote_address".'];

   file_put_contents(LOG_DIR . '/quote-add.log', ' ERROR > ' . json_encode($response) . PHP_EOL, FILE_APPEND);
   echo json_encode($response);
   exit();
}

$wp_db = new MySQL('wp', 'local');
$query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_postmeta` WHERE meta_key = 'rz_apartment_uri' AND meta_value = '$quote_link' LIMIT 1");
$query->execute();
$total_posts = $query->fetchColumn();

$is_post_exist = false;
if ($total_posts > 0) {
   $is_post_exist = true;
   $query = $wp_db->pdo->prepare("SELECT post_id FROM `wp_postmeta` WHERE meta_key = 'rz_apartment_uri' AND meta_value = ? LIMIT 1");
   $query->execute([$quote_link]);
   $existing_post_id = $query->fetchColumn();
   file_put_contents(LOG_DIR . '/quote-add.log', 'Update existing post > ' . $existing_post_id . PHP_EOL, FILE_APPEND);
} else {
   file_put_contents(LOG_DIR . '/quote-add.log', 'New post will be created! ' . PHP_EOL, FILE_APPEND);
}

$apartment_amenities = [];
$community_amenities = [];

$availability_address = $quote_address;
$availability_address_lc = replace_string($quote_address);

$unit_source = 'quote';

// Image gallery
$is_gallery_empty = true;
$wpImageArray = [];
$decoded_images = json_decode(json_decode('"' . $quote_images . '"', true));
$image_urls = [];
if (is_array($decoded_images) && count($decoded_images) > 0) {
   foreach ($decoded_images as $key => $value) {
      // file_put_contents(LOG_DIR . '/quote-add.log', ' | value - ' . $value->name . ' | ' . str_replace(' ', '_', strtolower($value->name)) . ' | ' . $value->url . ' | ' . $value->extension . PHP_EOL, FILE_APPEND);
      $re = '`^.*/`m';
      $subst = '';
      // IMAGE NAME CHECKING
      $orig_filename = $value->name;
      $orig_fileextension = $value->extension;
      $orig_filename = str_replace(' ', '_', strtolower($orig_filename));
      $filename_path = __DIR__ . '/images' . '/' . $orig_filename . '.' . $orig_fileextension;
      $is_file_exist = file_exists($filename_path);

      $file_get = file_get_contents($value->url);
      if ($file_get !== false) {
         file_put_contents($filename_path, $file_get);
         $moveToWP = moveToWp($filename_path, $property_address, $unit_source);
         if ($moveToWP) {
            $wpImageId = (object)array('id' => (string)$moveToWP);
            array_push($wpImageArray, $wpImageId);
         } else {
            file_put_contents(LOG_DIR . '/quote-add.log', ' | Error image transferring WP - ' . $filename_path . PHP_EOL, FILE_APPEND);
         }
      }
      $image_urls[] = $value->url;
   }
}
$decoded_image_urls = json_encode($image_urls);

// Checking for images of post
$is_gallery_empty = (count($wpImageArray) == 0) ? true : false;
$rz_gallery = json_encode($wpImageArray);
file_put_contents(LOG_DIR . '/quote-add.log', ' | $rz_gallery - ' . $rz_gallery . PHP_EOL, FILE_APPEND);

// Создаем массив данных новой записи
$post_data = array(
   'post_title'    => $quote_address,
   'post_name'     => $quote_address,
   'post_content'  => $quote_description,
   'post_excerpt'  => $quote_description,
   'post_status'   => 'publish',
   'post_author'   => 62,
   'post_type'     => 'rz_listing'
);
if (!$is_post_exist) {
   // Вставляем запись в базу данных
   $main_post_insert_result = wp_insert_post($post_data);
} else {
   $post_data['ID'] = $existing_post_id;
   $main_post_insert_result = wp_update_post($post_data);
}
if ($main_post_insert_result && $main_post_insert_result != 0) {
   wp_set_post_terms($main_post_insert_result, [$rz_region_id], 'rz_regions');

   $new_property_meta = [
      'post_content'                => $quote_description,
      'rz_apartment_uri'            => $quote_link,
      'rz_booking_type'             => 'Request booking',
      'rz_city'                     => $quote_city,
      'rz_listing_type'             => '25769',
      'rz_location'                 => $np_rz_location__address,
      'rz_location__address'        => $np_rz_location__address,
      'rz_location__lat'            => '',
      'rz_location__lng'            => '',
      'rz_post_address1'            => $np_rz_location__address_line1,
      'rz_post_address2'            => $np_rz_location__address_line2,
      'rz_priority'                 => '0',
      'rz_priority_custom'          => '0',
      'rz_priority_selection'       => 'normal',
      'rz_reservation_length_max'   => '0',
      'rz_reservation_length_min'   => '0',
      'rz_state'                    => $quote_state,
      'rz_status'                   => 'Now',
      'rz_street_line_1'            => $np_rz_location__address_line1,
      'rz_street_line_2'            => $np_rz_location__address_line2,
      'rz_zip'                      => $quote_zip,
      'rz_gallery'                  => $rz_gallery,
      'rz_ranking'                  => $rz_ranking,
      'rz_listing_region'           => $region_slug
   ];
   foreach ($new_property_meta as $key => $value) {
      add_post_meta($main_post_insert_result, $key, $value, true) or update_post_meta($main_post_insert_result, $key, $value);
   }

   if (!empty($decoded_premise_services)) {
      foreach ($decoded_premise_services as $key => $value) {
         foreach ($value as $data) {
            $term_id = array_search($data, $apartment_amenities_list);
            if ($term_id) {
               add_post_meta($main_post_insert_result, 'rz_amenities', $term_id, false);
            }
         }
      }
   }
}

// Add post if it has images
$rz_unit_type = 'single';
$rz_search = 1;
$rz_multi_units = [];

if ($main_post_insert_result && $main_post_insert_result != 0) {

   $new_property_meta = [];

   $quote_baths = floatval($quote_baths);
   if ($quote_baths == intval($quote_baths)) {
      $quote_baths = round($quote_baths);
   } else {
      $quote_baths = round($quote_baths, 1);
   }
   $bath_count = trim(preg_replace("/[a-zA-Z]/", "", $quote_baths)); // rz_bathrooms

   $quote_beds = round(floatval($quote_beds));
   $bed_count = trim(preg_replace("/[a-zA-Z]/", "", $quote_beds)); // rz_bed

   $quote_sqft = round(floatval($quote_sqft));
   $sqft = trim(preg_replace("/\D/", "", $quote_sqft)); // rz_sqft

   file_put_contents(LOG_DIR . '/quote-add.log', ' | quote_price - ' . $quote_price . PHP_EOL, FILE_APPEND);

   $listing_price = clearPrice(intval((int)$quote_price));

   file_put_contents(LOG_DIR . '/quote-add.log', ' | listing_price - ' . $listing_price . PHP_EOL, FILE_APPEND);

   $new_property_meta = [
      'post_content'       => $unit_description,
      'rz_apartment_uri'   => $quote_link,
      'rz_bathrooms'       => $bath_count,
      'rz_bed'             => $bed_count,
      'rz_bedroom'         => $bed_count,
      'rz_price'           => $listing_price,
      'rz_sqft'            => $sqft,
      'rz_unit_type'       => $rz_unit_type,
      'rz_search'          => $rz_search,
      'rz_multi_units'     => $rz_multi_units
   ];
   foreach ($new_property_meta as $key => $value) {
      add_post_meta($main_post_insert_result, $key, $value, true) or update_post_meta($main_post_insert_result, $key, $value);
   }
   if ($listing_price != 0) {
      $price_per_day = get_custom_price($main_post_insert_result);
      file_put_contents(LOG_DIR . '/quote-add.log', ' | price_per_day - ' . $price_per_day . PHP_EOL, FILE_APPEND);
      if (!add_post_meta($main_post_insert_result, 'price_per_day', $price_per_day, true)) {
         update_post_meta($main_post_insert_result, 'price_per_day', $price_per_day);
      }
   }
   $response = ['status_code' => 200, 'booking_page_link' => get_permalink($main_post_insert_result)];

   file_put_contents(LOG_DIR . '/quote-add.log', ' > ' . json_encode($response) . PHP_EOL, FILE_APPEND);
   // echo json_encode($response);
   file_put_contents(LOG_DIR . '/quote-add.log', ' > main_post_insert_result ' . $main_post_insert_result . ' > quote_link ' . $quote_link . ' > quote_address ' . $quote_address . ' > quote_street ' . $quote_street . ' > quote_city ' . $quote_city . ' > quote_state ' . $quote_state . ' > quote_zip ' . $quote_zip . ' > quote_description ' . $quote_description . ' > decoded_image_urls ' . $decoded_image_urls . ' > bed_count ' . $bed_count . ' > bath_count ' . $bath_count . ' > quote_price ' . $quote_price . ' > sqft ' . $sqft . PHP_EOL, FILE_APPEND);
   addToParsing($main_post_insert_result, $quote_link, $quote_address, $quote_street, $quote_city, $quote_state, $quote_zip, $quote_description, $decoded_image_urls, $bed_count, $bath_count, $quote_price, $sqft);
} else {
   $response = ['status_code' => 400, 'message' => 'These fields are required: "quote_id","quote_title" and "quote_address"!'];

   file_put_contents(LOG_DIR . '/quote-add.log', ' > ' . json_encode($response) . PHP_EOL, FILE_APPEND);
   echo json_encode($response);
}

file_put_contents(LOG_DIR . '/quote-add.log', '[' . date('Y-m-d H:i:s') . '] End............................................................' . PHP_EOL, FILE_APPEND);

function addToParsing($post_id, $link, $address, $addr_line_1, $city, $state, $zip_code, $property_info, $image_urls, $bed_cnt, $bath_cnt, $listing_price, $sqft, $source = 'HOMI', $type = 'Home', $addr_line_2 = '', $pet_policy = '', $community_amenities = '', $apartment_amenities = '', $listing_comments = '', $virtual_tour_urls = '', $nearby_schools = '', $nearby_colleges  = '', $nearby_rail = '', $nearby_transit = '', $nearby_shopping = '', $nearby_parks = '', $nearby_airports = '', $neighborhood_comments = '', $listing_last_updated = '', $parking = '', $building_features = '', $builiding_office_hours = '', $expences = '', $status = 'Now')
{
   file_put_contents(LOG_DIR . '/quote-add.log', ' > post_id ' . $post_id . ' > link ' . $link . ' > address ' . $address . ' > addr_line_1 ' . $addr_line_1 . ' > city ' . $city . ' > state ' . $state . ' > zip_code ' . $zip_code . ' > property_info ' . $property_info . ' > image_urls ' . $image_urls . ' > bed_cnt ' . $bed_cnt . ' > bath_cnt ' . $bath_cnt . ' > listing_price ' . $listing_price . ' > sqft ' . $sqft . ' > source ' . $source . ' > type ' . $type . ' > addr_line_2 ' . $addr_line_2 . ' > pet_policy ' . $pet_policy . ' > community_amenities ' . $community_amenities . ' > apartment_amenities ' . $apartment_amenities . ' > listing_comments ' . $listing_comments . ' > virtual_tour_urls ' . $virtual_tour_urls . ' > nearby_schools ' . $nearby_schools . ' > nearby_colleges ' . $nearby_colleges . ' > nearby_rail ' . $nearby_rail . ' > nearby_transit ' . $nearby_transit . ' > nearby_shopping ' . $nearby_shopping . ' > nearby_parks ' . $nearby_parks . ' > nearby_airports ' . $nearby_airports . ' > neighborhood_comments ' . $neighborhood_comments . ' > listing_last_updated ' . $listing_last_updated . ' > parking ' . $parking . ' > building_features ' . $building_features . ' > builiding_office_hours ' . $builiding_office_hours . ' > expences ' . $expences . ' > status ' . $status . PHP_EOL, FILE_APPEND);
   $building_desc = $property_info;
   // $listing_last_updated = $last_update = date('Y-m-d H:i:s');
   $parsing_db = new MySQL('parsing', 'local');
   $query = $parsing_db->pdo->prepare("INSERT INTO `properties` (
      `post_id`,
      `link`,
      `source`,
      `address`,
      `type`,
      `addr_line_1`,
      `addr_line_2`,
      `city`,
      `state_cd`,
      `zip5_cd`,
      `building_desc`,
      `property_info`,
      `image_urls`,
      `pet_policy`,
      `on_premise_services`,
      `on_premise_features`,
      `listing_comments`,
      `virtual_tour_urls`,
      `nearby_school`,
      `nearby_colleges`,
      `nearby_rail`,
      `nearby_transit`,
      `nearby_shopping`,
      `nearby_parks`,
      `nearby_airports`,
      `neighborhood_comments`,
      `parking`,
      `building_features`,
      `builiding_office_hours`,
      `expences`
   ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
   try {
      $query->execute([
         $post_id,
         $link,
         $source,
         $address,
         $type,
         $addr_line_1,
         $addr_line_2,
         $city,
         $state,
         $zip_code,
         $building_desc,
         $property_info,
         $image_urls,
         $pet_policy,
         $community_amenities,
         $apartment_amenities,
         $listing_comments,
         $virtual_tour_urls,
         $nearby_schools,
         $nearby_colleges,
         $nearby_rail,
         $nearby_transit,
         $nearby_shopping,
         $nearby_parks,
         $nearby_airports,
         $neighborhood_comments,
         $parking,
         $building_features,
         $builiding_office_hours,
         $expences
      ]);
      $property_id = $parsing_db->pdo->lastInsertId();
   } catch (Exception $e) {
      file_put_contents(LOG_DIR . '/quote-add.log', ' > MoveToWP error - ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
      return false;
   }
   file_put_contents(LOG_DIR . '/quote-add.log', ' > property_id ' . $property_id . PHP_EOL, FILE_APPEND);

   $query = $parsing_db->pdo->prepare("INSERT INTO `availability` (
      `post_id`,
      `property_id`,
      `bedroom_cnt`, 
      `bathroom_cnt`, 
      `listing_price`, 
      `home_size_sq_ft`,
      `status`,
      `image_urls`
   ) VALUES (?,?,?,?,?,?,?,?)");
   try {
      $query->execute([
         $post_id,
         $property_id,
         $bed_cnt,
         $bath_cnt,
         $listing_price,
         $sqft,
         $status,
         $image_urls
      ]);
      $availability_id = $parsing_db->pdo->lastInsertId();
   } catch (Exception $e) {
      file_put_contents(LOG_DIR . '/quote-add.log', ' > MoveToWP error - ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
      return false;
   }
   file_put_contents(LOG_DIR . '/quote-add.log', ' > availability_id ' . $availability_id . PHP_EOL, FILE_APPEND);

   return true;
}

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
/*
function removeExif($incoming_file) {
    $img = new Imagick(realpath($incoming_file));
    $profiles = $img->getImageProfiles("icc", true);
    $img->stripImage();
    if(!empty($profiles)) {
       $img->profileImage("icc", $profiles['icc']);
    }
}
*/
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
      file_put_contents($filename_path, $image_data);
      if ($unit_source == 'rentprogress.com') {
         cropImage($filename_path, 100, 82);
      }
      $wp_filetype = wp_check_filetype($filename_path, null);
      $attachment = array(
         'post_mime_type' => $wp_filetype['type'],
         'post_title' => sanitize_file_name($alt_text),
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
      file_put_contents(LOG_DIR . '/quote-add.log', ' > MoveToWP error - ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
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
   $wp_db = new MySQL('wp', 'local');
   $query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_terms` WHERE `slug` = ? LIMIT 1");
   $query->execute([$slug_string]);
   return $query->fetchColumn();
}

function clearPrice($price)
{
   $price_array = explode('$', trim($price));
   $new_price = end($price_array);
   return preg_replace('/[^0-9]/', '', $new_price);
}
