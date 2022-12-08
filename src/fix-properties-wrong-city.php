<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';
require_once(realpath('../../wp-load.php'));
// Clear log files
$f = fopen(LOG_DIR . '/fix-cities-post.log', 'w');
fclose($f);
// Start transfer
echo date("Y-m-d H:i:s") . " Start fixing wrong cities ";
file_put_contents(LOG_DIR . '/fix-cities-post.log', '[' . date('Y-m-d H:i:s') . ']  Start >>> ', FILE_APPEND);

$parsing_db = new MySQL('parsing', 'local');

for ($i = 10; $i < 100; $i++) {
    $like_var = '%' . $i . '%';
    $query = $parsing_db->pdo->prepare("SELECT count(*) FROM `properties` WHERE city LIKE ? LIMIT 1");
    $query->execute([$like_var]);
    $total_properties = $query->fetchColumn();
    echo $i . ' | total_properties - ' . ' | ' . $total_properties;
    file_put_contents(LOG_DIR . '/fix-cities-post.log', ' | total_properties - ' . $total_properties, FILE_APPEND);
    $pages = intdiv($total_properties, 1000);
    for ($j = 0; $j <= $pages; $j++) {
        $query = $parsing_db->pdo->prepare("SELECT id, post_id, city, state_cd FROM `properties` WHERE city LIKE ? LIMIT ?,1000");
        $query->execute([$like_var, $j * 1000]);
        $properties = $query->fetchAll();
        foreach ($properties as $property) {
            echo ' | ' . $property->post_id;
            file_put_contents(LOG_DIR . '/fix-cities-post.log', ' | ' . $property->post_id, FILE_APPEND);
        }
    }
    echo PHP_EOL;
}

echo " >>> " . date("Y-m-d H:i:s") . " - End.." . PHP_EOL;
file_put_contents(LOG_DIR . '/fix-cities-post.log', ' >>> [' . date('Y-m-d H:i:s') . '] - End..' . PHP_EOL, FILE_APPEND);
