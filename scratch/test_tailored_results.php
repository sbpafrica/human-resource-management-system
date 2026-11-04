<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Test tailoring function
function getTailoredColumnsForClient(string $clientDb): array
{
    $canonical = PayrollColumn::getCanonicalStandardColumns();
    if ($clientDb === 'all' || empty($clientDb)) {
        return array_keys($canonical);
    }

    $clientCols = [];

    // 1. Table schema discovery
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

    foreach ($tables as $t) {
        $cols = Schema::getColumnListing($t->TABLE_NAME);
        foreach ($cols as $c) {
            $clientCols[strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $c))] = true;
        }
    }

    // 2. Overrides in DB for this client
    $overrides = DB::table('payroll_columns')
        ->where('client_dbname', $clientDb)
        ->pluck('column_name');
    foreach ($overrides as $ov) {
        $clientCols[strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $ov))] = true;
    }

    // 3. Always include staff_id
    $clientCols['staff_id'] = true;

    // Filter canonical
    $filtered = [];
    foreach ($canonical as $colName => $def) {
        $norm = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $colName));
        if (isset($clientCols[$norm]) || isset($clientCols[$colName])) {
            $filtered[$colName] = $def;
        }
    }

    return array_keys($filtered);
}

$clients = Client::all();
echo "TAILORED COLUMN COUNTS BY CLIENT:\n\n";

foreach ($clients as $client) {
    $dbName = $client->client_dbname;
    $cols = getTailoredColumnsForClient($dbName);
    echo sprintf("%-20s (%-15s): %2d columns (was 410)\n", 
        $client->client_name, 
        $dbName, 
        count($cols)
    );
}

