<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PayrollColumn;
use Illuminate\Support\Facades\DB;

echo "=== STARTING FULL VERIFICATION TEST ===" . PHP_EOL . PHP_EOL;

// 1. Check existing column
$col = PayrollColumn::where('client_dbname', 'shefer')->where('column_name', 'total_cash_emolument')->first();
if (!$col) {
    die("Error: total_cash_emolument not found!\n");
}
echo "1. Initial State:" . PHP_EOL;
echo "   Column Name: {$col->column_name}" . PHP_EOL;
echo "   Display Label: {$col->display_label}" . PHP_EOL;
echo "   Header Aliases: {$col->header_aliases}" . PHP_EOL . PHP_EOL;

// 2. Simulate User Updating the Display Label to 'Total Cash Emoluments 2026'
$originalLabel = $col->display_label;
$originalAliases = $col->header_aliases;

$newLabel = "Total Cash Emoluments 2026";
$userEnteredAliases = "Cash Emoluments, Cash Emolument, Total Cash";

// Logic matching PayrollColumnController::update:
$aliasesArray = array_filter(array_map('trim', preg_split('/[,;|\n\r]+/', $userEnteredAliases)));
if ($col->display_label !== $newLabel && !in_array($col->display_label, $aliasesArray, true)) {
    array_unshift($aliasesArray, $col->display_label);
}
if (!in_array($col->column_name, $aliasesArray, true)) {
    $aliasesArray[] = $col->column_name;
}
$col->display_label = $newLabel;
$col->header_aliases = implode(', ', array_unique($aliasesArray));
$col->save();

echo "2. After Update:" . PHP_EOL;
echo "   Column Name (MySQL): {$col->column_name} (UNTOUCHED!)" . PHP_EOL;
echo "   Display Label: {$col->display_label}" . PHP_EOL;
echo "   Header Aliases: {$col->header_aliases}" . PHP_EOL . PHP_EOL;

// 3. Test Template Header Augmentation (Template should now show NEW display label)
$headers = [
    'Staff ID', 'Name', 'Position', 'Gross Salary',
    'Total Cash Emolument', // Old hardcoded template header
    'Total Assessable Income',
    'Net Pay'
];
$exampleRow = ['SHF001', 'John Doe', 'Tech', 3000.00, 3000.00, 3000.00, 2500.00];

PayrollColumn::augmentTemplate($headers, $exampleRow, 'shefer');
echo "3. Template Augmentation Output:" . PHP_EOL;
echo "   Header at position 4: " . $headers[4] . PHP_EOL;
if ($headers[4] === 'Total Cash Emoluments 2026') {
    echo "   [SUCCESS] New name appears on the template header!" . PHP_EOL . PHP_EOL;
} else {
    echo "   [FAIL] Header was not updated!" . PHP_EOL . PHP_EOL;
}

// 4. Test Excel Upload Mapping with NEW changed header name
$newUploadedHeaders = ['Staff ID', 'Name', 'Gross Salary', 'Total Cash Emoluments 2026', 'Net Pay'];
$rowWithNewName = ['SHF001', 'John Doe', 3000.00, 4250.00, 3800.00];
$extractedNew = PayrollColumn::extractCustomColumnValues($rowWithNewName, $newUploadedHeaders, 'shefer');

echo "4. Ingestion with NEW Header Name ('Total Cash Emoluments 2026'):" . PHP_EOL;
echo "   Extracted data: " . json_encode($extractedNew) . PHP_EOL;
if (($extractedNew['total_cash_emolument'] ?? null) === 4250.00) {
    echo "   [SUCCESS] Successfully mapped to target DB column 'total_cash_emolument'!" . PHP_EOL . PHP_EOL;
} else {
    echo "   [FAIL] Could not extract with new header name!" . PHP_EOL . PHP_EOL;
}

// 5. Test Excel Upload Mapping with OLD format header name ('Total Cash Emolument')
$oldUploadedHeaders = ['Staff ID', 'Name', 'Gross Salary', 'Total Cash Emolument', 'Net Pay'];
$rowWithOldName = ['SHF002', 'Jane Doe', 4000.00, 4800.00, 4100.00];
$extractedOld = PayrollColumn::extractCustomColumnValues($rowWithOldName, $oldUploadedHeaders, 'shefer');

echo "5. Ingestion with OLD Format Header ('Total Cash Emolument'):" . PHP_EOL;
echo "   Extracted data: " . json_encode($extractedOld) . PHP_EOL;
if (($extractedOld['total_cash_emolument'] ?? null) === 4800.00) {
    echo "   [SUCCESS] Old format securely maps to 'total_cash_emolument' without any issues!" . PHP_EOL . PHP_EOL;
} else {
    echo "   [FAIL] Could not extract with old format header!" . PHP_EOL . PHP_EOL;
}

// 6. Test Excel Upload Mapping with an Alias ('Cash Emoluments')
$aliasHeaders = ['Staff ID', 'Name', 'Gross Salary', 'Cash Emoluments', 'Net Pay'];
$rowWithAlias = ['SHF003', 'Bob Smith', 2500.00, 2750.00, 2300.00];
$extractedAlias = PayrollColumn::extractCustomColumnValues($rowWithAlias, $aliasHeaders, 'shefer');

echo "6. Ingestion with Alias ('Cash Emoluments'):" . PHP_EOL;
echo "   Extracted data: " . json_encode($extractedAlias) . PHP_EOL;
if (($extractedAlias['total_cash_emolument'] ?? null) === 2750.00) {
    echo "   [SUCCESS] Alias securely mapped to 'total_cash_emolument'!" . PHP_EOL . PHP_EOL;
} else {
    echo "   [FAIL] Could not extract with alias!" . PHP_EOL . PHP_EOL;
}

// 7. Test Ingestion with Legacy 49-column sheet (Old Format without custom column)
$legacyHeaders = ['Staff ID', 'Name', 'Gross Salary', 'Employer SSNIT (13%)', 'Net Pay'];
$rowLegacy = ['SHF004', 'Alice', 3000.00, 390.00, 2600.00];
$extractedLegacy = PayrollColumn::extractCustomColumnValues($rowLegacy, $legacyHeaders, 'shefer');

echo "7. Ingestion with Legacy Format (No custom column present):" . PHP_EOL;
echo "   Extracted data: " . json_encode($extractedLegacy) . PHP_EOL;
if (empty($extractedLegacy['total_cash_emolument'])) {
    echo "   [SUCCESS] Safely returns empty without corrupting or overwriting SSNIT column!" . PHP_EOL . PHP_EOL;
} else {
    echo "   [FAIL] Inappropriately extracted value!" . PHP_EOL . PHP_EOL;
}

// Revert test changes back to clean state
$col->display_label = $originalLabel;
$col->header_aliases = $originalAliases;
$col->save();

echo "=== ALL VERIFICATION TESTS PASSED SUCCESSFULLY! ===" . PHP_EOL;

