<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$tables = DB::select("SHOW TABLES");
$dbName = DB::getDatabaseName();
$prop = "Tables_in_" . $dbName;

$allColumnsByClient = [];
$universalUniqueColumns = [];

$clients = Client::all()->keyBy('client_dbname');

foreach ($tables as $t) {
    $tName = strtolower($t->$prop);
    
    // Check which client this table belongs to
    foreach ($clients as $clientDb => $client) {
        if (str_starts_with($tName, strtolower($clientDb))) {
            // Check if this looks like a monthly table (e.g., ends in month+year like april26 or october25)
            if (preg_match('/(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|may|jun|jul|aug|sep|sept|oct|nov|dec)[0-9]{2}/i', $tName)) {
                $cols = Schema::getColumnListing($tName);
                if (!isset($allColumnsByClient[$clientDb])) {
                    $allColumnsByClient[$clientDb] = [
                        'sample_table' => $tName,
                        'client_name' => $client->client_name,
                        'columns' => $cols
                    ];
                }
                foreach ($cols as $c) {
                    $universalUniqueColumns[strtolower($c)] = $c;
                }
            }
        }
    }
}

echo "Found " . count($allColumnsByClient) . " client payroll schemas in database.\n";
echo "Total unique column names across all client monthly tables: " . count($universalUniqueColumns) . "\n\n";

echo "=== ALL UNIQUE COLUMN NAMES ACROSS ALL CLIENTS ===\n";
ksort($universalUniqueColumns);
foreach ($universalUniqueColumns as $colKey => $orig) {
    echo "  '{$colKey}',\n";
}

echo "\n=== JAY KAY (jkay) COLUMNS ===\n";
if (isset($allColumnsByClient['jkay'])) {
    echo "Table: " . $allColumnsByClient['jkay']['sample_table'] . "\n";
    echo implode(', ', $allColumnsByClient['jkay']['columns']) . "\n";
} else {
    echo "No jkay table found in client loop.\n";
}

