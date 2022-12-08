<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
// require_once(realpath('../../wp-load.php'));

$rz_listing_type = '380';
$rz_ranking = '4';

// Start transfer
echo date("Y-m-d H:i:s") . " Start adding ranking";
file_put_contents(LOG_DIR . '/add-ranking.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

//Query our MySQL table
$wp_db = new MySQL('wp', 'local');
$query = $wp_db->pdo->prepare("SELECT post_id FROM `wp_postmeta` WHERE `meta_key` = 'rz_listing_type' AND `meta_value` = ?");
$query->execute([$rz_listing_type]);
$rows = $query->fetchAll();

foreach ($rows as $row) {
    $post_id = $row->post_id;
    // var_dump($post_id);
    $query = $wp_db->pdo->prepare("SELECT meta_value FROM `wp_postmeta` WHERE `meta_key` = 'rz_ranking' AND `post_id` = ?");
    $query->execute([$post_id]);
    $new_rows = $query->fetchAll();
    $is_duplicate = false;
    echo " \033[31mPost: " . $post_id . "\033[0m";
    file_put_contents(LOG_DIR . '/add-ranking.log', ' Post: ' . $post_id, FILE_APPEND);
    foreach ($new_rows as $new_row) {
        echo ' Existing ranking - ' . $new_row->meta_value;
        file_put_contents(LOG_DIR . '/add-ranking.log', ' Existing ranking: ' . $new_row->meta_value, FILE_APPEND);
        $is_duplicate = true;
    }
    if (!$is_duplicate) {
        $query = $wp_db->pdo->prepare("INSERT INTO `wp_postmeta` (`post_id`,`meta_key`,`meta_value`) VALUES (?,?,?)");
        $query->execute([$post_id, 'rz_ranking', $rz_ranking]);
        echo " \033[31m Added ranking\033[0m | ";
        file_put_contents(LOG_DIR . '/add-ranking.log', ' Added ranking ', FILE_APPEND);
    }
}


echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/add-ranking.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
