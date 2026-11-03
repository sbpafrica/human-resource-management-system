<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$files = glob(__DIR__ . '/../app/Http/Controllers/*PayController.php');
$files = array_merge($files, [__DIR__ . '/../app/Http/Controllers/SalaryPaymentController.php']);

$results = [];
foreach ($files as $file) {
    $className = 'App\\Http\\Controllers\\' . basename($file, '.php');
    if (!class_exists($className)) {
        continue;
    }
    $ref = new ReflectionClass($className);
    if (!$ref->hasMethod('downloadTemplate')) {
        continue;
    }
    $m = $ref->getMethod('downloadTemplate');
    if ($m->getNumberOfRequiredParameters() > 0) {
        continue;
    }

    try {
        $inst = new $className();
        $resp = $inst->downloadTemplate();
        $results[basename($file)] = [
            'status' => 'SUCCESS',
            'type' => get_class($resp),
            'http_code' => method_exists($resp, 'getStatusCode') ? $resp->getStatusCode() : 'N/A'
        ];
    } catch (\Throwable $e) {
        $results[basename($file)] = [
            'status' => 'ERROR',
            'message' => $e->getMessage(),
            'line' => $e->getLine()
        ];
    }
}

foreach ($results as $ctrl => $res) {
    if ($res['status'] === 'SUCCESS') {
        echo "[OK] {$ctrl} => {$res['type']} (HTTP {$res['http_code']})\n";
    } else {
        echo "[FAIL] {$ctrl} => line {$res['line']}: {$res['message']}\n";
    }
}

