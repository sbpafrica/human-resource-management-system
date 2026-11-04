<?php
$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/../new update.zip') === true) {
    echo "--- Samples from 'build/' ---\n";
    $count = 0;
    for ($i = 0; $i < $zip->numFiles && $count < 5; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_starts_with($name, 'build/')) {
            echo "  " . $name . "\n";
            $count++;
        }
    }

    echo "--- Samples from 'public/build/' ---\n";
    $count = 0;
    for ($i = 0; $i < $zip->numFiles && $count < 5; $i++) {
        $name = $zip->getNameIndex($i);
        if (str_starts_with($name, 'public/build/')) {
            echo "  " . $name . "\n";
            $count++;
        }
    }
}

