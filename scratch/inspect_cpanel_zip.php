<?php
$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/../cpanel_update_with_build.zip') === true) {
    for ($i = 0; $i < 10; $i++) {
        echo $zip->getNameIndex($i) . "\n";
    }
}

