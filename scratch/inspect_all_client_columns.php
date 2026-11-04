<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "=== CLIENTS IN DATABASE ===\n";
$clients = Client::all();
foreach ($clients as $c) {
    echo "ID: {$c->id} | Slug: {$c->slug} | DBName: {$c->client_dbname} | Name: {$c->client_name}\n";
}

echo "\n=== JAY KAY TABLES & COLUMNS ===\n";
// Find any tables with jkay or jaykay
$tables = DB::select("SHOW TABLES");
$dbName = DB::getDatabaseName();
$prop = "Tables_in_" . $dbName;

$jkayTables = [];
$samplePayrollTables = [];
foreach ($tables as $t) {
    $tName = $t->$prop;
    if (str_contains($tName, 'jaykay') || str_contains($tName, 'jkay')) {
        $jkayTables[] = $tName;
    }
    if (preg_match('/(shefer|pz|melcom|transsion|nyonkopa|jaykay|jkay|bunge|century|avara)[a-z]+[0-9]{2}/i', $tName)) {
        $prefix = preg_replace('/[0-9]+$/', '', $tName);
        $samplePayrollTables[$prefix] = $tName;
    }
}

echo "Jay Kay Tables: " . implode(', ', array_slice($jkayTables, 0, 10)) . "\n";
if (!empty($jkayTables)) {
    $sampleJkay = end($jkayTables);
    echo "Columns in $sampleJkay:\n";
    $cols = Schema::getColumnListing($sampleJkay);
    echo implode(', ', $cols) . "\n\n";
}

echo "=== SAMPLE PAYROLL TABLES ACROSS CLIENTS ===\n";
foreach ($samplePayrollTables as $pfx => $tbl) {
    echo "--- $pfx (Table: $tbl) ---\n";
    $cols = Schema::getColumnListing($tbl);
    echo implode(', ', $cols) . "\n\n";
}

