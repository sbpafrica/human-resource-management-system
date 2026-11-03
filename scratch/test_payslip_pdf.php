<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Client;
use App\Models\PayrollColumn;

$client = Client::where('client_dbname', 'shefer')->first();
$customColumns = PayrollColumn::getPayslipColumns('shefer');

$dummyPayslip = (object)[
    'staff_id' => 'ST001',
    'name1' => 'Akosua Test',
    'position' => 'Manager',
    'department' => 'Operations',
    'basic' => 5000.00,
    'basic_earned' => 5000.00,
    'gross' => 6000.00,
    'total_cash_emolument' => 5500.00,
    'total_assessable_income' => 6000.00,
    'ssnit_5_5_percent' => 275.00,
    'paye' => 500.00,
    'net_pay' => 4500.00,
];

try {
    $pdf1 = Pdf::loadView('payslip.shefer-pdf', [
        'payslip' => $dummyPayslip,
        'formattedMonth' => 'October 2026',
        'ssnit' => 'C12345678',
        'bank' => ['bankname' => 'GCB', 'name' => 'Akosua Test', 'alcnum' => '123456', 'branch' => 'Accra'],
        'customColumns' => $customColumns,
    ]);
    echo "shefer-pdf size: " . strlen($pdf1->output()) . " bytes\n";

    $pdf2 = Pdf::loadView('payslip.pdf', [
        'payslip' => $dummyPayslip,
        'formattedMonth' => 'October 2026',
        'ssnit' => 'C12345678',
        'bank' => ['bankname' => 'GCB', 'name' => 'Akosua Test', 'alcnum' => '123456', 'branch' => 'Accra'],
        'customColumns' => $customColumns,
    ]);
    echo "general pdf size: " . strlen($pdf2->output()) . " bytes\n";
} catch (\Throwable $e) {
    echo "PDF Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

