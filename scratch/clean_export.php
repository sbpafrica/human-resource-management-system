<?php

$dir = realpath(__DIR__ . '/../app/Http/Controllers');
$files = glob($dir . '/*PayController.php');

foreach ($files as $file) {
    $content = file_get_contents($file);

    // If within export() function, remove augmentTemplate
    // Match export function
    $pattern = '/(public function export\b[\s\S]*?)([\t ]*\$nullRow = null;\s*\\\\App\\\\Models\\\\PayrollColumn::augmentTemplate[\s\S]*?;\n)([\s\S]*?public function)/m';

    if (preg_match($pattern, $content)) {
        $content = preg_replace_callback($pattern, function($m) {
            return $m[1] . $m[3];
        }, $content);

        file_put_contents($file, $content);
        echo "Cleaned export in " . basename($file) . "\n";
    }
}

