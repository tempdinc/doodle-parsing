<?php
$test_data = '[{\"url\":\"https://drive.google.com/uc?id=1_3TKcpF6ykoeE_NCNvi-DkzLcIpBnxIJ&export=download\",\"name\":\"Unit Property Image QTE-2022-10-13\",\"extension\":\"jpg\"},{\"url\":\"https://drive.google.com/uc?id=1brQn8mpZ6OKNscB6mrX0pJkETA8xRLWa&export=download\",\"name\":\"Additional Unit Image QTE1-2022-10-13\",\"extension\":\"jpg\"},{\"url\":\"https://drive.google.com/uc?id=11OFm-jZ0uSAZ3sthSI_hfFyVChgjdWPN&export=download\",\"name\":\"Additional Unit Image QTE2-2022-10-13\",\"extension\":\"jpg\"}]';
$decoded_image_urls = json_decode(json_decode('"' . $test_data . '"', true));
// var_dump($decoded_image_urls);
$image_urls = [];
foreach ($decoded_image_urls as $decoded_image_url) {
   $image_urls[] = $decoded_image_url->url;
}
// var_dump($image_urls);
$quote_link = "https://airtable.com/appRKFaWwZ0mHgpsu/tblZ4hGs0Mo4LYkbs/viwlklpaU9ld8DduV/recwLpXXHlo74Pn3h";
$test_urls = json_encode($image_urls);

use App\Classes\MySQL;

require_once 'bootstrap.php';

$post_id       = '343434';
$link          = 'https://tempd.com';
$source        = 'HOMI';
$address       = '12345, USA';
$type          = 'Home';
$addr_line_1   = 'Home street, 1';
$addr_line_2   = '';
$city          = 'Houston';
$state         = 'TX';
$zip_сode      = '12345';
$building_desc = '';
$property_info = 'Testing description';
$image_urls    = $test_urls;
$pet_policy    = '';
$community_amenities    = '';
$apartment_amenities    = '';
$listing_comments       = '';
$virtual_tour_urls      = '';
$nearby_schools         = '';
$nearby_colleges        = '';
$nearby_rail            = '';
$nearby_transit         = '';
$nearby_shopping        = '';
$nearby_parks           = '';
$nearby_airports        = '';
$neighborhood_comments  = '';
$listing_last_updated   = date('Y-m-d H:i:s');
$parking                = '';
$building_features      = '';
$builiding_office_hours = '';
$expences               = '';
$last_update            = date('Y-m-d H:i:s');

$parsing_db = new MySQL('parsing', 'local');
/*
$query = $parsing_db->pdo->prepare("INSERT INTO `properties` (
   'id',
   'post_id',
   'is_deleted',
   'link',
   'source',
   'address',
   'type',
   'lease_length',
   'latitude',
   'longitude',
   'contact_phone',
   'contact_email',
   'contact_type',
   'contact_person',
   'contact_company',
   'building_units',
   'addr_line_1',
   'addr_line_2',
   'city',
   'state_cd',
   'zip5_cd',
   'building_name',
   'building_desc',
   'property_info',
   'image_urls',
   'pet_policy',
   'on_premise_services',
   'on_premise_features',
   'listing_comments',
   'virtual_tour_urls',
   'outdoor_space',
   'walk_score',
   'transit_score',
   'nearby_school',
   'nearby_colleges',
   'nearby_rail',
   'nearby_transit',
   'nearby_shopping',
   'nearby_parks',
   'nearby_airports',
   'neighborhood_comments',
   'listing_last_updated',
   'utilities_included',
   'building_security',
   'living_space',
   'student_features',
   'kitchen',
   'parking',
   'building_features',
   'subdivision',
   'builiding_office_hours',
   'expences',
   'last_update',
   'date_added'
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
*/
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
   `listing_last_updated`,
   `parking`,
   `building_features`,
   `builiding_office_hours`,
   `expences`,
   `last_update`
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
   $listing_last_updated,
   $parking,
   $building_features,
   $builiding_office_hours,
   $expences,
   $last_update
]);
$property_id = $parsing_db->pdo->lastInsertId();
var_dump($property_id);

$bed_cnt = '3';
$bath_cnt = '2';
$listing_price = '$3000.0';
$sqft = '250 sqft';
$status = 'Now';
/*
$query = $parsing_db->pdo->prepare("INSERT INTO `availability` (
   'id',
   'post_id',
   'is_deleted',
   'property_id',
   'bedroom_cnt', 
   'bathroom_cnt', 
   'listing_price', 
   'home_size_sq_ft',
   'lease_length',
   'status',
   'image_urls',
   'date_added'
) VALUES (?,?,?,?,?,?,?,?,?)");
*/
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
file_put_contents(LOG_DIR . '/quote-add.log', ' > availability_id ' . $availability_id . PHP_EOL, FILE_APPEND);
var_dump($availability_id);
exit();

$is_post_exist = false;
if ($total_posts > 0) {
   $is_post_exist = true;
   $query = $wp_db->pdo->prepare("SELECT post_id FROM 'wp_postmeta' WHERE 'meta_key' = 'rz_apartment_uri' AND 'meta_value' = ? LIMIT 1");
   $query->execute([$quote_link]);
   $existing_post_id = $query->fetchColumn();
}

var_dump($is_post_exist);
exit();

foreach ($decoded_image_urls as $key => $value) {
   // $value = json_decode($value);
   // file_put_contents(LOG_DIR . '/quote-add.log', ' | value - ' . $value->name . ' | ' . str_replace(' ', '_', strtolower($value->name)) . ' | ' . $value->url . ' | ' . $value->extension . PHP_EOL, FILE_APPEND);
   echo ' | value - ' . $value->name . ' | ' . str_replace(' ', '_', strtolower($value->name)) . ' | ' . $value->url . ' | ' . $value->extension . PHP_EOL;
   $re = `'^.*/'m`;
   $subst = '';
   // IMAGE NAME CHECKING
   $orig_filename = $value->name;
   // echo ' | orig_full_filename - ' . $orig_full_filename;
   $orig_fileextension = $value->extension;
   // echo ' | orig_fileextension - ' . $orig_fileextension; 
   $orig_filename = str_replace(' ', '_', strtolower($orig_filename));
   // echo ' | ' . $orig_filename;
   $filename_path = __DIR__ . '/images' . '/' . $orig_filename . '.' . $orig_fileextension;
   $is_file_exist = file_exists($filename_path);

   $file_get = file_get_contents($value->url);
   if ($file_get !== false) {
      file_put_contents($filename_path, $file_get);
   }
}
