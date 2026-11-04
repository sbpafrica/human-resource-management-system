<?php

$rootDir = realpath(__DIR__ . '/..');
$cleanRoot = rtrim(str_replace('\\', '/', $rootDir), '/') . '/';

echo "Clean root: {$cleanRoot}\n";

$testPath = $rootDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'build' . DIRECTORY_SEPARATOR . 'manifest.json';
$cleanFile = str_replace('\\', '/', $testPath);

if (str_starts_with($cleanFile, $cleanRoot)) {
    $rel = substr($cleanFile, strlen($cleanRoot));
    echo "Relative path: {$rel}\n";
} else {
    echo "Failed to strip root!\n";
}

