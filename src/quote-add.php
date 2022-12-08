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

file_put_contents(LOG_DIR . '/quote-add.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ' . PHP_EOL, FILE_APPEND);

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
   $quote_link = ((isset($_POST['quote_link'])) ? $_POST['quote_link'] : '');
   $quote_images = ((isset($_POST['quote_images'])) ? $_POST['quote_images'] : '');

   $property_address = sanitize_text_field($quote_address); // Used for post title
   $np_rz_location__address = $quote_city . ', ' . $quote_state . ', US';
   $np_rz_location__state_country = $quote_state . ', US';
   $np_rz_location__address_line1 = $quote_street;
   $np_rz_location__address_line2 = $quote_city . ', ' . $quote_state . ' ' . $quote_zip;

   // region
   $city_low = strtolower($quote_city);
   $state_low = strtolower($quote_state);
   $region_slug = str_replace(' ', '-', $city_low . ' ' . $state_low);
   $key = array_search($region_slug, array_column($rz_full_regions, 'slug'));
   $rz_region_id = $rz_full_regions[$key]['term_id'];
   $custom_tax = array(
      'rz_regions' => array(
         $rz_region_id
      )
   );
   file_put_contents(LOG_DIR . '/quote-add.log', $quote_id . ' > ' . $quote_title . ' > ' . $quote_description . PHP_EOL, FILE_APPEND);
   file_put_contents(LOG_DIR . '/quote-add.log', $quote_address . ' > ' . $quote_street . ' > ' . $quote_city . ' > ' . $quote_state . PHP_EOL, FILE_APPEND);
   file_put_contents(LOG_DIR . '/quote-add.log', $quote_zip . ' > ' . $quote_baths . ' > ' . $quote_beds . ' > ' . $quote_sqft . ' > ' . $quote_images . PHP_EOL, FILE_APPEND);
} else {
   $response = ['status_code' => 400, 'message' => 'These fields are required: "quote_id","quote_title" and "quote_address"!'];
   file_put_contents(LOG_DIR . '/quote-add.log', ' > ' . json_encode($response) . PHP_EOL, FILE_APPEND);
   echo json_encode($response);
   exit();
}

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
         // file_put_contents(LOG_DIR . '/quote-add.log', ' >>> [' . $term_id . '] - ' . $term_name . ' | ' . $region_city_up . ' | ' . $term_slug . PHP_EOL, FILE_APPEND);
      }
   }
}

$apartment_amenities = [];
$community_amenities = [];

$availability_address = $quote_address;
$availability_address_lc = replace_string($quote_address);

$unit_source = 'quote';

// Image gallery
$is_gallery_empty = true;
$wpImageArray = [];
$decoded_image_urls = json_decode(json_decode('"' . $quote_images . '"', true));
file_put_contents(LOG_DIR . '/quote-add.log', ' | type of decoded_image_urls - ' . $decoded_image_urls . PHP_EOL, FILE_APPEND);
if (is_array($decoded_image_urls) && count($decoded_image_urls) > 0) {
   foreach ($decoded_image_urls as $key => $value) {
      file_put_contents(LOG_DIR . '/quote-add.log', ' | value - ' . $value->name . ' | ' . str_replace(' ', '_', strtolower($value->name)) . ' | ' . $value->url . ' | ' . $value->extension . PHP_EOL, FILE_APPEND);
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
   }
   foreach ($decoded_image_urls as $key => $value) {
   }
}
file_put_contents(LOG_DIR . '/quote-add.log', ' | WP Image ID - END' . PHP_EOL, FILE_APPEND);

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
   'post_status'   => 'draft',
   'post_author'   => 62,
   'post_type'     => 'rz_listing'
);
// Вставляем запись в базу данных
$main_post_insert_result = wp_insert_post($post_data);
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
if (!$is_gallery_empty) {
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

      $listing_price = clearPrice(100);

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
   }
} else {
   file_put_contents(LOG_DIR . '/quote-add.log', ' | No images for post | ' . PHP_EOL, FILE_APPEND);
}

file_put_contents(LOG_DIR . '/quote-add.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);

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
