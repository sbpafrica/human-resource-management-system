<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function testGetStandardColumnsWithOverrides(string $clientDb, bool $filterByClient = true): array
{
    $canonical = PayrollColumn::getCanonicalStandardColumns();

    // Fetch overrides stored in database for this client (and 'all')
    $overrides = PayrollColumn::where('is_custom', false)
        ->where('is_active', true)
        ->where(function ($q) use ($clientDb) {
            $q->where('client_dbname', $clientDb)->orWhere('client_dbname', 'all');
        })
        ->get()
        ->keyBy('column_name');

    if ($clientDb === 'all' || !$filterByClient) {
        $result = [];
        foreach ($canonical as $colName => $def) {
            $override = $overrides->get($colName);
            $result[] = [
                'id'              => $override?->id ?? null,
                'client_dbname'   => $override?->client_dbname ?? $clientDb,
                'is_custom'       => false,
                'column_name'     => $colName,
                'display_label'   => $override?->display_label ?? $def['display_label'],
                'default_label'   => $def['display_label'],
                'header_aliases'  => $override?->header_aliases ?? $def['header_aliases'],
                'default_aliases' => $def['header_aliases'],
                'data_type'       => $def['data_type'],
                'position_after'  => $override?->position_after ?? null,
                'show_in_payslip' => $override?->show_in_payslip ?? true,
                'payslip_section' => $override?->payslip_section ?? $def['payslip_section'],
                'description'     => $def['description'] ?? '',
                'has_override'    => $override !== null,
                'locked'          => !empty($def['locked']),
            ];
        }
        return $result;
    }

    // Client-specific tailoring
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

    $clientCols = [];
    foreach ($tables as $t) {
        $cols = Schema::getColumnListing($t->TABLE_NAME);
        foreach ($cols as $c) {
            $clientCols[strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $c))] = $c;
        }
    }

    // Overrides
    foreach ($overrides as $ov) {
        $clientCols[strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $ov->column_name))] = $ov->column_name;
    }

    // Core ID
    $clientCols['staff_id'] = 'staff_id';

    $result = [];
    $processedKeys = [];

    // 1. Canonical matching
    foreach ($canonical as $colName => $def) {
        $norm = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '_', $colName));
        if (isset($clientCols[$norm]) || isset($clientCols[$colName])) {
            $override = $overrides->get($colName);
            $isLocked = !empty($def['locked']) || in_array($colName, ['staff_id', 'staffid', 'staffida', 'employ', 'id']);
            $result[] = [
                'id'              => $override?->id ?? null,
                'client_dbname'   => $override?->client_dbname ?? $clientDb,
                'is_custom'       => false,
                'column_name'     => $colName,
                'display_label'   => $override?->display_label ?? $def['display_label'],
                'default_label'   => $def['display_label'],
                'header_aliases'  => $override?->header_aliases ?? $def['header_aliases'],
                'default_aliases' => $def['header_aliases'],
                'data_type'       => $def['data_type'],
                'position_after'  => $override?->position_after ?? null,
                'show_in_payslip' => $override?->show_in_payslip ?? true,
                'payslip_section' => $override?->payslip_section ?? $def['payslip_section'],
                'description'     => $def['description'] ?? '',
                'has_override'    => $override !== null,
                'locked'          => $isLocked,
            ];
            $processedKeys[$norm] = true;
            $processedKeys[$colName] = true;
        }
    }

    // 2. Any client table physical columns not in canonical
    foreach ($clientCols as $norm => $origCol) {
        if (!isset($processedKeys[$norm]) && !isset($processedKeys[$origCol])) {
            // Skip pure system timestamps
            if (in_array($norm, ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'password'])) continue;

            $override = $overrides->get($origCol);
            $cleanLabel = ucwords(str_replace('_', ' ', $norm));
            $isId = in_array($norm, ['staff_id', 'staffid', 'staffida', 'employ', 'id', 'employid']);

            $result[] = [
                'id'              => $override?->id ?? null,
                'client_dbname'   => $override?->client_dbname ?? $clientDb,
                'is_custom'       => false,
                'column_name'     => $origCol,
                'display_label'   => $override?->display_label ?? $cleanLabel,
                'default_label'   => $cleanLabel,
                'header_aliases'  => $override?->header_aliases ?? "{$origCol}, {$cleanLabel}",
                'default_aliases' => "{$origCol}, {$cleanLabel}",
                'data_type'       => 'varchar(100)',
                'position_after'  => $override?->position_after ?? null,
                'show_in_payslip' => $override?->show_in_payslip ?? true,
                'payslip_section' => $isId ? 'info' : 'earnings',
                'description'     => "Physical column '{$origCol}' for {$clientDb}.",
                'has_override'    => $override !== null,
                'locked'          => $isId,
            ];
            $processedKeys[$norm] = true;
        }
    }

    return $result;
}

echo "=== TESTING TAILORED STANDARD COLUMNS ===\n\n";

$testClients = ['jkay', 'melcom', 'shefer', 'honda', 'all'];
foreach ($testClients as $c) {
    $cols = testGetStandardColumnsWithOverrides($c);
    echo "Client: {$c} => " . count($cols) . " columns\n";
    if ($c === 'jkay') {
        echo "Jay Kay Columns:\n";
        foreach ($cols as $col) {
            $lockStr = $col['locked'] ? ' [LOCKED]' : '';
            echo "  - {$col['column_name']} ({$col['display_label']}) [{$col['payslip_section']}]{$lockStr}\n";
        }
    }
}

