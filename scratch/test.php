<?php
$payslip = new stdClass();
$payslip->basicsalary = 1000;
$payslip->totalamount = 1500;
$payslip->takehomepay = 1200;

$payslip->basic = $payslip->basic 
    ?? $payslip->basicsalary 
    ?? 0;

$payslip->grosspay = $payslip->grosspay 
    ?? $payslip->totalamount 
    ?? 0;

$payslip->netpay = $payslip->netpay 
    ?? $payslip->takehomepay 
    ?? 0;

echo json_encode($payslip, JSON_PRETTY_PRINT);
