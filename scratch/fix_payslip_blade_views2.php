<?php

$dir = realpath(__DIR__ . '/../resources/views/payslip');
$files = glob($dir . '/*.blade.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    $newContent = str_replace('( ?? \'0.00\')', '($val ?? \'0.00\')', $content);
    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
    }
}

echo "Fixed syntax in all files!\n";

