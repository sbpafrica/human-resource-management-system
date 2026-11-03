<?php
$files = glob(__DIR__ . '/../app/Http/Controllers/*PayController.php');
$files = array_merge($files, [__DIR__ . '/../app/Http/Controllers/SalaryPaymentController.php']);

foreach ($files as $f) {
    $code = file_get_contents($f);
    if (strpos($code, 'function downloadTemplate') !== false && strpos($code, 'tempnam') !== false) {
        preg_match('/(public function downloadTemplate\([^\)]*\)\s*\{[\s\S]*?)(return response\(\)->download\([^;]+;\s*\})/i', $code, $m);
        if ($m) {
            // grab the last 10 lines of the method
            $lines = explode("\n", $m[0]);
            $tail = array_slice($lines, -8);
            echo "=== " . basename($f) . " ===\n" . implode("\n", $tail) . "\n\n";
        } else {
            echo "NO REGEX MATCH in " . basename($f) . "\n";
        }
    }
}

