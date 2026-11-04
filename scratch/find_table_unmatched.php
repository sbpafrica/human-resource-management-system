<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$canonical = PayrollColumn::getCanonicalStandardColumns();
$canonicalLower = [];
foreach ($canonical as $k => $v) {
    $canonicalLower[strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $k))] = true;
}

$clients = Client::all();
$allUnmatched = [];

foreach ($clients as $client) {
    $clientDb = $client->client_dbname;
    $prefixes = PayrollColumn::getTablePrefixesForClient($clientDb);
    
    $whereParts = [];
    $bindings = [];
    foreach ($prefixes as $p) {
        $whereParts[] = "TABLE_NAME LIKE ?";
        $bindings[] = "{$p}%";
    }

    $tables = DB::select("
        SELECT TABLE_NAME 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND (" . implode(' OR ', $whereParts) . ")
          AND TABLE_NAME NOT LIKE '%_sign'
          AND TABLE_NAME NOT LIKE '%_contracts%'
          AND TABLE_NAME NOT LIKE '%attendance%'
          AND TABLE_NAME NOT LIKE '%sms%'
          AND TABLE_NAME NOT LIKE '%data'
          AND TABLE_NAME NOT LIKE '%con'
        ORDER BY TABLE_NAME DESC 
        LIMIT 10
    ", $bindings);

    foreach ($tables as $t) {
        $cols = Schema::getColumnListing($t->TABLE_NAME);
        foreach ($cols as $c) {
            $norm = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $c));
            if (!isset($canonicalLower[$norm])) {
                $allUnmatched[$norm][] = "{$client->short_name}:{$c}";
            }
        }
    }
}

echo "=== UNMATCHED PHYSICAL TABLE COLUMNS (" . count($allUnmatched) . ") ===\n";
foreach ($allUnmatched as $col => $sources) {
    $src = implode(', ', array_unique(array_slice($sources, 0, 3)));
    echo "  $col => [$src]\n";
}

