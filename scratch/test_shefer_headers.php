<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = new \App\Http\Controllers\SheferPayController();
$ref = new ReflectionClass($controller);
// Let's create spreadsheet as SheferPayController does and see headers in row 1
$headers = [
    'Staff ID', 'Name', 'Position', 'Department', 'Start Date',
    'Basic Salary', 'Total Weekdays', 'Days Worked', 'Leave Pay', 'Absent Days', 'Basic Earned',
    'Night Days',
    'Weekday OT Hours', 'Weekday OT Rate', 'Weekday OT Amount',
    'Weekend OT Hours', 'Weekend OT Rate', 'Weekend OT Amount',
    'Sunday OT Hours', 'Sunday OT Rate', 'Sunday OT Amount',
    'Fixed OT Basic', 'Fixed OT', 'Total Overtime',
    'Food Basic', 'Food Allowance',
    'Airtime Basic', 'Airtime Allowance',
    'Night Allowance',
    'Response Basic', 'Response Allowance',
    'Transport Basic', 'Transport Allowance',
    'Medical Basic', 'Medical Allowance',
    'Total Allowance',
    'Gross Salary',
    'Total Cash Emolument',
    'Total Assessable Income',
    'Employer SSNIT (13%)',
    'Employee SSNIT (5.5%)',
    'PETRA',
    'Taxable Income', 'PAYE', 'OT Tax', 'Total Tax',
    'Absent Without Pay', 'Salary Loan',
    'Total Deductions', 'Refunds', 'Net Pay',
];
$exampleRow = [];
\App\Models\PayrollColumn::augmentTemplate($headers, $exampleRow, 'shefer');

echo "Augmented headers count: " . count($headers) . "\n";
echo "Headers:\n";
foreach ($headers as $idx => $h) {
    if (in_array(strtolower($h), ['total cash emolument', 'total assessable income', 'more money'])) {
        echo "  [★ MATCH] col " . ($idx + 1) . ": {$h}\n";
    }
}

