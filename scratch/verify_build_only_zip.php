<?php
$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/../build_only.zip') === true) {
    $dirs = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $parts = explode('/', $name);
        $dirs[$parts[0]] = ($dirs[$parts[0]] ?? 0) + 1;
    }
    echo "Files per top-level folder in 'build_only.zip':\n";
    print_r($dirs);
}
