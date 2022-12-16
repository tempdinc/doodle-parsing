<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));

// Start transfer
echo date("Y-m-d H:i:s") . " Start publishing WP posts";
file_put_contents(LOG_DIR . '/fix-post-description.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

$wp_db = new MySQL('wp', 'local');
$query = $wp_db->pdo->prepare("SELECT count(*) FROM `wp_posts` WHERE `post_type` = ? LIMIT 1");
$query->execute(['rz_listing']);
$total_rz_listing = $query->fetchColumn();
$pages = intdiv($total_rz_listing, 500);
file_put_contents(LOG_DIR . '/fix-post-description.log', ' Total posts - ' . $total_rz_listing . PHP_EOL, FILE_APPEND);
echo 'Total posts - ' . $total_rz_listing . PHP_EOL;
$listing_type = '25769';
$counter_wrong_posts = 0;
$counter_clear_posts = 0;
$removed_posts_counter = 0;
for ($i = 0; $i <= $pages + 1; $i++) {
    echo ' | page #' . $i . PHP_EOL;
    unset($posts);
    $start = $i * 500 - $removed_posts_counter;
    $removed_posts_counter = 0;
    $posts = $wp_db->getAllPostsContentRZListing($listing_type, $start, 500);
    foreach ($posts as $post) {
        $description_response = check_description($post->post_content);
        if ($description_response == 'remove') {
            echo $post->id . ' | ';
            updateProperty($post->id);
            wp_delete_post($post->id, true);
            // file_put_contents(LOG_DIR . '/fix-post-description.log', ' | Wrong post ID - ' . $post->id, FILE_APPEND);
            $counter_wrong_posts++;
            $removed_posts_counter++;
        } elseif ($description_response == 'clear') {
            // var_dump($post->post_content);
            $new_post_content = clear_description($post->post_content);
            $new_post_excerpt = wp_trim_excerpt($new_post_content);
            // Создаем массив данных
            $my_post = [
                'ID' => $post->id,
                'post_content' => $new_post_content,
                'post_excerpt' => $new_post_excerpt,
                'comment_status' => 'closed'
            ];
            // Обновляем данные в БД
            wp_update_post(wp_slash($my_post));
            delete_post_meta($post->id, 'post_content');
            // file_put_contents(LOG_DIR . '/fix-post-description.log', ' | Content & excerpt updated post ID - ' . $post->id, FILE_APPEND);
            $counter_clear_posts++;
        }
        /*
        exit();
        $city_meta = $wp_db->getAllMetaByPostByMetakey($post->id, 'post_content');
        if (is_array($city_meta)) {
            $city_meta = $city_meta[0];
        } else {
            $city_meta = $city_meta;
        }
        $state_meta = $wp_db->getAllMetaByPostByMetakey($post->id, 'rz_state');
        if (is_array($state_meta)) {
            $state_meta = $state_meta[0];
        } else {
            $state_meta = $state_meta;
        }
        file_put_contents(LOG_DIR . '/fix-post-description.log',  ' | ' . round(memory_get_usage() / 1048576, 2) . '' . ' MB', FILE_APPEND);
        file_put_contents(LOG_DIR . '/fix-post-description.log', PHP_EOL, FILE_APPEND);
        */
    }
}
echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-post-description.log', '[' . date('Y-m-d H:i:s') . ']  END >>> Total wrong posts: ' . $counter_wrong_posts . ' | Total clear posts: ' . $counter_clear_posts . PHP_EOL, FILE_APPEND);

function check_description($string)
{
    $response = 'do_nothing';
    $search_strings = array('Blueground', 'American Homes 4 Rent');

    foreach ($search_strings as $search_string) {
        if (stripos($string, $search_string) !== false) {
            $response = 'remove';
            return $response;
        }
    }

    $string = str_replace('.com', '-com', $string);
    $search_strings = array('price', 'rentprogress', 'Progress Residential', 'Blueground', 'American Homes 4 Rent', 'zillow', 'apartments-com', 'hotpads', 'contact', 'phone', '@', 'Progress', 'call', 'Application Coordinator', 'Managed By', 'Website', 'Email', 'email', 'Leasing Specialists', 'credit', 'click');
    $array_strings = explode('.', $string);
    foreach ($array_strings as $key => $value) {
        foreach ($search_strings as $search_string) {
            if (stripos($value, $search_string) !== false) {
                $response = 'clear';
                return $response;
            }
        }
    }

    return $response;
}

function clear_description($string)
{
    $string = str_replace('.com', '-com', $string);
    $search_strings = array('price', 'rentprogress', 'Progress Residential', 'Blueground', 'American Homes 4 Rent', 'zillow', 'apartments-com', 'hotpads', 'contact', 'phone', '@', 'Progress', 'call', 'Application Coordinator', 'Managed By', 'Website', 'Email', 'email', 'Leasing Specialists', 'credit', 'click');
    $array_strings = explode('.', $string);
    foreach ($array_strings as $key => $value) {
        foreach ($search_strings as $search_string) {
            if (stripos($value, $search_string) !== false) {
                unset($array_strings[$key]);
            }
        }
    }
    return implode('.', $array_strings);
    // $vowels = array(" ", ".", ",", "$", ";", ":", "%", "*", "(", ")", "+", "=", "_", "|", "#", "@", "!", "`", "~", "^", "&");
    // return mb_strtolower(str_replace($vowels, '-', $string));
}

function wrongProperty($propertyId)
{
    // file_put_contents(LOG_DIR . '/fix-post-description.log', ' Wrong property: ' . $propertyId, FILE_APPEND);
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
    // file_put_contents(LOG_DIR . '/fix-post-description.log', ' Update property where post ID: ' . $post_id . PHP_EOL, FILE_APPEND);
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
