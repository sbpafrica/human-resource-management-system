<?php
require __DIR__.'/../vendor/autoload.php';

function normalizePayslip($payslip) {
    if (!$payslip) return $payslip;
    
    $payslip->basic = $payslip->basic 
        ?? $payslip->basicsalary 
        ?? $payslip->total1 
        ?? 0;

    $payslip->grosspay = $payslip->grosspay 
        ?? $payslip->gross 
        ?? $payslip->grosssalary 
        ?? $payslip->totalamount 
        ?? 0;

    $payslip->netpay = $payslip->netpay 
        ?? $payslip->net_pay 
        ?? $payslip->takehome 
        ?? $payslip->takehomepay 
        ?? 0;

    return $payslip;
}

$payslip = new stdClass();
$payslip->basicsalary = "40.32";
$payslip->totalamount = "1290.24";
$payslip->takehomepay = "1213.45";

$normalized = normalizePayslip($payslip);

echo "grosspay: " . data_get($normalized, 'grosspay', 0) . "\n";
echo "netpay: " . data_get($normalized, 'netpay', 0) . "\n";
echo "basic: " . data_get($normalized, 'basic', 0) . "\n";

