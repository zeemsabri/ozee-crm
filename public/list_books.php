<?php

// Set headers to allow cross-origin requests from any domain.
// This is crucial for your React app running on a different port/domain.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept');
header('Content-Type: application/json');

$booksDirectory = __DIR__.'/books/';

$epubFiles = [];

// Check if the directory exists and is readable
if (is_dir($booksDirectory) && $handle = opendir($booksDirectory)) {
    while (false !== ($file = readdir($handle))) {
        // Only include files that end with .epub and are not system files
        if (pathinfo($file, PATHINFO_EXTENSION) === 'epub') {
            $epubFiles[] = $file;
        }
    }
    closedir($handle);
}

// Return the list of files as a JSON array
echo json_encode($epubFiles);
