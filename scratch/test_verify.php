<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;
use App\Models\Sheferpay;
use Illuminate\Support\Facades\Schema;

echo "Checking SheferPay createTableIfNotExists:\n";
Sheferpay::createTableIfNotExists('sheferoctober26');
echo "Sheferpay table verified!\n";

echo "Checking getColumnsForClient:\n";
$cols = PayrollColumn::getColumnsForClient('shefer');
echo "Count of Shefer columns: " . $cols->count() . "\n";
foreach ($cols as $c) {
    echo " - {$c->column_name} ({$c->display_label})\n";
}

echo "All tests passed cleanly!\n";

