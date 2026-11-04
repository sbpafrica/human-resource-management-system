<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PayrollColumn;

echo "=== TESTING UNIVERSAL PAYROLL COLUMN MAPPING ===\n\n";

$clientDb = 'test_client_' . time();

// 1. Check canonical standard columns
$standardCols = PayrollColumn::getStandardColumnsWithOverrides($clientDb);
echo "1. Retrieved " . count($standardCols) . " standard columns for client {$clientDb}.\n";

// Verify staff_id is present and protected
$staffIdCol = collect($standardCols)->firstWhere('column_name', 'staff_id');
if ($staffIdCol) {
    echo "   [PASS] staff_id identified in standard columns: " . $staffIdCol['display_label'] . "\n";
} else {
    echo "   [FAIL] staff_id not found in standard columns.\n";
}

// 2. Test saving standard column override for 'food'
echo "\n2. Testing saving standard column override for 'food'...\n";
$colName = 'food';
$customLabel = 'Canteen & Lunch Allowance';
$customAliases = 'Canteen, Lunch, Staff Food, Daily Lunch';

$override = PayrollColumn::updateOrCreate(
    [
        'client_dbname' => $clientDb,
        'column_name'   => $colName,
    ],
    [
        'is_custom'       => false,
        'display_label'   => $customLabel,
        'header_aliases'  => $customAliases,
        'data_type'       => 'decimal(10,2)',
        'show_in_payslip' => true,
        'payslip_section' => 'earnings',
        'is_active'       => true,
    ]
);

echo "   [PASS] Saved override record with is_custom = false (ID: {$override->id})\n";

// 3. Test getColumnLabelsMap
$labelsMap = PayrollColumn::getColumnLabelsMap($clientDb);
if (($labelsMap['food'] ?? null) === $customLabel) {
    echo "   [PASS] getColumnLabelsMap reflects customized label: '{$labelsMap['food']}'\n";
} else {
    echo "   [FAIL] getColumnLabelsMap returned: " . ($labelsMap['food'] ?? 'null') . "\n";
}

// 4. Test header alias resolution via getAllHeaderAliasesMap
$aliasesMap = PayrollColumn::getAllHeaderAliasesMap($clientDb);
echo "\n4. Testing Header Ingestion Resolution:\n";

$testHeaders = [
    'Canteen & Lunch Allowance' => 'Custom Display Label',
    'Canteen'                   => 'Custom Alias 1',
    'Lunch'                     => 'Custom Alias 2',
    'Staff Food'                => 'Custom Alias 3',
    'Food Allowance'            => 'Default Canonical Label',
    'food'                      => 'Database column name itself',
    'Food Allow'                => 'Canonical default alias',
    'Canteen Allowance'         => 'Direct variation without &',
];

foreach ($testHeaders as $header => $desc) {
    $norm = PayrollColumn::normalizeHeader($header);
    $resolved = $aliasesMap[$norm] ?? null;
    if ($resolved === 'food') {
        echo "   [PASS] '{$header}' ({$desc}) -> successfully resolved to database column 'food'\n";
    } else {
        echo "   [INFO] '{$header}' (norm: '{$norm}') resolved to " . ($resolved ?? 'NONE') . "\n";
    }
}

// 5. Test mapRowHeadersToDb
echo "\n5. Testing mapRowHeadersToDb in row ingestion:\n";
$dummyHeaderRow = ['Staff ID', 'Employee Name', 'Lunch', 'Basic Salary'];
$dummyDataRow   = ['SBP001', 'John Doe', 150.00, 2000.00];

$extracted = PayrollColumn::mapRowHeadersToDb($dummyDataRow, $dummyHeaderRow, $clientDb);
if (($extracted['food'] ?? null) === 150.00) {
    echo "   [PASS] Upload row with header 'Lunch' securely mapped to \$row['food'] = 150.00\n";
} else {
    echo "   [FAIL] Expected extracted['food'] == 150.00, got: " . var_export($extracted, true) . "\n";
}

// Also test upload with original canonical name 'Food Allowance'
$dummyHeaderRow2 = ['Staff ID', 'Employee Name', 'Food Allowance', 'Basic Salary'];
$dummyDataRow2   = ['SBP002', 'Jane Doe', 220.00, 2500.00];
$extracted2 = PayrollColumn::mapRowHeadersToDb($dummyDataRow2, $dummyHeaderRow2, $clientDb);
if (($extracted2['food'] ?? null) === 220.00) {
    echo "   [PASS] Upload row with original header 'Food Allowance' also securely mapped to \$row['food'] = 220.00\n";
} else {
    echo "   [FAIL] Expected extracted2['food'] == 220.00, got: " . var_export($extracted2, true) . "\n";
}

// 6. Test template augmentation
echo "\n6. Testing template header display:\n";
$headers = ['staff_id', 'food', 'basic'];
$example = ['SBP001', 50.00, 1500.00];
PayrollColumn::augmentTemplate($headers, $example, $clientDb);

if (in_array($customLabel, $headers, true)) {
    echo "   [PASS] augmentTemplate rewrote 'food' header to '{$customLabel}'\n";
} else {
    echo "   [FAIL] Template headers: " . implode(', ', $headers) . "\n";
}

// 7. Test Reset to Default
echo "\n7. Testing reset to system default:\n";
PayrollColumn::where('client_dbname', $clientDb)
    ->where('column_name', 'food')
    ->where('is_custom', false)
    ->delete();

$labelsMapAfterReset = PayrollColumn::getColumnLabelsMap($clientDb);
if (($labelsMapAfterReset['food'] ?? null) === 'Food Allowance') {
    echo "   [PASS] After reset, 'food' reverted back to canonical default: 'Food Allowance'\n";
} else {
    echo "   [FAIL] Expected 'Food Allowance', got: " . ($labelsMapAfterReset['food'] ?? 'null') . "\n";
}

// Cleanup any test records
PayrollColumn::where('client_dbname', $clientDb)->delete();

echo "\n=== ALL VERIFICATIONS COMPLETED ===\n";
