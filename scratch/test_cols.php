<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$cols = App\Models\PayrollColumn::all();
foreach ($cols as $c) {
    echo "ID: {$c->id} | client: {$c->client_dbname} | col: {$c->column_name} | label: {$c->display_label} | type: {$c->data_type} | pos: {$c->position_after} | payslip: {$c->show_in_payslip} [{$c->payslip_section}]\n";
}
if ($cols->isEmpty()) {
    echo "No custom columns found in payroll_columns table.\n";
}

