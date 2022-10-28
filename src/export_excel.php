<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

use App\Classes\MySQL;

require_once __DIR__ . '/bootstrap.php';

//Query our MySQL table
$parsing_db = new MySQL('parsing', 'local');

$query = $parsing_db->pdo->prepare("SELECT * FROM `availability` LEFT JOIN `properties` ON availability.property_id = properties.id");
$query->execute();
$rows = $query->fetchAll();

// require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$counter = 0;
foreach($rows as $row) {
    $counter++;
    $cell = 'A'.$counter;
    $sheet->setCellValue($cell, var_export($row->address, true));
    // if($counter > 2) exit;
}

$writer = new Xlsx($spreadsheet);
$writer->save('export_parsigng.xlsx');