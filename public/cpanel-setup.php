<?php

// public/cpanel-setup.php
// Standalone web-based deployment and maintenance script for cPanel hosting

define('LARAVEL_START', microtime(true));

$key = $_GET['key'] ?? $_GET['secret'] ?? '';
$expectedKey = 'sbp-deploy-2026';

// Read .env if available to check custom key
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    if (preg_match('/^SETUP_KEY=(.*)$/m', $envContent, $m)) {
        $expectedKey = trim($m[1], " \t\n\r\0\x0B\"'");
    }
}

if (empty($key) || $key !== $expectedKey) {
    http_response_code(403);
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Unauthorized</title><style>body{font-family:sans-serif;padding:40px;background:#f8fafc;color:#1e293b;text-align:center}.box{background:#fff;padding:30px;max-width:500px;margin:auto;border-radius:10px;box-shadow:0 4px 6px -1px rgb(0 0 0 / 0.1)}h2{color:#ef4444}code{background:#f1f5f9;padding:4px 8px;border-radius:4px;font-size:14px}</style></head><body><div class="box"><h2>Access Denied</h2><p>Please pass the valid setup key in the URL:</p><p><code>?key=sbp-deploy-2026</code></p></div></body></html>';
    exit;
}

$results = [];

// Step 0: Remove stale cache files in bootstrap/cache
$cacheFiles = [
    __DIR__ . '/../bootstrap/cache/routes-v7.php',
    __DIR__ . '/../bootstrap/cache/routes.php',
    __DIR__ . '/../bootstrap/cache/config.php',
    __DIR__ . '/../bootstrap/cache/events.php',
];

$clearedFiles = [];
foreach ($cacheFiles as $file) {
    if (file_exists($file)) {
        @unlink($file);
        $clearedFiles[] = basename($file);
    }
}
if (!empty($clearedFiles)) {
    $results[] = '<b>[Cache Files Removed]</b> ' . implode(', ', $clearedFiles);
}

// Step 1: Boot Laravel
$autoloadFile = __DIR__ . '/../vendor/autoload.php';
$appFile = __DIR__ . '/../bootstrap/app.php';

if (!file_exists($autoloadFile) || !file_exists($appFile)) {
    $results[] = '<b>[Error]</b> vendor/autoload.php or bootstrap/app.php missing. Please ensure all files are extracted.';
} else {
    require $autoloadFile;
    $app = require_once $appFile;
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    // Step 2: Storage Link
    $publicStorage = __DIR__ . '/storage';
    $targetStorage = __DIR__ . '/../storage/app/public';

    if (!file_exists($publicStorage)) {
        try {
            if (function_exists('symlink')) {
                @symlink($targetStorage, $publicStorage);
            }
            if (!file_exists($publicStorage)) {
                \Illuminate\Support\Facades\Artisan::call('storage:link');
            }
            $results[] = '<b>[Storage Link]</b> Public storage symlink created successfully.';
        } catch (\Throwable $e) {
            $results[] = '<b>[Storage Link Note]</b> ' . htmlspecialchars($e->getMessage());
        }
    } else {
        $results[] = '<b>[Storage Link]</b> Public storage link already exists.';
    }

    // Step 3: Run Migrations
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $migOutput = \Illuminate\Support\Facades\Artisan::output();
        $results[] = '<b>[Database Migrations]</b><pre>' . htmlspecialchars($migOutput ?: 'Nothing to migrate.') . '</pre>';
    } catch (\Throwable $e) {
        $results[] = '<b>[Database Migration Error]</b> ' . htmlspecialchars($e->getMessage());
    }

    // Step 4: Clear & Refresh All Caches
    try {
        \Illuminate\Support\Facades\Artisan::call('optimize:clear');
        $optOutput = \Illuminate\Support\Facades\Artisan::output();
        $results[] = '<b>[Cache Optimization]</b><pre>' . htmlspecialchars($optOutput) . '</pre>';
    } catch (\Throwable $e) {
        $results[] = '<b>[Cache Optimization Error]</b> ' . htmlspecialchars($e->getMessage());
    }
}

// Render HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>cPanel Deployment Setup | SBP HRMS</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 30px; background: #f8fafc; color: #1e293b; margin: 0; }
        .card { background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); max-width: 800px; margin: 0 auto; }
        h2 { color: #0284c7; margin-top: 0; }
        pre { background: #0f172a; color: #38bdf8; padding: 15px; border-radius: 8px; overflow-x: auto; font-size: 13px; }
        .success { color: #16a34a; font-weight: 600; font-size: 16px; margin-bottom: 20px; }
        hr { border: none; border-top: 1px solid #e2e8f0; margin: 20px 0; }
        .btn { display: inline-block; margin-top: 20px; background: #0284c7; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 600; }
        .btn:hover { background: #0369a1; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🚀 SBP Africa HRMS — cPanel Deployment Setup</h2>
        <p class="success">✓ Operations finished successfully!</p>
        <?php echo implode('<hr>', $results); ?>
        <p><a href="/login" class="btn">Proceed to Login →</a></p>
    </div>
</body>
</html>
