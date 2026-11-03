<?php

$dir = realpath(__DIR__ . '/../app/Http/Controllers');

$clientMap = [
    'A2feedPayController.php'          => 'a2feed',
    'A2soyaPayController.php'          => 'a2soya',
    'AvaraPayController.php'           => 'avara',
    'BeninBungePayController.php'      => 'beninbunge',
    'BungePayController.php'           => 'bungegh',
    'CenturyPayController.php'         => 'centurypay',
    'CivBungePayController.php'        => 'civ',
    'DriverPayController.php'          => 'driverssnit',
    'FonPayController.php'             => 'fonpackage',
    'GmoneyPayController.php'          => 'gmoney',
    'HondaPayController.php'           => 'honda',
    'HygienePayrollController.php'     => 'hygiene',
    'InhousePayController.php'         => 'inhouse',
    'JaykayPayController.php'          => 'jkay',
    'MelcomPayController.php'          => 'melcom',
    'NyonkopaPayController.php'        => 'nyonkopa',
    'OfibwhPayController.php'          => 'olambwh',
    'OfiexternalPayController.php'     => 'hygieneexternal',
    'OfihygienePayController.php'      => 'olamhygiene',
    'OfioffloadPayController.php'      => 'offload',
    'OfitempPayController.php'         => 'olamtem',
    'OlamfoodPayController.php'        => 'olamfood',
    'TecnoPayController.php'           => 'transsiontec',
    'TogoBungePayController.php'       => 'togobunge',
    'TranssionPayController.php'       => 'transsion',
    'TranssnetPayController.php'       => 'transsnet',
    'TwelliumPayController.php'        => 'twellium',
    'TwelliumaccraPayController.php'   => 'twelliumaccra',
    'XiaomiPayController.php'          => 'xiaomi',
];

$updatedCount = 0;

foreach ($clientMap as $file => $clientDb) {
    $fullPath = $dir . '/' . $file;
    if (!file_exists($fullPath)) continue;

    $content = file_get_contents($fullPath);

    // Check if augmentTemplate is already in downloadTemplate
    if (str_contains($content, "PayrollColumn::augmentTemplate(\$headers, \$nullRow, '{$clientDb}')")) {
        continue;
    }

    // Pattern 1: $sheet->fromArray($headers, null, 'A1');
    if (str_contains($content, '$sheet->fromArray($headers')) {
        $replacement = "\$nullRow = null;\n        \\App\\Models\\PayrollColumn::augmentTemplate(\$headers, \$nullRow, '{$clientDb}');\n        \$sheet->fromArray(\$headers";
        $content = str_replace('$sheet->fromArray($headers', $replacement, $content);
        file_put_contents($fullPath, $content);
        echo "Updated downloadTemplate in {$file}\n";
        $updatedCount++;
    }
    // Pattern 2: foreach ($headers as $i => $header)
    elseif (str_contains($content, 'foreach ($headers as $i => $header)')) {
        $replacement = "\$nullRow = null;\n        \\App\\Models\\PayrollColumn::augmentTemplate(\$headers, \$nullRow, '{$clientDb}');\n\n        foreach (\$headers as \$i => \$header)";
        $content = str_replace('foreach ($headers as $i => $header)', $replacement, $content);
        file_put_contents($fullPath, $content);
        echo "Updated downloadTemplate in {$file}\n";
        $updatedCount++;
    }
    // Pattern 3: foreach ($headers as $index => $header)
    elseif (str_contains($content, 'foreach ($headers as $index => $header)')) {
        $replacement = "\$nullRow = null;\n        \\App\\Models\\PayrollColumn::augmentTemplate(\$headers, \$nullRow, '{$clientDb}');\n\n        foreach (\$headers as \$index => \$header)";
        $content = str_replace('foreach ($headers as $index => $header)', $replacement, $content);
        file_put_contents($fullPath, $content);
        echo "Updated downloadTemplate in {$file}\n";
        $updatedCount++;
    }
}

echo "Total updated: {$updatedCount}\n";

