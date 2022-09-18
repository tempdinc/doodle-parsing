<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

   require_once __DIR__ . '/src/bootstrap.php';
   
   $dbhost = env('MYSQL_HOST', '127.0.0.1');
   $dbuser = env('MYSQL_USER', 'root');
   $dbpass = env('MYSQL_PASSWORD', '');
   $dbname = env('MYSQL_DBNAME', 'parsing');
   // $conn = mysqli_connect($dbhost, $dbuser, $dbpass);

   // Create connection
   $conn = mysqli_connect($dbhost, $dbuser, $dbpass);
   // Check connection
   if (!$conn) {
      die("Connection failed: " . mysqli_connect_error()) . PHP_EOL;
   }

   // Create database
   $sql = "CREATE DATABASE parsing";
   if (mysqli_query($conn, $sql)) {
      echo "Database `parsing` created successfully | ";
   } else {
      echo "Error creating database: " . mysqli_error($conn) . PHP_EOL;
   }
 
   $sql = 'CREATE TABLE `properties` (
      `id` bigint NOT NULL AUTO_INCREMENT,
      `post_id` int DEFAULT NULL,
      `address` varchar(256) DEFAULT NULL,
      `type` varchar(32) DEFAULT NULL,
      `lease_length` varchar(256) DEFAULT NULL,
      `latitude` varchar(64) DEFAULT NULL,
      `longitude` varchar(64) DEFAULT NULL,
      `contact_type` varchar(64) DEFAULT NULL,
      `contact_person` varchar(256) DEFAULT NULL,
      `contact_company` varchar(256) DEFAULT NULL,
      `contact_phone` varchar(32) DEFAULT NULL,
      `contact_email` varchar(128) DEFAULT NULL,
      `building_units` varchar(64) DEFAULT NULL,
      `addr_line_1` varchar(128) DEFAULT NULL,
      `addr_line_2` varchar(128) DEFAULT NULL,
      `city` varchar(64) DEFAULT NULL,
      `state_cd` char(2) DEFAULT NULL,
      `zip5_cd` varchar(32) DEFAULT NULL,
      `image_urls` text,
      `listing_comments` text,
      `virtual_tour_urls` text,
      `pet_policy` text,
      `outdoor_space` varchar(128) DEFAULT NULL,
      `on_premise_services` text,
      `walk_score` varchar(128) DEFAULT NULL,
      `transit_score` varchar(128) DEFAULT NULL,
      `nearby_school` text,
      `nearby_colleges` text,
      `nearby_rail` text,
      `nearby_transit` text,
      `nearby_shopping` text,
      `nearby_parks` text,
      `nearby_airports` text,
      `neighborhood_comments` text,
      `listing_last_updated` varchar(32) DEFAULT NULL,
      `utilities_included` varchar(256) DEFAULT NULL,
      `building_security` varchar(128) DEFAULT NULL,
      `living_space` varchar(128) DEFAULT NULL,
      `on_premise_features` text,
      `student_features` varchar(256) DEFAULT NULL,
      `kitchen` varchar(256) DEFAULT NULL,
      `parking` text,
      `property_info` text,
      `building_features` text,
      `subdivision` varchar(32) DEFAULT NULL,
      `building_desc` text,
      `building_name` varchar(128) DEFAULT NULL,
      `builiding_office_hours` text,
      `link` varchar(255) NOT NULL,
      `expences` text,
      `is_deleted` tinyint(1) NOT NULL DEFAULT "0",
      `last_update` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

   if(!mysqli_select_db($conn,'parsing')) {
      echo "Database NOT SELECTED" . PHP_EOL;
   }

   $retval = mysqli_query( $sql, $conn );
   
   if (mysqli_query($conn, $sql)) {
      echo "Table `property` created successfully | ";
   } else {
      echo "Error creating table `property`: " . mysqli_error($conn) . PHP_EOL;
   }

   $sql = 'CREATE TABLE `availability` (
      `id` bigint NOT NULL AUTO_INCREMENT,
      `property_id` bigint NOT NULL,
      `bedroom_cnt` varchar(32) DEFAULT NULL,
      `bathroom_cnt` varchar(32) DEFAULT NULL,
      `listing_price` varchar(32) DEFAULT NULL,
      `home_size_sq_ft` varchar(32) DEFAULT NULL,
      `lease_length` varchar(32) DEFAULT NULL,
      `status` varchar(32) DEFAULT NULL,
      `image_urls` text,
      `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;';

   if(!mysqli_select_db($conn,'parsing')) {
      echo "Database NOT SELECTED" . PHP_EOL;
   }

   $retval = mysqli_query( $sql, $conn );
   
   if (mysqli_query($conn, $sql)) {
      echo "Table `availability` created successfully" . PHP_EOL;
   } else {
      echo "Error creating table `availability`: " . mysqli_error($conn) . PHP_EOL;
   }

   mysqli_close($conn);
?>
