<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controllers = [
    'tecno' => new \App\Http\Controllers\TecnoPayController(),
    'shefer' => new \App\Http\Controllers\SheferPayController(),
    'nyonkopa' => new \App\Http\Controllers\NyonkopaPayController(),
    'transsion' => new \App\Http\Controllers\TranssionPayController(),
];

foreach ($controllers as $name => $controller) {
    echo "\n--- Testing $name downloadTemplate ---\n";
    $response = $controller->downloadTemplate();
    echo "Response class: " . get_class($response) . "\n";
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Content-Type: " . $response->headers->get('Content-Type') . "\n";
    echo "Content-Disposition: " . $response->headers->get('Content-Disposition') . "\n";
    
    ob_start();
    $response->sendContent();
    $content = ob_get_clean();
    echo "Streamed content length: " . strlen($content) . " bytes\n";
    $first4 = substr($content, 0, 4);
    echo "First 4 bytes: " . bin2hex($first4) . " (ASCII: " . ($first4 === "PK\x03\x04" ? "VALID ZIP/XLSX PK\\x03\\x04" : "INVALID!") . ")\n";
}
