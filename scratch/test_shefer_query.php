<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\Request;
use App\Http\Controllers\SheferPayController;

$controller = new SheferPayController();
$request = Request::create('/sheferpay', 'GET', ['month' => '2026-10']);

try {
    $response = $controller->index($request);
    echo "Index method returned response successfully!\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

