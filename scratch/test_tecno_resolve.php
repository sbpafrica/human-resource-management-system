<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$controller = new \App\Http\Controllers\PayslipController();
$reflector = new ReflectionClass($controller);
$method = $reflector->getMethod('resolveClient');
$method->setAccessible(true);

$testSlugs = [
    'tecno', 'transsiontec', 'unitycorp', 'melcom', 'driver', 'drivers', 
    'century', 'jaykay', 'jkay', 'pz', 'twellium', 'twelliumaccra', 
    'shefer', 'fon', 'ofibwh', 'ofioffload', 'ofiexternal', 'ofihygiene', 
    'ofitemp', 'honda', 'bunge', 'inhouse', 'xiaomi', 'a2soya', 'a2feed', 
    'avara', 'gmoney', 'transburkina', 'tbfa', 'civ', 'togobunge', 'beninbunge'
];

$allPassed = true;
foreach ($testSlugs as $slug) {
    try {
        $res = $method->invoke($controller, $slug);
        echo "OK: '{$slug}' => {$res->client_name} [{$res->client_dbname}]\n";
    } catch (\Throwable $e) {
        echo "FAILED: '{$slug}' => " . $e->getMessage() . "\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\nAll test slugs resolved successfully!\n";
} else {
    echo "\nSome slugs failed to resolve!\n";
}
