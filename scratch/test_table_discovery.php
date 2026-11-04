<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$clients = Client::all();
echo "Testing client DB table column discovery:\n\n";

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
        LIMIT 20
    ", $bindings);
    
    $colSet = [];
    foreach ($tables as $t) {
        $cols = Schema::getColumnListing($t->TABLE_NAME);
        foreach ($cols as $c) {
            $colSet[strtolower($c)] = $c;
        }
    }
    
    echo sprintf("%-20s (%-15s): %2d tables found | %2d unique columns\n", 
        $client->short_name, 
        $clientDb, 
        count($tables), 
        count($colSet)
    );
    if (count($colSet) > 0) {
        echo "   Cols: " . implode(', ', array_slice(array_values($colSet), 0, 10)) . "...\n";
    }
}

