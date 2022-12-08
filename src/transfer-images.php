<?php

$counter_tasks = 0;
$counter_errors = 0;

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));
/*
//Fork settings
pcntl_async_signals(true);

pcntl_signal(SIGTERM, 'signalHandler'); // Termination ('kill' was called)
pcntl_signal(SIGHUP, 'signalHandler'); // Terminal log-out
pcntl_signal(SIGINT, 'signalHandler'); // Interrupted (Ctrl-C is pressed)
*/
// Getting all parsed
$parsing_db = new MySQL('parsing', 'local');
// $new_properties = $parsing_db->getAllNewRecords('properties');
// var_dump($new_properties);
$all_availability = $parsing_db->getAvailability();
echo 'Transfer images - ' . count($all_availability);
file_put_contents(LOG_DIR . '/transfer-images.log', 'New units - ' . count($all_availability), FILE_APPEND);

foreach ($all_availability as $availability) {
  $wpImageArray = [];
  $image_urls = $availability->av_image_urls;
  // echo $availability->av_id;
  $decoded_image_urls = json_decode($image_urls);
  // var_dump($decoded_image_urls);
  if (is_array($decoded_image_urls) && count($decoded_image_urls) > 0) {
    foreach ($decoded_image_urls as $key => $value) {
      // echo $value . PHP_EOL;
      $re = '`^.*/`m';
      $subst = '';
      $filename = __DIR__ . '/images/' . preg_replace($re, $subst, parse_url($value, PHP_URL_PATH));
      // echo $filename . PHP_EOL;
      file_put_contents($filename, file_get_contents($value));
      echo $filename;
      // $wpImageId = (object)array('id' => (string)moveToWp($filename));
      cropImage($filename, 100, 82);
      $wpImageId = (object)array('id' => (string)moveToWp($filename));
      array_push($wpImageArray, $wpImageId);
      // echo file_put_contents($filename, file_get_contents($value));
    }
  }
  $rz_gallery = json_encode($wpImageArray);
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
    imagepng($im2, $image);
    imagedestroy($im2);
  }
  imagedestroy($im);
}

function moveToWp($image_url)
{
  // $image_url = 'adress img';

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
  // update_post_meta( $attach_id, '_wp_attachment_image_alt', $image_name );
  return $attach_id;
}
