<?php
$content = file_get_contents(__DIR__ . '/../public/build/manifest.json');
$json = json_decode($content, true);
if ($json === null) {
    echo "JSON ERROR: " . json_last_error_msg() . "\n";
} else {
    echo "Valid JSON! Keys count: " . count($json) . "\n";
    echo "resources/css/app.css: " . json_encode($json['resources/css/app.css'] ?? 'NOT FOUND') . "\n";
    echo "resources/js/app.tsx: " . json_encode($json['resources/js/app.tsx'] ?? 'NOT FOUND') . "\n";
}

