<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;

$customColumns = PayrollColumn::getPayslipColumns('shefer');

$dummyPayslip = (object)[
    'staff_id' => 'ST001',
    'name1' => 'Akosua Test',
    'position' => 'Manager',
    'department' => 'Operations',
    'basic' => 5000.00,
    'gross' => 6000.00,
    'total_cash_emolument' => null, // NULL test!
    'total_assessable_income' => null, // NULL test!
    'net_pay' => 4500.00,
];

$html = view('payslip.shefer-pdf', [
    'payslip' => $dummyPayslip,
    'formattedMonth' => 'October 2026',
    'ssnit' => 'C12345678',
    'bank' => ['bankname' => 'GCB', 'name' => 'Akosua Test', 'alcnum' => '123456', 'branch' => 'Accra'],
    'customColumns' => $customColumns,
])->render();

echo "HTML contains 'Total Cash Emolument'? " . (str_contains($html, 'Total Cash Emolument') ? 'YES' : 'NO') . "\n";
echo "HTML contains 'Total Assessable Income'? " . (str_contains($html, 'Total Assessable Income') ? 'YES' : 'NO') . "\n";

