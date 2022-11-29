<?php
$test = '[{\"url\":\"https://drive.google.com/uc?id=1_3TKcpF6ykoeE_NCNvi-DkzLcIpBnxIJ&export=download\",\"name\":\"Unit Property Image QTE-2022-10-13\",\"extension\":\"jpg\"},{\"url\":\"https://drive.google.com/uc?id=1brQn8mpZ6OKNscB6mrX0pJkETA8xRLWa&export=download\",\"name\":\"Additional Unit Image QTE1-2022-10-13\",\"extension\":\"jpg\"},{\"url\":\"https://drive.google.com/uc?id=11OFm-jZ0uSAZ3sthSI_hfFyVChgjdWPN&export=download\",\"name\":\"Additional Unit Image QTE2-2022-10-13\",\"extension\":\"jpg\"}]';
$decoded_image_urls = json_decode(json_decode('"' . $test . '"', true));

foreach ($decoded_image_urls as $key => $value) {
   // $value = json_decode($value);
   // file_put_contents(LOG_DIR . '/quote-add.log', ' | value - ' . $value->name . ' | ' . str_replace(' ', '_', strtolower($value->name)) . ' | ' . $value->url . ' | ' . $value->extension . PHP_EOL, FILE_APPEND);
   echo ' | value - ' . $value->name . ' | ' . str_replace(' ', '_', strtolower($value->name)) . ' | ' . $value->url . ' | ' . $value->extension . PHP_EOL;
   $re = '`^.*/`m';
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