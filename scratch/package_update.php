<?php

$rootDir = realpath(__DIR__ . '/..');

echo "Creating deployment packages in {$rootDir}...\n";

// 1. Files for code update (backend files)
$zip1Path = $rootDir . '/cpanel_update_today.zip';
if (file_exists($zip1Path)) {
    unlink($zip1Path);
}

$zip1 = new ZipArchive();
if ($zip1->open($zip1Path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Cannot create {$zip1Path}\n");
}

// Add app/, database/migrations/, resources/views/
$dirsToAdd = [
    'app',
    'database/migrations',
    'resources/views',
    'routes',
];

foreach ($dirsToAdd as $dir) {
    $fullPath = $rootDir . '/' . $dir;
    if (!is_dir($fullPath)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $subPath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $item->getPathname());
        $subPath = str_replace('\\', '/', $subPath);

        if ($item->isDir()) {
            $zip1->addEmptyDir($subPath);
        } else {
            $zip1->addFile($item->getPathname(), $subPath);
        }
    }
}

$zip1->close();
echo "Created cpanel_update_today.zip (" . number_format(filesize($zip1Path) / 1024, 1) . " KB)\n";

// 2. Full update with public/build
$zip2Path = $rootDir . '/cpanel_update_with_build.zip';
if (file_exists($zip2Path)) {
    unlink($zip2Path);
}

$zip2 = new ZipArchive();
if ($zip2->open($zip2Path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Cannot create {$zip2Path}\n");
}

$dirsToAddWithBuild = [
    'app',
    'database/migrations',
    'resources/views',
    'routes',
    'public/build',
];

foreach ($dirsToAddWithBuild as $dir) {
    $fullPath = $rootDir . '/' . $dir;
    if (!is_dir($fullPath)) continue;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $subPath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $item->getPathname());
        $subPath = str_replace('\\', '/', $subPath);

        if ($item->isDir()) {
            $zip2->addEmptyDir($subPath);
        } else {
            $zip2->addFile($item->getPathname(), $subPath);
        }
    }
}

$zip2->close();
echo "Created cpanel_update_with_build.zip (" . number_format(filesize($zip2Path) / (1024 * 1024), 2) . " MB)\n";
echo "Packaging complete!\n";

