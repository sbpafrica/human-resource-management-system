<?php

$rootDir = realpath(__DIR__ . '/..');

echo "Creating 'new update.zip' and 'new_update.zip' in {$rootDir}...\n";

$targetZips = [
    $rootDir . '/new update.zip',
    $rootDir . '/new_update.zip',
];

$dirsToInclude = [
    'app',
    'database/migrations',
    'resources/views',
    'resources/js',
    'routes',
    'public/build',
];

foreach ($targetZips as $zipPath) {
    if (file_exists($zipPath)) {
        @unlink($zipPath);
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Error: Cannot create {$zipPath}\n");
    }

    $fileCount = 0;
    foreach ($dirsToInclude as $dir) {
        $fullPath = $rootDir . '/' . $dir;
        if (!is_dir($fullPath)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $subPath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $item->getPathname());
            $subPath = str_replace('\\', '/', $subPath);

            if ($item->isDir()) {
                $zip->addEmptyDir($subPath);
            } else {
                $zip->addFile($item->getPathname(), $subPath);
                $fileCount++;
            }
        }
    }

    $zip->close();
    $sizeMb = number_format(filesize($zipPath) / (1024 * 1024), 2);
    echo "Created " . basename($zipPath) . " ({$sizeMb} MB, {$fileCount} files)\n";
}

echo "Packaging complete!\n";

