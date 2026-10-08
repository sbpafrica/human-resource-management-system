<?php
require __DIR__.'/../vendor/autoload.php';

$payslip = new stdClass();
$payslip->basicsalary = "1000.00";
$payslip->totalamount = 1500;
$payslip->takehomepay = 1200;

$payslip->basic = $payslip->basic 
    ?? $payslip->basicsalary 
    ?? 0;

var_dump(data_get($payslip, 'basic', 0));
