<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$tables = \Illuminate\Support\Facades\DB::select('SHOW TABLES');
$found = [];
foreach ($tables as $t) {
    $arr = (array)$t;
    $val = reset($arr);
    if (preg_match('/(jkay|melcom|shefer|trans|pz|honda|bunge|avara|twel|olam|a2|driver|fon|century|nyonkopa)/i', $val)) {
        $found[] = $val;
    }
}
echo "Found " . count($found) . " client payroll tables:\n";
foreach (array_slice($found, 0, 30) as $tbl) {
    echo " - $tbl\n";
}

