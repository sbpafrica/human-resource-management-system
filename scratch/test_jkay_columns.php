<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PayrollColumn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function testGetClientColumns($clientDb) {
    $prefix = strtolower(trim($clientDb));
    $prefixes = ($prefix === 'all')
        ? ['shefer', 'pz', 'melcom', 'nyonkopa', 'jkay']
        : PayrollColumn::getTablePrefixesForClient($prefix);

    $likeClauses = [];
    $bindings = [];
    foreach ($prefixes as $p) {
        $likeClauses[] = "TABLE_NAME = ? OR TABLE_NAME LIKE ?";
        $bindings[] = $p;
        $bindings[] = "{$p}%";
    }
    $whereLikes = implode(' OR ', $likeClauses);

    $tables = DB::select("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND ({$whereLikes})
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
        $tableName = $t->TABLE_NAME;
        if (Schema::hasTable($tableName)) {
            $cols = Schema::getColumnListing($tableName);
            if (!empty($cols)) {
                return ['table' => $tableName, 'columns' => $cols];
            }
        }
    }
    return ['table' => null, 'columns' => []];
}

$jkayResult = testGetClientColumns('jkay');
echo "JKAY table found: " . $jkayResult['table'] . "\n";
echo "JKAY columns count: " . count($jkayResult['columns']) . "\n";
echo implode(', ', $jkayResult['columns']) . "\n";

