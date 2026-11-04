<?php

$rootDir = realpath(__DIR__ . '/..');
$cleanRoot = rtrim(str_replace('\\', '/', $rootDir), '/') . '/';

// 1. Build Only Zip (for uploading directly to public_html or public)
$buildOnlyPath = $rootDir . '/build_only.zip';
if (file_exists($buildOnlyPath)) {
    @unlink($buildOnlyPath);
}

$zipBuild = new ZipArchive();
if ($zipBuild->open($buildOnlyPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    $buildDir = $rootDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'build';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $cleanItemPath = str_replace('\\', '/', $item->getPathname());
        $cleanBuildDir = rtrim(str_replace('\\', '/', $buildDir), '/') . '/';
        $sub = substr($cleanItemPath, strlen($cleanBuildDir));

        // Add to both build/ and public/build/
        if ($item->isDir()) {
            $zipBuild->addEmptyDir('build/' . $sub);
            $zipBuild->addEmptyDir('public/build/' . $sub);
        } else {
            $zipBuild->addFile($item->getPathname(), 'build/' . $sub);
            $zipBuild->addFile($item->getPathname(), 'public/build/' . $sub);
        }
    }
    $zipBuild->close();
    echo "Created build_only.zip (" . number_format(filesize($buildOnlyPath) / (1024 * 1024), 2) . " MB)\n";
}

// 2. Today Changes Only Zip (Only files modified today)
$todayZipPath = $rootDir . '/today_changes_only.zip';
if (file_exists($todayZipPath)) {
    @unlink($todayZipPath);
}

$zipToday = new ZipArchive();
if ($zipToday->open($todayZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
    // Collect all git changed files today + build
    $gitOutput = shell_exec('git diff --name-only baec135~1 HEAD 2>&1');
    $files = array_filter(array_map('trim', explode("\n", (string)$gitOutput)));

    $addedFiles = 0;
    foreach ($files as $relFile) {
        if (str_starts_with($relFile, 'scratch/') || str_starts_with($relFile, '.') || str_ends_with($relFile, '.zip')) {
            continue;
        }
        $fullPath = $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relFile);
        if (file_exists($fullPath) && !is_dir($fullPath)) {
            $zipToday->addFile($fullPath, str_replace('\\', '/', $relFile));
            $addedFiles++;
        }
    }

    // Add build files to today zip
    $buildDir = $rootDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'build';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($buildDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $cleanItemPath = str_replace('\\', '/', $item->getPathname());
        $cleanBuildDir = rtrim(str_replace('\\', '/', $buildDir), '/') . '/';
        $sub = substr($cleanItemPath, strlen($cleanBuildDir));

        if ($item->isDir()) {
            $zipToday->addEmptyDir('build/' . $sub);
            $zipToday->addEmptyDir('public/build/' . $sub);
        } else {
            $zipToday->addFile($item->getPathname(), 'build/' . $sub);
            $zipToday->addFile($item->getPathname(), 'public/build/' . $sub);
            $addedFiles += 2;
        }
    }

    $zipToday->close();
    echo "Created today_changes_only.zip (" . number_format(filesize($todayZipPath) / (1024 * 1024), 2) . " MB, {$addedFiles} files)\n";
}

echo "All specialized packages created!\n";
