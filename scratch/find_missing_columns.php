<?php
/**
 * Find all real DB columns across ALL client payroll tables
 * using raw PDO to cross-query database schemas.
 */
require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;
use Illuminate\Support\Facades\DB;

// Get all canonical column names
$canonical = PayrollColumn::getCanonicalStandardColumns();
$canonicalKeys = array_keys($canonical);
echo "=== CANONICAL COLUMNS COUNT: " . count($canonicalKeys) . " ===\n\n";

// Get all client databases
$clients = DB::table('client')->select('client_name', 'client_dbname', 'short_name')->get();

$allDbColumns = [];
$columnsByClient = [];
$skip = ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'email_verified_at', 'password', 'email', 'id', 'paymonth', 'payyear', 'month', 'year', 'activate', 'status'];

foreach ($clients as $client) {
    $dbName = $client->client_dbname;
    if (!$dbName) continue;

    // Check if DB exists
    try {
        $exists = DB::select("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?", [$dbName]);
        if (empty($exists)) continue;
    } catch (\Exception $e) {
        continue;
    }

    // Get all tables in client DB
    try {
        $tables = DB::select("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = 'BASE TABLE'", [$dbName]);
    } catch (\Exception $e) {
        echo "ERR tables {$dbName}: {$e->getMessage()}\n";
        continue;
    }

    foreach ($tables as $table) {
        $tbl = $table->TABLE_NAME;
        if (in_array($tbl, ['migrations', 'sessions', 'cache', 'jobs', 'failed_jobs', 'personal_access_tokens', 'password_reset_tokens', 'password_resets', 'users', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'roles', 'permissions'])) continue;

        try {
            $cols = DB::select("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?", [$dbName, $tbl]);
        } catch (\Exception $e) {
            continue;
        }

        foreach ($cols as $col) {
            $colName = $col->COLUMN_NAME;
            if (in_array(strtolower($colName), $skip)) continue;

            $allDbColumns[strtolower($colName)] = $colName;
            $columnsByClient[strtolower($colName)][] = "{$client->short_name}:{$tbl}";
        }
    }
}

// Find missing
$canonicalLower = array_map('strtolower', $canonicalKeys);
$missing = [];
foreach ($allDbColumns as $lower => $original) {
    if (!in_array($lower, $canonicalLower)) {
        $sources = array_unique($columnsByClient[$lower] ?? []);
        $missing[$original] = $sources;
    }
}

ksort($missing);

echo "=== ALL UNIQUE DB COLUMNS: " . count($allDbColumns) . " ===\n";
echo "=== MISSING FROM CANONICAL: " . count($missing) . " ===\n\n";

echo "--- MISSING COLUMNS ---\n";
foreach ($missing as $col => $sources) {
    $srcList = implode(', ', array_slice($sources, 0, 4));
    if (count($sources) > 4) $srcList .= " +(" . (count($sources)-4) . " more)";
    echo "  {$col} => [{$srcList}]\n";
}
