<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$cols = Illuminate\Support\Facades\Schema::getColumnListing('payroll_columns');
echo json_encode($cols, JSON_PRETTY_PRINT) . PHP_EOL;

$records = Illuminate\Support\Facades\DB::table('payroll_columns')->get();
echo "Total records: " . $records->count() . PHP_EOL;
foreach ($records as $r) {
    echo "ID: {$r->id} | Client: {$r->client_dbname} | DB Col: {$r->column_name} | Label: {$r->display_label}" . PHP_EOL;
}

