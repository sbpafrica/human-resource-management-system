<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\File;

$controllerDir = app_path('Http/Controllers');
$files = File::allFiles($controllerDir);

$templates = [];

foreach ($files as $file) {
    $content = file_get_contents($file->getRealPath());
    if (str_contains($content, 'function downloadTemplate')) {
        $cName = $file->getFilenameWithoutExtension();
        // Extract $headers array if defined
        if (preg_match('/\$headers\s*=\s*\[(.*?)\];/s', $content, $m)) {
            $rawHeaders = $m[1];
            // Match quoted strings
            preg_match_all("/['\"]([^'\"]+)['\"]/", $rawHeaders, $hMatches);
            if (!empty($hMatches[1])) {
                $templates[$cName] = $hMatches[1];
            }
        }
    }
}

echo "Found " . count($templates) . " downloadTemplate header definitions:\n\n";
foreach ($templates as $cName => $headers) {
    echo "=== {$cName} (" . count($headers) . " headers) ===\n";
    echo implode(', ', array_slice($headers, 0, 20)) . (count($headers) > 20 ? ' ... and ' . (count($headers) - 20) . ' more' : '') . "\n\n";
}

