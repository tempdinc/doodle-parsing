<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));
// Clear log files
$f = fopen(LOG_DIR . '/fix-postmeta-sqft.log', 'w');
fclose($f);

// Start transfer
echo date("Y-m-d H:i:s") . " Start publishing WP posts";
file_put_contents(LOG_DIR . '/fix-postmeta-sqft.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

$wp_db = new MySQL('wp', 'local');
$query = $wp_db->pdo->prepare("SELECT COUNT(*) FROM `wp_postmeta` WHERE (`meta_key` = 'rz_sqft' AND `meta_value` LIKE '%-%') OR (`meta_key` = 'rz_sqft' AND `meta_value` REGEXP '^.{5,100}$') OR (`meta_key` = 'rz_sqft' AND `meta_value` = '') OR (`meta_key` = 'rz_sqft' AND `meta_value` IS NULL) LIMIT 1");
$query->execute();
$total_rz_listing = $query->fetchColumn();
$pages = intdiv($total_rz_listing, 500);
file_put_contents(LOG_DIR . '/fix-postmeta-sqft.log', ' Total posts - ' . $total_rz_listing . PHP_EOL, FILE_APPEND);
echo 'Total posts - ' . $total_rz_listing . PHP_EOL;
$listing_type = '25769';
$counter_wrong_posts = 0;
$counter_clear_posts = 0;
$counter_clear_post_content_meta = 0;
$removed_posts_counter = 0;
for ($i = 0; $i <= $pages + 1; $i++) {
    echo ' | page #' . $i . PHP_EOL;
    $start = $i * 500 - $removed_posts_counter;
    $removed_posts_counter = 0;
    $query = $wp_db->pdo->prepare("SELECT * FROM `wp_postmeta` WHERE (`meta_key` = 'rz_sqft' AND `meta_value` LIKE '%-%') OR (`meta_key` = 'rz_sqft' AND `meta_value` REGEXP '^.{5,100}$') OR (`meta_key` = 'rz_sqft' AND `meta_value` = '') OR (`meta_key` = 'rz_sqft' AND `meta_value` IS NULL) LIMIT $start,500");
    $query->execute();
    $total_postmeta = $query->fetchAll();
    foreach ($total_postmeta as $postmeta) {
        $post_id = $postmeta->post_id;
        $post_in_property = check_property($post_id);
        if ($post_in_property !== false) {
            $new_sqft_meta = get_availability_sqft_by_property_id($post_in_property);
            if($new_sqft_meta !== false) {
                delete_post_meta($post_id, 'rz_sqft');
                $adding_meta = add_post_meta($post_id, 'rz_sqft', $new_sqft_meta, true);
            } else {
                wp_delete_post( $post_id, true );
                $removed_posts_counter++;
            }
        }
        $post_in_availability = check_availability($post_id);
        if ($post_in_availability !== false) {
            $new_sqft_meta = get_availability_sqft_by_availability_id($post_in_property);
            delete_post_meta($post_id, 'rz_sqft');
            $adding_meta = add_post_meta($post_id, 'rz_sqft', $new_sqft_meta, true);
        }
        file_put_contents(LOG_DIR . '/fix-postmeta-sqft.log', '[' . date('Y-m-d H:i:s') . ']  Post ID: ' . $post_id . ' > post in property:' . $post_in_property . ' > post in availability:' . $post_in_availability . ' > post sqft: ' . $new_sqft_meta . ' > $adding_meta: ' . $adding_meta . PHP_EOL, FILE_APPEND);
    }
}
echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-postmeta-sqft.log', '[' . date('Y-m-d H:i:s') . ']  END >>> Total wrong posts: ' . $counter_wrong_posts . ' | Total clear posts: ' . $counter_clear_posts . ' | Total clear post_content meta: ' . $counter_clear_post_content_meta . PHP_EOL, FILE_APPEND);

function check_property($post_id)
{
    $response = false;
    $parsing_db = new MySQL('parsing', 'local');
    $query = $parsing_db->pdo->prepare("SELECT COUNT(*) FROM `properties` WHERE `post_id` = ? LIMIT 1");
    $query->execute([$post_id]);
    $property_count = $query->fetchColumn();
    if ($property_count > 0) {
        $query = $parsing_db->pdo->prepare("SELECT id FROM `properties` WHERE `post_id` = ?");
        $query->execute([$post_id]);
        $response = $query->fetchColumn();
    }
    return $response;
}

function get_availability_sqft_by_property_id($property_id)
{
    $parsing_db = new MySQL('parsing', 'local');
    // $query = $parsing_db->pdo->prepare("SELECT home_size_sq_ft FROM `availability` WHERE `property_id` = ?");
    $query = $parsing_db->pdo->prepare(
        "SELECT home_size_sq_ft FROM `availability` WHERE (property_id = ? AND status = 'Available Now' AND is_deleted IS NULL) OR (property_id = ? AND status = 'Move In Ready' AND is_deleted IS NULL) OR (property_id = ? AND status = 'Move-In Ready' AND is_deleted IS NULL) OR (property_id = ? AND status = 'Now' AND is_deleted IS NULL)"
    );
    $query->execute([$property_id, $property_id, $property_id, $property_id]);
    $sqfts = $query->fetchAll(PDO::FETCH_COLUMN, 0);
    $sqft_array = [];
    foreach ($sqfts as $sqft) {
        $sqft = str_replace(['-', '–', '—', '―'], '–', $sqft);
        if (trim(preg_replace("/\D+/", "", $sqft)) !== '' && trim(preg_replace("/\D+/", "", $sqft)) !== 0 && trim(preg_replace("/\D+/", "", $sqft)) !== NULL) {
            if (stripos($sqft, '–') === false) {
                $sqft_array[] = intval(trim(preg_replace("/\D+/", "", $sqft)));
            } else {
                $sqft_temp_array = explode('–', $sqft);
                foreach ($sqft_temp_array as $sqft_temp) {
                    if (trim(preg_replace("/\D+/", "", $sqft_temp)) !== '' && trim(preg_replace("/\D+/", "", $sqft_temp)) !== 0 && trim(preg_replace("/\D+/", "", $sqft_temp)) !== NULL) {
                        $sqft_array[] = intval(trim(preg_replace("/\D+/", "", $sqft_temp)));
                    }
                }
            }
        }
    }
    $sqft_array = array_unique($sqft_array, SORT_NUMERIC);
    sort($sqft_array, SORT_NUMERIC);
    file_put_contents(LOG_DIR . '/fix-postmeta-sqft.log', print_r($sqft_array, true), FILE_APPEND);
    if(!empty($sqft_array)) {
        $sqft_min = min($sqft_array);
        $sqft_max = max($sqft_array);
        return ($sqft_min == $sqft_max) ? $sqft_min : $sqft_min . '-' . $sqft_max;
    } else {
        
        return false;
    }
}

function check_availability($post_id)
{
    $response = false;
    $parsing_db = new MySQL('parsing', 'local');
    $query = $parsing_db->pdo->prepare("SELECT COUNT(*) FROM `availability` WHERE `post_id` = ? LIMIT 1");
    $query->execute([$post_id]);
    $availability_count = $query->fetchColumn();
    if ($availability_count > 0) {
        $query = $parsing_db->pdo->prepare("SELECT id FROM `availability` WHERE `post_id` = ?");
        $query->execute([$post_id]);
        $response = $query->fetchColumn();
    }
    return $response;
}

function get_availability_sqft_by_availability_id($availability_id)
{
    $parsing_db = new MySQL('parsing', 'local');
    // $query = $parsing_db->pdo->prepare("SELECT home_size_sq_ft FROM `availability` WHERE `property_id` = ?");
    $query = $parsing_db->pdo->prepare(
        "SELECT home_size_sq_ft FROM `availability` WHERE id = ?"
    );
    $query->execute([$availability_id]);
    $sqfts = $query->fetchAll(PDO::FETCH_COLUMN, 0);
    $sqft_array = [];
    foreach ($sqfts as $sqft) {
        $sqft = str_replace(['-', '–', '—', '―'], '–', $sqft);
        if (trim(preg_replace("/\D+/", "", $sqft)) !== '' && trim(preg_replace("/\D+/", "", $sqft)) !== 0 && trim(preg_replace("/\D+/", "", $sqft)) !== NULL) {
            if (stripos($sqft, '–') === false) {
                $sqft_array[] = intval(trim(preg_replace("/\D+/", "", $sqft)));
            } else {
                $sqft_temp_array = explode('–', $sqft);
                foreach ($sqft_temp_array as $sqft_temp) {
                    if (trim(preg_replace("/\D+/", "", $sqft_temp)) !== '' && trim(preg_replace("/\D+/", "", $sqft_temp)) !== 0 && trim(preg_replace("/\D+/", "", $sqft_temp)) !== NULL) {
                        $sqft_array[] = intval(trim(preg_replace("/\D+/", "", $sqft_temp)));
                    }
                }
            }
        }
    }
    $sqft_array = array_unique($sqft_array, SORT_NUMERIC);
    sort($sqft_array, SORT_NUMERIC);
    file_put_contents(LOG_DIR . '/fix-postmeta-sqft.log', print_r($sqft_array, true), FILE_APPEND);
    if(!empty($sqft_array)) {
        $sqft_min = min($sqft_array);
        $sqft_max = max($sqft_array);
        return ($sqft_min == $sqft_max) ? $sqft_min : $sqft_min . '-' . $sqft_max;
    } else {
        return false;
    }
}

function wrongProperty($propertyId)
{
    // file_put_contents(LOG_DIR . '/fix-postmeta-sqft.log', ' Wrong property: ' . $propertyId, FILE_APPEND);
    $parsing_db = new MySQL('parsing', 'local');
    try {
        $query = $parsing_db->pdo->prepare("UPDATE `properties` SET post_id = ? WHERE id = ?");
        $query->execute([0, $propertyId]);
    } catch (\Exception $ex) {
        return $ex->getMessage();
    }
    try {
        $query = $parsing_db->pdo->prepare("UPDATE `availability` SET post_id = ? WHERE property_id = ?");
        $query->execute([0, $propertyId]);
    } catch (\Exception $ex) {
        return $ex->getMessage();
    }
}

function updateProperty($post_id)
{
    // file_put_contents(LOG_DIR . '/fix-postmeta-sqft.log', ' Update property where post ID: ' . $post_id . PHP_EOL, FILE_APPEND);
    $parsing_db = new MySQL('parsing', 'local');
    try {
        $query = $parsing_db->pdo->prepare("UPDATE `properties` SET post_id = 0 WHERE post_id = ?");
        $query->execute([$post_id]);
    } catch (\Exception $ex) {
        return $ex->getMessage();
    }
    try {
        $query = $parsing_db->pdo->prepare("UPDATE `availability` SET post_id = 0 WHERE postId = ?");
        $query->execute([$post_id]);
    } catch (\Exception $ex) {
        return $ex->getMessage();
    }
}
