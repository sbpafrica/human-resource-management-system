<?php
$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/../new update.zip') === true) {
    $topDirs = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $parts = explode('/', $name);
        $topDirs[$parts[0]] = true;
    }
    echo "Top-level entries in 'new update.zip':\n";
    print_r(array_keys($topDirs));
}

