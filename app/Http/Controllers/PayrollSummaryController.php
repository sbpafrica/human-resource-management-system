<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Inertia\Inertia;

class PayrollSummaryController extends Controller
{
    public function show($client_dbname)
    {
        $client = Client::where('client_dbname', $client_dbname)->firstOrFail();

        return Inertia::render('Payroll/ClientSummary/Index', [
            'client' => $client,
        ]);
    }
}
