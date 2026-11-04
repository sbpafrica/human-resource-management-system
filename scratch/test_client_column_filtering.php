<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\PayrollColumn;
use App\Models\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$clients = Client::all();
echo "Testing client column resolution for all " . count($clients) . " clients:\n\n";

foreach ($clients as $client) {
    $dbName = $client->client_dbname;
    $cols = PayrollColumn::getClientStandardColumnNames($dbName);
    echo sprintf("%-20s (%-15s): %d columns\n", $client->client_name, $dbName, count($cols));
    if (count($cols) > 0) {
        echo "   Sample: " . implode(', ', array_slice($cols, 0, 8)) . "...\n";
    }
}

