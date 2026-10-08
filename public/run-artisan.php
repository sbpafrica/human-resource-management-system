<?php
// TEMPORARY SCRIPT — DELETE THIS FILE IMMEDIATELY AFTER USE
// Visit this URL once, then delete the file from the server.

define('LARAVEL_START', microtime(true));

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

$commands = [
    ['route:clear',   []],
    ['config:clear',  []],
    ['cache:clear',   []],
    ['view:clear',    []],
    ['optimize',      []],
];

echo '<pre style="font-family:monospace; background:#111; color:#0f0; padding:20px;">';
echo "Running Laravel artisan commands...\n\n";

foreach ($commands as [$cmd, $args]) {
    echo "▶ php artisan {$cmd}\n";
    try {
        $exitCode = $kernel->call($cmd, $args);
        echo "  ✓ Done (exit code: {$exitCode})\n\n";
    } catch (\Throwable $e) {
        echo "  ✗ Error: " . $e->getMessage() . "\n\n";
    }
}

echo "composer dump-autoload cannot run here — do it via cPanel File Manager if needed.\n\n";
echo "✅ All done. DELETE this file from the server now!\n";
echo '</pre>';
