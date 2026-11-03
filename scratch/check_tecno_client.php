<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$clients = \App\Models\Client::select('id', 'client_name', 'short_name', 'client_dbname', 'slug', 'is_active')->get();
foreach ($clients as $c) {
    if (str_contains(strtolower($c->client_name), 'tecno') || str_contains(strtolower($c->short_name), 'tecno') || str_contains(strtolower($c->client_dbname), 'tecno')) {
        echo "Found Tecno match: id={$c->id}, name={$c->client_name}, short={$c->short_name}, dbname={$c->client_dbname}, slug={$c->slug}, is_active={$c->is_active}\n";
    }
}

