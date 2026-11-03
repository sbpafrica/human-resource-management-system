<?php

$dir = realpath(__DIR__ . '/../resources/views/payslip');
$files = glob($dir . '/*.blade.php');

$pattern = '/([ \t]*)@if\(\(float\)\$val\s*!=\s*0\s*\|\|\s*\(!is_numeric\(\$val\)\s*&&\s*!empty\(\$val\)\)\)([\s\S]*?)\n([ \t]*)@endif/m';

$updatedCount = 0;

foreach ($files as $file) {
    $content = file_get_contents($file);
    $basename = basename($file);

    // Replace the @if((float)$val != 0 ...) ... @endif with just the inner content
    $newContent = preg_replace_callback($pattern, function($matches) {
        $inner = $matches[2];
        return $inner;
    }, $content);

    // Also ensure $val uses data_get($payslip, $cc->column_name) instead of $payslip->{$cc->column_name}
    $newContent = preg_replace(
        '/@php\s+\$val\s*=\s*\$payslip->\{\$cc->column_name\}\s*\?\?\s*0;\s*@endphp/',
        '@php $val = data_get($payslip, $cc->column_name); @endphp',
        $newContent
    );
    $newContent = preg_replace(
        '/@php\s+\$val\s*=\s*data_get\(\$payslip,\s*\$cc->column_name,\s*0\);\s*@endphp/',
        '@php $val = data_get($payslip, $cc->column_name); @endphp',
        $newContent
    );

    // Also ensure numeric formatting displays '0.00' when null or 0
    $newContent = str_replace(
        ': $val }}',
        ": ($val ?? '0.00') }}",
        $newContent
    );

    if ($newContent !== $content) {
        file_put_contents($file, $newContent);
        echo "Updated {$basename}\n";
        $updatedCount++;
    }
}

echo "Finished updating {$updatedCount} payslip view(s)!\n";

