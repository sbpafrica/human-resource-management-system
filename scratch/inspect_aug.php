<?php
$files = glob(__DIR__ . '/../app/Http/Controllers/*PayController.php');

foreach ($files as $f) {
    $code = file_get_contents($f);
    if (strpos($code, 'augmentTemplate') !== false) {
        preg_match('/(augmentTemplate[^\n]+)/', $code, $m);
        echo basename($f) . ": " . $m[1] . "\n";
    } else {
        echo basename($f) . ": NO augmentTemplate\n";
    }
}

