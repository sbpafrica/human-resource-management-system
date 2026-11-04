<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;

$cols = PayrollColumn::getCanonicalStandardColumns();
echo "TOTAL CANONICAL STANDARD COLUMNS: " . count($cols) . "\n";

// Verify Jay Kay columns are present
$jkaySample = [
    'overtime_hours', 'overtime_rate', 'overtime_amount', 'daily_rate',
    'days_worked', 'absentee_days', 'absent_deductions', 'earned_basic_salary',
    'saturdays_worked', 'saturday_rate', 'saturday_amount', 'total_days_amount',
    'night_allowance', 'totalovertime', 'management_fee', 'getfund', 'nhil',
    'vat', 'ssf', 'cost_to_jkay', 'ssnit_ee', 'overtime_tax',
    'surcharge_or_student_loan', 'take_home_pay', 'basic_salary',
];

echo "\n--- VERIFYING JAY KAY COLUMNS ---\n";
$allFound = true;
foreach ($jkaySample as $col) {
    if (isset($cols[$col])) {
        echo "  ✓ {$col} -> {$cols[$col]['display_label']} ({$cols[$col]['payslip_section']})\n";
    } else {
        echo "  ✗ MISSING: {$col}\n";
        $allFound = false;
    }
}

if ($allFound) {
    echo "\nALL JAY KAY COLUMNS PRESENT AND VERIFIED!\n";
} else {
    echo "\nSOME COLUMNS ARE MISSING!\n";
}

