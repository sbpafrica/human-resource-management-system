<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$headers = [
    'Staff ID', 'Name', 'Gross Salary', 'Total Cash Emolument', 'Total Assessable Income', 'Net Pay'
];
$row = ['ST001', 'Test User', 5000.00, 4800.50, 4900.25, 4200.00];

$extracted = \App\Models\PayrollColumn::extractCustomColumnValues($row, $headers, 'shefer');
echo "Extracted custom columns:\n";
print_r($extracted);

