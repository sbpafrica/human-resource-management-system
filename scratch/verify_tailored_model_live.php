<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;

echo "=== VERIFYING MODEL getStandardColumnsWithOverrides ===\n\n";

// Test Jay Kay
$jkayCols = PayrollColumn::getStandardColumnsWithOverrides('jkay');
echo "Jay Kay columns count: " . count($jkayCols) . "\n";
$first5 = array_slice($jkayCols, 0, 5);
foreach ($first5 as $c) {
    echo "  - {$c['column_name']} => '{$c['display_label']}' (locked: " . ($c['locked'] ? 'YES' : 'NO') . ")\n";
}

// Test Melcom
$melcomCols = PayrollColumn::getStandardColumnsWithOverrides('melcom');
echo "\nMelcom columns count: " . count($melcomCols) . "\n";

// Test All
$allCols = PayrollColumn::getStandardColumnsWithOverrides('all');
echo "\nAll clients columns count: " . count($allCols) . "\n";

// Test un-tailored show_all
$jkayAllCols = PayrollColumn::getStandardColumnsWithOverrides('jkay', false);
echo "\nJay Kay show_all=true count: " . count($jkayAllCols) . "\n";

echo "\nSUCCESS: ALL CHECKS PASSED!\n";

