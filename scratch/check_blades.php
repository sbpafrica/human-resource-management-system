<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$files = glob(__DIR__ . '/../resources/views/payslip/*.blade.php');
$hasErrors = false;
foreach ($files as $f) {
    $content = file_get_contents($f);
    try {
        $compiled = $app['blade.compiler']->compileString($content);
        $tmp = tempnam(sys_get_temp_dir(), 'blade_test_');
        file_put_contents($tmp, '<?php function _test_render() { ?>' . $compiled . '<?php }');
        $out = shell_exec('php -l ' . escapeshellarg($tmp) . ' 2>&1');
        if (strpos($out, 'No syntax errors detected') === false) {
            echo "ERROR in " . basename($f) . ": " . trim($out) . "\n";
            $hasErrors = true;
        }
        @unlink($tmp);
    } catch (\Throwable $e) {
        echo "EXCEPTION in " . basename($f) . ": " . $e->getMessage() . "\n";
        $hasErrors = true;
    }
}
if (!$hasErrors) {
    echo "All payslip blade files compiled and passed PHP lint successfully!\n";
}

