<?php
$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/../new update.zip') === true) {
    for ($i = 0; $i < 10; $i++) {
        echo $zip->getNameIndex($i) . "\n";
    }
}

