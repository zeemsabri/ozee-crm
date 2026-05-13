<?php

$fp = fopen('/Users/zeeshansabri/laravel/email-approval-app/storage/app/private/local-database-sync/backups/projects.csv', 'r');
$headers = fgetcsv($fp);
$serviceDetailsIndex = array_search('service_details', $headers);
$count = 0;
while (($row = fgetcsv($fp)) !== false) {
    if (isset($row[$serviceDetailsIndex]) && !empty($row[$serviceDetailsIndex]) && $row[$serviceDetailsIndex] !== '[]') {
        $count++;
    }
}
fclose($fp);
echo "Projects with service_details in CSV: " . $count . "\n";
