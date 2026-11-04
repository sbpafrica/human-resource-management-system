<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PayrollColumn;

$col = PayrollColumn::where('client_dbname', 'shefer')->where('column_name', 'total_cash_emolument')->first();
$original = $col->display_label;

$col->display_label = 'Total Cash Emoluments'; // changed display label
$col->save();

$headers = ['Staff ID', 'Name', 'Gross Salary', 'Total Cash Emolument', 'Employer SSNIT (13%)'];
$exampleRow = ['SHF001', 'John Doe', 3500.00, 3500.00, 455.00];

PayrollColumn::augmentTemplate($headers, $exampleRow, 'shefer');

echo "Updated headers:\n";
print_r($headers);

$col->display_label = $original;
$col->save();
