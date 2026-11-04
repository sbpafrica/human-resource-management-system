<?php
$zip = new ZipArchive();
if ($zip->open(__DIR__ . '/../new update.zip') === true) {
    // Read manifest.json from build/manifest.json
    $m1 = $zip->getFromName('build/manifest.json');
    $d1 = json_decode($m1, true);
    echo "build/manifest.json valid? " . ($d1 !== null ? "YES (" . count($d1) . " keys)" : "NO: " . json_last_error_msg()) . "\n";
    echo "  resources/css/app.css: " . json_encode($d1['resources/css/app.css'] ?? null) . "\n";

    // Read manifest.json from public/build/manifest.json
    $m2 = $zip->getFromName('public/build/manifest.json');
    $d2 = json_decode($m2, true);
    echo "public/build/manifest.json valid? " . ($d2 !== null ? "YES (" . count($d2) . " keys)" : "NO: " . json_last_error_msg()) . "\n";
    echo "  resources/css/app.css: " . json_encode($d2['resources/css/app.css'] ?? null) . "\n";
}

