<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$headers = [
    'Staff ID', 'Name', 'Gross Salary', 'Net Pay'
];
$example = ['ST001', 'Test User', 5000.00, 4200.00];

\App\Models\PayrollColumn::augmentTemplate($headers, $example, 'shefer');

echo "Augmented headers:\n";
print_r($headers);
echo "Augmented example row:\n";
print_r($example);

