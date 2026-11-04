<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$clients = DB::table('client')->select('client_name','client_dbname','short_name')->get();
echo "=== CLIENTS ===\n";
foreach ($clients as $c) {
    echo "{$c->short_name} => {$c->client_dbname}\n";
}

// Test if INFORMATION_SCHEMA works
echo "\n=== TESTING INFO SCHEMA ON FIRST CLIENT ===\n";
$first = $clients->first();
if ($first && $first->client_dbname) {
    $schemas = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$first->client_dbname]);
    echo "Schema found: " . count($schemas) . "\n";
    
    if (count($schemas) > 0) {
        $tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE' LIMIT 5", [$first->client_dbname]);
        echo "Tables found: " . count($tables) . "\n";
        foreach ($tables as $t) echo "  - {$t->TABLE_NAME}\n";
        
        if (count($tables) > 0) {
            $cols = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 10", [$first->client_dbname, $tables[0]->TABLE_NAME]);
            echo "Cols in {$tables[0]->TABLE_NAME}: " . count($cols) . "\n";
            foreach ($cols as $c) echo "  - {$c->COLUMN_NAME}\n";
        }
    }
}

