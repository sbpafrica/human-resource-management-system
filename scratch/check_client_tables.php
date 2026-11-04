<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$clients = ['jkay', 'melcom', 'transsion', 'pz', 'shefer', 'honda', 'bungegh', 'nyonkopa'];
foreach ($clients as $c) {
    $prefixes = \App\Models\PayrollColumn::getTablePrefixesForClient($c);
    $tables = \Illuminate\Support\Facades\DB::select("
        SELECT TABLE_NAME 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND (" . implode(' OR ', array_map(fn($p) => "TABLE_NAME LIKE '{$p}%'", $prefixes)) . ")
          AND TABLE_NAME NOT LIKE '%_sign'
          AND TABLE_NAME NOT LIKE '%_contracts%'
          AND TABLE_NAME NOT LIKE '%attendance%'
          AND TABLE_NAME NOT LIKE '%sms%'
          AND TABLE_NAME NOT LIKE '%data'
          AND TABLE_NAME NOT LIKE '%con'
        ORDER BY TABLE_NAME DESC 
        LIMIT 5
    ");
    echo "Client: $c (prefixes: " . implode(',', $prefixes) . ")\n";
    foreach ($tables as $t) {
        $cols = \Illuminate\Support\Facades\Schema::getColumnListing($t->TABLE_NAME);
        echo "  Table: {$t->TABLE_NAME} (" . count($cols) . " columns)\n";
        echo "    Sample cols: " . implode(', ', array_slice($cols, 0, 10)) . "\n";
    }
}

