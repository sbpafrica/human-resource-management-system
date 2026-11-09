<?php

$rootDir = realpath(__DIR__ . '/..');
$cleanRoot = rtrim(str_replace('\\', '/', $rootDir), '/') . '/';

// Verify manifest.json is valid before packaging
$manifestPath = $rootDir . '/public/build/manifest.json';
if (!file_exists($manifestPath)) {
    die("ERROR: {$manifestPath} does not exist!\n");
}
$manifestContent = file_get_contents($manifestPath);
$decoded = json_decode($manifestContent, true);
if ($decoded === null) {
    die("FATAL ERROR: manifest.json is corrupted: " . json_last_error_msg() . "\n");
}
if (!isset($decoded['resources/css/app.css'])) {
    die("FATAL ERROR: resources/css/app.css is missing in manifest.json!\n");
}
echo "Verified manifest.json is 100% valid with " . count($decoded) . " entries, including resources/css/app.css!\n";

$zipsToCreate = [
    'new update.zip' => true,
    'new_update.zip' => true,
    'cpanel_update_with_build.zip' => true,
    'cpanel_update_today.zip' => false,
];

$dirs = [
    'app',
    'database/migrations',
    'resources/views',
    'resources/js',
    'routes',
    'public/build',
];

foreach ($zipsToCreate as $zipName => $includeBuild) {
    $zipPath = $rootDir . '/' . $zipName;
    if (file_exists($zipPath)) {
        @unlink($zipPath);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Error creating {$zipName}\n");
    }

    $fileCount = 0;
    foreach ($dirs as $dir) {
        if (!$includeBuild && ($dir === 'public/build' || $dir === 'resources/js')) {
            continue;
        }

        $fullDir = $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir);
        if (!is_dir($fullDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $cleanItemPath = str_replace('\\', '/', $item->getPathname());
            if (!str_starts_with($cleanItemPath, $cleanRoot)) {
                continue;
            }

            // Explicitly exclude any .env or .htaccess files
            $basename = $item->getBasename();
            if (str_starts_with($basename, '.env') || $basename === '.htaccess') {
                continue;
            }

            $relPath = substr($cleanItemPath, strlen($cleanRoot));

            if ($item->isDir()) {
                $zip->addEmptyDir($relPath);
            } else {
                $zip->addFile($item->getPathname(), $relPath);
                $fileCount++;
            }
        }
    }

    // If including build, also mirror public/build/ directly to build/ in the zip root
    // so both public/build and build exist for cPanel / public_html setups
    if ($includeBuild) {
        $buildDir = $rootDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'build';
        if (is_dir($buildDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $cleanItemPath = str_replace('\\', '/', $item->getPathname());
                $cleanBuildDir = rtrim(str_replace('\\', '/', $buildDir), '/') . '/';
                if (!str_starts_with($cleanItemPath, $cleanBuildDir)) {
                    continue;
                }

                $subBuildPath = 'build/' . substr($cleanItemPath, strlen($cleanBuildDir));

                if ($item->isDir()) {
                    $zip->addEmptyDir($subBuildPath);
                } else {
                    $zip->addFile($item->getPathname(), $subBuildPath);
                    $fileCount++;
                }
            }
        }
    }

    $zip->close();
    $sizeMb = number_format(filesize($zipPath) / (1024 * 1024), 2);
    echo "Successfully packaged {$zipName} ({$sizeMb} MB, {$fileCount} files)\n";
}

echo "All zips packaged successfully!\n";

