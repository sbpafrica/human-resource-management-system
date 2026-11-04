<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$canonical = PayrollColumn::getCanonicalStandardColumns();
$canonicalLower = [];
foreach ($canonical as $k => $v) {
    $canonicalLower[strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $k))] = $k;
}

$prefixes = PayrollColumn::getTablePrefixesForClient('jkay');
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

$matched = [];
$unmatched = [];
$allTableCols = [];

foreach ($tables as $t) {
    $cols = Schema::getColumnListing($t->TABLE_NAME);
    foreach ($cols as $c) {
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $c));
        $allTableCols[$clean] = $c;
        if (isset($canonicalLower[$clean])) {
            $matched[$canonicalLower[$clean]] = true;
        } else {
            $unmatched[$c] = true;
        }
    }
}

echo "Jay Kay Total Table Columns: " . count($allTableCols) . "\n";
echo "Matched to Canonical: " . count($matched) . "\n";
echo "Unmatched: " . count($unmatched) . " (" . implode(', ', array_keys($unmatched)) . ")\n";

echo "\nMatched Columns List for Jay Kay:\n";
foreach (array_keys($matched) as $col) {
    echo "  - $col => " . $canonical[$col]['display_label'] . "\n";
}

