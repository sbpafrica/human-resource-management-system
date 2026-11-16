<?php
/**
 * Standalone Emergency Disk Cleaner & Unpacker for SBP Africa HRMS
 * Usage: https://hrs.sbpafrica.group/clean-and-unpack.php?key=sbp-deploy-2026
 */

$key = $_GET['key'] ?? '';
if ($key !== 'sbp-deploy-2026') {
    http_response_code(403);
    die('<h3>Unauthorized</h3><p>Access key required: <code>?key=sbp-deploy-2026</code></p>');
}

header('Content-Type: text/html; charset=utf-8');

$projectRoot = dirname(__DIR__);
$userHome = dirname($projectRoot);

echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Disk Cleanup & Unpack</title>';
echo '<style>body{font-family:monospace;padding:20px;background:#0f172a;color:#38bdf8;line-height:1.6}h2{color:#4ade80}b{color:#fff}</style></head><body>';
echo "<h2>🧹 SBP Africa HRMS — Emergency Disk Cleaner & Asset Unpacker</h2>";

$cleanedCount = 0;
$cleanedBytes = 0;

// 1. Clean cPanel trash
$trashPaths = [
    $userHome . '/.trash',
    '/home/u1710176/.trash',
];
foreach ($trashPaths as $tp) {
    if (is_dir($tp)) {
        try {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tp, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $fi) {
                if ($fi->isFile()) {
                    $cleanedBytes += $fi->getSize();
                    @unlink($fi->getRealPath());
                    $cleanedCount++;
                } elseif ($fi->isDir()) {
                    @rmdir($fi->getRealPath());
                }
            }
            echo "<div>[Trash Cleaned] Emptied <code>{$tp}</code></div>";
        } catch (Throwable $e) {
            echo "<div>[Trash Warning] " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}

// 2. Clean old dumps, gz, stale zips
$rootFiles = @scandir($projectRoot) ?: [];
foreach ($rootFiles as $f) {
    if ($f === '.' || $f === '..') continue;
    $fp = $projectRoot . '/' . $f;
    if (is_file($fp)) {
        if (preg_match('/\.(sql|sql\.gz|tar\.gz|bak)$/i', $f) ||
            (preg_match('/\.zip$/i', $f) && !in_array($f, ['cpanel_update_with_build.zip', 'cpanel_update_today.zip']))) {
            $sz = @filesize($fp) ?: 0;
            if (@unlink($fp)) {
                $cleanedBytes += $sz;
                $cleanedCount++;
                echo "<div>[Deleted Stale File] <code>{$f}</code> (" . round($sz / (1024 * 1024), 2) . " MB)</div>";
            }
        }
    }
}

// 3. Clean temporary .part and .tmp files
$tempDirs = [
    $projectRoot . '/storage/app/temp',
    __DIR__ . '/storage',
];
foreach ($tempDirs as $td) {
    if (is_dir($td)) {
        foreach (@scandir($td) ?: [] as $f) {
            if ($f === '.' || $f === '..') continue;
            $fp = $td . '/' . $f;
            if (is_file($fp) && (preg_match('/\.part$/i', $f) || preg_match('/\.tmp$/i', $f) || preg_match('/^leaves_.*\.zip$/i', $f))) {
                $sz = @filesize($fp) ?: 0;
                if (@unlink($fp)) {
                    $cleanedBytes += $sz;
                    $cleanedCount++;
                    echo "<div>[Deleted Temp] <code>{$f}</code></div>";
                }
            }
        }
    }
}

// 4. Clean obsolete build assets
$manifestPath = __DIR__ . '/build/manifest.json';
if (file_exists($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
    $activeFiles = [];
    foreach ($manifest as $entry) {
        if (!empty($entry['file'])) {
            $activeFiles[basename($entry['file'])] = true;
        }
        if (!empty($entry['css'])) {
            foreach ($entry['css'] as $c) {
                $activeFiles[basename($c)] = true;
            }
        }
    }
    $assetsDir = __DIR__ . '/build/assets';
    if (is_dir($assetsDir)) {
        foreach (@scandir($assetsDir) ?: [] as $af) {
            if ($af === '.' || $af === '..') continue;
            if (!isset($activeFiles[$af])) {
                $sz = @filesize($assetsDir . '/' . $af) ?: 0;
                if (@unlink($assetsDir . '/' . $af)) {
                    $cleanedBytes += $sz;
                    $cleanedCount++;
                }
            }
        }
    }
}

$freeSpace = @disk_free_space($projectRoot);
$freeMb = $freeSpace !== false ? round($freeSpace / (1024 * 1024), 2) . ' MB' : 'Unknown';
echo "<div><b>[Cleanup Summary]</b> Removed <b>{$cleanedCount}</b> files (" . round($cleanedBytes / (1024 * 1024), 2) . " MB freed). Free space: <b>{$freeMb}</b></div>";

// 5. Unpack update zip
$unpacked = false;
foreach (['cpanel_update_with_build.zip', 'cpanel_update_today.zip'] as $z) {
    $zp = $projectRoot . '/' . $z;
    if (file_exists($zp)) {
        $zip = new ZipArchive();
        if ($zip->open($zp) === true) {
            echo "<div>[Unpacking] Extracting <code>{$z}</code>...</div>";
            $zip->extractTo($projectRoot);
            $zip->close();
            @unlink($zp); // delete zip to free quota
            $unpacked = true;
            echo "<div>[Success] Extracted and removed <code>{$z}</code>!</div>";
            break;
        } else {
            echo "<div>[Error] Could not open <code>{$z}</code>.</div>";
        }
    }
}

// 6. Verify Critical Assets
$criticalFiles = [
    'build/assets/app-Dp-Z435i.js',
    'build/assets/index-DVz44jsP.js',
    'build/assets/input-DUAQp_0z.js',
    'build/assets/app-CuIAOfZk.js',
    'build/assets/login-Bq7Hx0et.js',
];
$allFound = true;
foreach ($criticalFiles as $cf) {
    $cfp = __DIR__ . '/' . $cf;
    if (file_exists($cfp)) {
        echo "<div style='color:#4ade80'>✓ Found: {$cf} (" . filesize($cfp) . " bytes)</div>";
    } else {
        echo "<div style='color:#f87171'>✗ MISSING: {$cf}</div>";
        $allFound = false;
    }
}

if ($allFound) {
    echo "<h3 style='color:#4ade80'>🎉 All critical assets are in place!</h3>";
    echo "<p><a href='/login' style='background:#0284c7;color:#fff;padding:8px 16px;text-decoration:none;border-radius:4px'>Go to Login Page →</a></p>";
} else {
    echo "<h3 style='color:#f87171'>⚠️ Some assets are still missing. Please upload cpanel_update_with_build.zip to your project root.</h3>";
}

echo '</body></html>';
