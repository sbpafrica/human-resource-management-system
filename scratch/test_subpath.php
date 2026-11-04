<?php
$rootDir = realpath(__DIR__ . '/..');
$dir = 'app';
$fullPath = $rootDir . '/' . $dir;
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $item) {
    echo "Item pathname: " . $item->getPathname() . "\n";
    echo "rootDir: " . $rootDir . "\n";
    $subPath = str_replace($rootDir . DIRECTORY_SEPARATOR, '', $item->getPathname());
    echo "subPath: " . $subPath . "\n";
    break;
}

