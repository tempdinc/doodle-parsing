<?php
/*
Fix & remove all wrong data in parsed units
*******************************************
1. Getting all post ids from properties table
2. Getting all post ids from availability table
3. Getting post ids with listing type 25769 | Calculating array diffs
4. Removing availability records with NON existing post_ids
5. Removing properties records with NON existing post_ids
6. Fixing & clearing availability records: bedroom_cnt, bathroom_cnt, listing_price, home_size_sq_ft
7. Removing availability records with post_id IS NULL && NOT Available statuses
8. Removing availability records with post_id IS NULL && WRONG listing_price & WRONG home_size_sq_ft
9. Count availability records with WRONG listing_price & WRONG home_size_sq_ft
10. Getting property_ids && post_ids FROM availability table with post_id = 0 & WRONG listing_price & WRONG home_size_sq_ft & image_urls  = ''
11. Geting property ids FROM properties table WITH post_id = 0 OR image_url = '' OR on_premise_features = ''
12. Getting post_ids FROM property table && availability table with WRONG listing_price & WRONG home_size_sq_ft | Remove property records && availability record with WRONG listing_price & WRONG home_size_sq_ft
13. Removing posts
*/
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));
// Clear log files
$f = fopen(LOG_DIR . '/remove-posts-total.log', 'w');
fclose($f);

echo date("Y-m-d H:i:s") . " Start fix && remove" . PHP_EOL;
file_put_contents(LOG_DIR . '/remove-posts-total.log', '[' . date('Y-m-d H:i:s') . '] Start >>>' . PHP_EOL, FILE_APPEND);

require_once __DIR__ . '/env-status_availability.php';
$parsing_db = new MySQL('parsing', 'local');


$posts_to_remove = [];
$existing_posts = [];
$availability_existing_posts = [];
$availability_existing_posts_to_remove = [];
$property_existing_posts = [];
$property_existing_posts_to_remove = [];


// 1. Getting all post ids from properties table
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 1. Getting all post ids from properties table' . PHP_EOL, FILE_APPEND);
$query = $parsing_db->pdo->prepare("SELECT count(*) FROM `properties` WHERE `post_id` IS NOT NULL AND `post_id` != '' LIMIT 1");
$query->execute();
$total_properties = $query->fetchColumn();
file_put_contents(LOG_DIR . '/remove-posts-total.log', "Total properties WHERE post_id IS NOT NULL && post_id != '' - " . $total_properties . PHP_EOL, FILE_APPEND);
$pages = intdiv($total_properties, 100);
for ($i = 0; $i <= $pages; $i++) {
    $start = $i * 100;
    try {
        $query = $parsing_db->pdo->prepare("SELECT `post_id` FROM `properties` WHERE `post_id` IS NOT NULL AND `post_id` != '' LIMIT $start,100");
        $query->execute();
        $rows = $query->fetchAll(PDO::FETCH_COLUMN, 0);
        $property_existing_posts = array_merge($property_existing_posts, $rows);
    } catch (\Exception $ex) {
        die($ex->getMessage());
    }
}
$property_existing_posts = array_unique($property_existing_posts);
sort($property_existing_posts);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Properties existing posts unique - ' . count($property_existing_posts) . PHP_EOL, FILE_APPEND);
$existing_posts = array_merge($existing_posts, $property_existing_posts);


// 2. Getting all post ids from availability table
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 2. Getting all post ids from availability table' . PHP_EOL, FILE_APPEND);
$query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE `post_id` IS NOT NULL AND `post_id` != '' LIMIT 1");
$query->execute();
$total_availabilities = $query->fetchColumn();
file_put_contents(LOG_DIR . '/remove-posts-total.log', "Total availabilities WHERE post_id IS NOT NULL && post_id != '' - " . $total_availabilities . PHP_EOL, FILE_APPEND);
$pages = intdiv($total_availabilities, 100);
for ($i = 0; $i <= $pages; $i++) {
    $start = $i * 100;
    try {
        $query = $parsing_db->pdo->prepare("SELECT `post_id` FROM `availability` WHERE `post_id` IS NOT NULL AND `post_id` != '' LIMIT $start,100");
        $query->execute();
        $rows = $query->fetchAll(PDO::FETCH_COLUMN, 0);
        $availability_existing_posts = array_merge($availability_existing_posts, $rows);
    } catch (\Exception $ex) {
        die($ex->getMessage());
    }
}
$availability_existing_posts = array_unique($availability_existing_posts);
sort($availability_existing_posts);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Availability existing posts unique - ' . count($availability_existing_posts) . PHP_EOL, FILE_APPEND);


$existing_posts = array_unique(array_merge($existing_posts, $availability_existing_posts));
sort($existing_posts);
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . 'Total (availability & property) existing posts unique - ' . count($existing_posts) . PHP_EOL, FILE_APPEND);


// 3. Getting post ids with listing type 25769 | Calculating array diffs
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 3. Getting post ids with listing type 25769 | Calculating array diffs' . PHP_EOL, FILE_APPEND);
$availability_existing_posts_to_remove = $availability_existing_posts;
$property_existing_posts_to_remove = $property_existing_posts;
$listing_type = '25769';
$wp_db = new MySQL('wp', 'local');
$total_posts = $wp_db->countPostsRZListing($listing_type);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Total post ids with listing type ' . $listing_type . ': ' . $total_posts . PHP_EOL, FILE_APPEND);
$limit = 100;
$pages = intdiv($total_posts, $limit);
$total_removed_posts = 0;
$removed_posts = 0;
for ($i = 0; $i <= $pages; $i++) {
    $start = $i * $limit;
    $sql_query = "SELECT wp.id FROM `wp_posts` wp LEFT JOIN `wp_postmeta` wppm ON wp.id = wppm.post_id WHERE wp.post_type = 'rz_listing' AND wppm.meta_key = 'rz_listing_type' AND wppm.meta_value = ? ORDER BY wp.id ASC LIMIT ?,?";
    $query = $wp_db->pdo->prepare($sql_query);
    $query->execute([$listing_type, $start, $limit]);
    $rows = $query->fetchAll(PDO::FETCH_COLUMN, 0);

    $posts_to_remove = array_merge(array_diff($rows, $existing_posts), $posts_to_remove);

    $availability_existing_posts_to_remove = array_diff($availability_existing_posts_to_remove, $rows);

    $property_existing_posts_to_remove = array_diff($property_existing_posts_to_remove, $rows);
}
$posts_to_remove = array_unique($posts_to_remove);
sort($posts_to_remove);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Posts to remove from wp_posts table - ' . count($posts_to_remove) . PHP_EOL, FILE_APPEND);


// 4. Removing availability records with NON existing post_ids
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 4. Removing availability records with NON existing post_ids' . PHP_EOL, FILE_APPEND);
$availability_existing_posts_to_remove = array_unique($availability_existing_posts_to_remove);
sort($availability_existing_posts_to_remove);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Posts to remove from availability table - ' . count($availability_existing_posts_to_remove) . PHP_EOL, FILE_APPEND);
foreach ($availability_existing_posts_to_remove as $post_id) {
    $sql_query = "DELETE FROM `availability` WHERE `post_id` = ?";
    $query = $parsing_db->pdo->prepare($sql_query);
    $query->execute([$post_id]);
    $rows = $query->fetchAll();
}


// 5. Removing properties records with NON existing post_ids
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 5. Removing properties records with NON existing post_ids' . PHP_EOL, FILE_APPEND);
$property_existing_posts_to_remove = array_unique($property_existing_posts_to_remove);
sort($property_existing_posts_to_remove);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Posts to remove from properties table - ' . count($property_existing_posts_to_remove) . PHP_EOL, FILE_APPEND);
foreach ($property_existing_posts_to_remove as $post_id) {
    $sql_query = "DELETE FROM `properties` WHERE `post_id` = ?";
    $query = $parsing_db->pdo->prepare($sql_query);
    $query->execute([$post_id]);
    $rows = $query->fetchAll();
}


// 6. Fixing & clearing availability records: bedroom_cnt, bathroom_cnt, listing_price, home_size_sq_ft
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 6. Fixing & clearing availability records: bedroom_cnt, bathroom_cnt, listing_price, home_size_sq_ft' . PHP_EOL, FILE_APPEND);
$query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE 1=1 LIMIT 1");
$query->execute();
$total_availabilities = $query->fetchColumn();
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Total availabilities - ' . $total_availabilities . PHP_EOL, FILE_APPEND);
$pages = intdiv($total_availabilities, 100);
for ($i = 0; $i <= $pages; $i++) {
    $start = $i * 100;
    $query = $parsing_db->pdo->prepare("SELECT * FROM `availability` WHERE 1=1 ORDER BY id DESC LIMIT $start,100");
    $query->execute();
    $rows = $query->fetchAll();

    foreach ($rows as $row) {
        $bed_cnt = $row->bedroom_cnt;
        $bath_cnt = $row->bathroom_cnt;
        $listing_price = $row->listing_price;
        $sqft = $row->home_size_sq_ft;
        $bed_cnt = (trim(preg_replace("/[a-zA-Z\/]/", "", $bed_cnt)) === '' || trim(preg_replace("/[a-zA-Z\/]/", "", $bed_cnt)) === NULL) ? 0 : trim(preg_replace("/[a-zA-Z]/", "", $bed_cnt));
        $bath_cnt = trim(preg_replace("/[a-zA-Z\/]/", "", $bath_cnt));
        $listing_price = trim(preg_replace("/[a-zA-Z$,\/]/", "", $listing_price));
        $sqft = trim(preg_replace("/[a-zA-Z,\/]/", "", $sqft));
        $query = $parsing_db->pdo->prepare("UPDATE `availability` SET bedroom_cnt = ?, bathroom_cnt = ?, listing_price = ?, home_size_sq_ft = ? WHERE id = ?");
        $query->execute([$bed_cnt, $bath_cnt, $listing_price, $sqft, $row->id]);
    }
}


// 7. Removing availability records with post_id IS NULL && NOT Available statuses
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 7. Removing availability records with post_id IS NULL && NOT Available statuses' . PHP_EOL, FILE_APPEND);
$query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE 1=1 LIMIT 1");
$query->execute();
$total_availabilities = $query->fetchColumn();
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Total availabilities - ' . $total_availabilities . PHP_EOL, FILE_APPEND);
$sql_query = "DELETE FROM `availability` WHERE (`post_id` IS NULL AND `status` IS NULL) OR (`post_id` IS NULL AND `status` = '')";
foreach ($status_availability as $status) {
    $sql_query .= " OR (`post_id` IS NULL AND `status` != '$status')";
}
file_put_contents(LOG_DIR . '/remove-posts-total.log', $sql_query . PHP_EOL, FILE_APPEND);
$query = $parsing_db->pdo->prepare($sql_query);
$query->execute();
$total_availabilities = $query->fetchColumn();


// 8. Removing availability records with post_id IS NULL && WRONG listing_price & WRONG home_size_sq_ft
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 8. Removing availability records with post_id IS NULL && WRONG listing_price & WRONG home_size_sq_ft' . PHP_EOL, FILE_APPEND);
$query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE 1=1 LIMIT 1");
$query->execute();
$total_availabilities = $query->fetchColumn();
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Total availabilities - ' . $total_availabilities . PHP_EOL, FILE_APPEND);
$sql_query = "DELETE FROM `availability` WHERE (`post_id` IS NULL AND `listing_price` IS NULL) OR (`post_id` IS NULL AND `listing_price` LIKE '%person%') OR (`post_id` IS NULL AND `listing_price` LIKE '%call%') OR (`post_id` IS NULL AND `home_size_sq_ft` IS NULL) OR (`post_id` IS NULL AND `home_size_sq_ft` = '')";
file_put_contents(LOG_DIR . '/remove-posts-total.log', $sql_query . PHP_EOL, FILE_APPEND);
$query = $parsing_db->pdo->prepare($sql_query);
$query->execute();
$total_availabilities = $query->fetchColumn();


$query = $parsing_db->pdo->prepare("SELECT count(*) FROM `availability` WHERE 1=1 LIMIT 1");
$query->execute();
$total_availabilities = $query->fetchColumn();
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . 'Total availabilities - ' . $total_availabilities . PHP_EOL, FILE_APPEND);


// 9. Count availability records with WRONG listing_price & WRONG home_size_sq_ft
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 9. Count availability records with WRONG listing_price & WRONG home_size_sq_ft' . PHP_EOL, FILE_APPEND);
$sql_query = "SELECT count(*) FROM `availability` WHERE `listing_price` IS NULL OR `listing_price` LIKE '%person%' OR `listing_price` LIKE '%call%' OR `listing_price` = '' OR `home_size_sq_ft` = '' OR `home_size_sq_ft` IS NULL OR `image_urls` = '' OR `image_urls` IS NULL LIMIT 1";
$query = $parsing_db->pdo->prepare($sql_query);
$query->execute();
$total_availabilities = $query->fetchColumn();
file_put_contents(LOG_DIR . '/remove-posts-total.log', "Total availability records with WRONG listing_price & WRONG home_size_sq_ft & image_urls  = ' - " . $total_availabilities . PHP_EOL, FILE_APPEND);


// 10. Getting property_ids && post_ids FROM availability table with post_id = 0 & WRONG listing_price & WRONG home_size_sq_ft & image_urls  = ''
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . "[" . date('Y-m-d H:i:s') . "] 10. Getting property_ids && post_ids FROM availability table with post_id = 0 & WRONG listing_price & WRONG home_size_sq_ft & image_urls  = ''" . PHP_EOL, FILE_APPEND);
$properties_ro_remove = [];
$pages = intdiv($total_availabilities, 100);
for ($i = 0; $i <= $pages; $i++) {
    $start = $i * 100;
    $query = $parsing_db->pdo->prepare("SELECT property_id, post_id FROM `availability` WHERE `post_id` = 0 OR `post_id` = '0' OR `listing_price` IS NULL OR `listing_price` LIKE '%person%' OR `listing_price` LIKE '%call%' OR `listing_price` = '' OR `home_size_sq_ft` = '' OR `home_size_sq_ft` IS NULL OR `image_urls` = '' OR `image_urls` IS NULL LIMIT $start,100");
    $query->execute();
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);

    $properties = array_column($rows, 'property_id');
    $properties = array_unique($properties);
    sort($properties);
    $properties_ro_remove = array_merge($properties, $properties_ro_remove);

    $posts_id = array_column($rows, 'post_id');
    $posts_id = array_unique($posts_id);
    sort($posts_id);
    $posts_to_remove = array_merge($posts_id, $posts_to_remove);
}
$properties_ro_remove = array_unique($properties_ro_remove);
sort($properties_ro_remove);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Properties to remove - ' . count($properties_ro_remove) . PHP_EOL, FILE_APPEND);
$posts_to_remove = array_unique($posts_to_remove);
sort($posts_to_remove);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Posts to remove - ' . count($posts_to_remove) . PHP_EOL, FILE_APPEND);


// 11. Geting property ids FROM properties table WITH post_id = 0 OR image_url = '' OR on_premise_features = ''
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . "[" . date('Y-m-d H:i:s') . "] 11. Geting property ids FROM properties table WITH post_id = 0 OR image_url = '' OR on_premise_features = ''" . PHP_EOL, FILE_APPEND);
$query = $parsing_db->pdo->prepare("SELECT id FROM `properties` WHERE `post_id` = 0 OR `post_id` = '0' OR `image_urls` = '' OR `image_urls` IS NULL OR `on_premise_features` = '' OR `on_premise_features` IS NULL");
$query->execute();
$rows = $query->fetchAll(PDO::FETCH_COLUMN, 0);
$properties_ro_remove = array_merge($rows, $properties_ro_remove);
$properties_ro_remove = array_unique($properties_ro_remove);
sort($properties_ro_remove);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Properties to remove - ' . count($properties_ro_remove) . PHP_EOL, FILE_APPEND);


// 12. Getting post_ids FROM property table && availability table with WRONG listing_price & WRONG home_size_sq_ft | Remove property records && availability record with WRONG listing_price & WRONG home_size_sq_ft
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 12. Getting post_ids FROM property table && availability table with WRONG listing_price & WRONG home_size_sq_ft | Remove property records && availability record with WRONG listing_price & WRONG home_size_sq_ft' . PHP_EOL, FILE_APPEND);
foreach ($properties_ro_remove as $property_id) {
    // Todo Добавить поиск постов в попертиз и добавить это же в transfer-data-new
    $query = $parsing_db->pdo->prepare("SELECT `post_id` FROM `properties` WHERE `id` = $property_id");
    $query->execute();
    $rows = $query->fetchAll(PDO::FETCH_COLUMN, 0);
    $posts_to_remove = array_merge($rows, $posts_to_remove);
    $query = $parsing_db->pdo->prepare("SELECT `post_id` FROM `availability` WHERE `property_id` = $property_id");
    $query->execute();
    $rows = $query->fetchAll(PDO::FETCH_COLUMN, 0);
    $posts_to_remove = array_merge($rows, $posts_to_remove);
    $query = $parsing_db->pdo->prepare("DELETE FROM `availability` WHERE `property_id` = $property_id");
    $query->execute();
    $rows = $query->fetchAll();
    $query = $parsing_db->pdo->prepare("DELETE FROM `properties` WHERE `id` = $property_id");
    $query->execute();
    $rows = $query->fetchAll();
}
$posts_to_remove = array_unique($posts_to_remove);
sort($posts_to_remove);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Posts to remove - ' . count($posts_to_remove) . PHP_EOL, FILE_APPEND);


// 13. Removing posts
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] 13. Removing posts' . PHP_EOL, FILE_APPEND);
$posts_removed_count = 0;
$posts_notremoved = [];
$posts_notremoved_count = 0;
foreach ($posts_to_remove as $post_to_remove) {
    $result = wp_delete_post($post_to_remove, true);
    if ($result !== false && $result !== NULL) {
        $posts_removed_count++;
    } else {
        $posts_notremoved_count++;
        $posts_notremoved[] = $post_to_remove;
    }
}
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Posts succesfully removed - ' . $posts_removed_count . PHP_EOL, FILE_APPEND);
file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Posts not removed (ERRORS) - ' . $posts_notremoved_count . PHP_EOL, FILE_APPEND);
if ($posts_notremoved_count > 0) {
    file_put_contents(LOG_DIR . '/remove-posts-total.log', 'Posts not removed - ' . print_r($posts_notremoved, true) . PHP_EOL, FILE_APPEND);
}


echo date("Y-m-d H:i:s") . " End fix && remove" . PHP_EOL;
file_put_contents(LOG_DIR . '/remove-posts-total.log', PHP_EOL . '[' . date('Y-m-d H:i:s') . '] End >>>', FILE_APPEND);
