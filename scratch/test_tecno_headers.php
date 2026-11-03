<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$headers = [
    'Staff ID', 'Name', 'Department', 'Hire Date', 'Contract Start', 'Contract End', 'Position', 'Grade',
    'Categories', 'Workplace', 'Bank Name', 'Account Number', 'Branch', 'SSNIT', 'COL 12', 'Work Days',
    'Leave Days', 'OT Work Days', 'OT Weekend', 'Basic', 'Performance Bonus', 'Coefficient', 'Performance Standard',
    'Allowance', 'Commission', 'OT Working Day', 'OT Holiday', 'Other', 'Annual Bonus', 'Leave Deduction',
    'Other Deduction', 'Loan', 'Gross Pay', 'Excess', 'Total Emoluments', 'Accommodation', 'Charge Income',
    'Petra', 'SSNIT Amount', 'NSSF', 'Tax Base', 'PAYE', 'Annual Tax', 'Net Pay', 'HR Cost', 'Brand Name'
];
$exampleRow = [
    'STF123', 'John Doe', 'Sales', '10/19/2016', '01/01/2025', '12/31/2025', 'SALES REPRESENTATIVE', 'L09',
    'FORMAL STAFF', 'ACCRA', 'Bank A', '1234567890', 'Branch X', 'P0012345678', 'P0031284639', 21, 0, 0, 0,
    1113.75, 1856.25, 1, 1856.25, 742.5, 0, 0, 0, 0, 0, 0, 0, 0, 3712.5, 0, 3712.5, 0, 3712.5, 55.69,
    5.57, 144.79, 3651.24, 529.72, 0, 3121.52, 3857.29, 'TRANSSION'
];

\App\Models\PayrollColumn::augmentTemplate($headers, $exampleRow, 'transsiontec');
echo "Tecno headers count: " . count($headers) . "\n";
echo "Tecno example row count: " . count($exampleRow) . "\n";

