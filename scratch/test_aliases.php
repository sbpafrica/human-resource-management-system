<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PayrollColumn;

$col = PayrollColumn::where('client_dbname', 'shefer')->where('column_name', 'total_cash_emolument')->first();
if ($col) {
    echo "Display label: {$col->display_label}\n";
    echo "Column name: {$col->column_name}\n";
    echo "Aliases in DB: {$col->header_aliases}\n";
    $aliases = PayrollColumn::getAllAliasesForColumn($col);
    echo "Generated normalized aliases: " . implode(', ', $aliases) . "\n\n";

    // Test extraction with different header variations:
    // Case 1: Exact new name
    $headers1 = ['Staff ID', 'Name', 'Total Cash Emolument', 'Net Pay'];
    $row1 = ['SHF001', 'John Doe', 4500.50, 4000.00];
    $res1 = PayrollColumn::extractCustomColumnValues($row1, $headers1, 'shefer');
    echo "Test 1 (Exact label): " . json_encode($res1) . "\n";

    // Case 2: Plural / Changed Header in Excel
    $headers2 = ['Staff ID', 'Name', 'Total Cash Emoluments', 'Net Pay'];
    $row2 = ['SHF002', 'Jane Doe', 5200.75, 4800.00];
    $res2 = PayrollColumn::extractCustomColumnValues($row2, $headers2, 'shefer');
    echo "Test 2 (Plural 'Emoluments'): " . json_encode($res2) . "\n";

    // Case 3: Suffix / Formatting variation
    $headers3 = ['Staff ID', 'Name', 'Cash Emoluments (GHS)', 'Net Pay'];
    $row3 = ['SHF003', 'Bob Smith', ' 3,150.00 ', 2900.00];
    $res3 = PayrollColumn::extractCustomColumnValues($row3, $headers3, 'shefer');
    echo "Test 3 (Cash Emoluments (GHS)): " . json_encode($res3) . "\n";

    // Case 4: Column name itself
    $headers4 = ['Staff ID', 'Name', 'total_cash_emolument', 'Net Pay'];
    $row4 = ['SHF004', 'Alice Green', 6000, 5500.00];
    $res4 = PayrollColumn::extractCustomColumnValues($row4, $headers4, 'shefer');
    echo "Test 4 (Database column name): " . json_encode($res4) . "\n";

    // Case 5: Old format / column missing
    $headers5 = ['Staff ID', 'Name', 'Gross', 'Net Pay'];
    $row5 = ['SHF005', 'Charlie', 3500, 3000];
    $res5 = PayrollColumn::extractCustomColumnValues($row5, $headers5, 'shefer');
    echo "Test 5 (Old format missing column): " . json_encode($res5) . "\n";
} else {
    echo "Column not found!\n";
}

