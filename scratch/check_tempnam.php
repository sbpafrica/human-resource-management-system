<?php
$files = glob(__DIR__ . '/../app/Http/Controllers/*PayController.php');
$files = array_merge($files, [__DIR__ . '/../app/Http/Controllers/SalaryPaymentController.php']);

foreach ($files as $f) {
    $code = file_get_contents($f);
    if (strpos($code, 'function downloadTemplate') !== false && strpos($code, 'tempnam') !== false) {
        echo basename($f) . " still uses tempnam\n";
    }
}

