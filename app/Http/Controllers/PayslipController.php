<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use ZipArchive;

class PayslipController extends Controller
{
    public function generalIndex(Request $request)
    {
        $user = $request->user();
        $clients = $user->is_super_admin
            ? Client::where('is_active', 1)->orderBy('display_order')->orderBy('client_name')->get()
            : $user->clients()->where('is_active', 1)->orderBy('display_order')->orderBy('client_name')->get();

        if ($clients->isEmpty()) {
            return redirect()->route('dashboard')->with('error', 'No active clients found.');
        }

        $firstClient = $clients->first();
        $routeSlug = $this->getRouteSlug($firstClient);

        return redirect()->route('payslips.index', ['client' => $routeSlug]);
    }

    private function getRouteSlug(Client $client): string
    {
        $map = [
            'good-ease-technology-gh-ltd'        => 'transsion',
            'unitycorp'                          => 'tecno',
            'melcom'                             => 'melcom',
            'melcom-drivers'                     => 'driver',
            'century'                            => 'century',
            'nyonkopa-cocoa-buying-ltd'          => 'nyonkopa',
            'jay-kay'                            => 'jaykay',
            'pz-cussons-ghana-ltd'               => 'pz',
            'twellium-industrial-company-ghana'  => 'twellium',
            'twellium-industrial-company-accra'  => 'twelliumaccra',
            'shefer-plastics-ltd'                => 'shefer',
            'fon-packaging'                      => 'fon',
            'a2-group-of-companies-a2-feed'      => 'a2feed',
            'a2-soya'                            => 'a2soya',
            'ofi-bean-warehouse'                 => 'ofibwh',
            'ofi-offloads'                       => 'ofioffload',
            'ofi-hygiene-external'               => 'ofiexternal',
            'ofi-hygiene'                        => 'ofihygiene',
            'ofi-tempering'                      => 'ofitemp',
            'honda-manufacturing-gh-ltd'         => 'honda',
            'bunge'                              => 'bunge',
            'inhouse'                            => 'inhouse',
            'xiaomi-ghana'                       => 'xiaomi',
            'olam-food-ingredients'              => 'olamfood',
            'g-money-financial-services-limited' => 'gmoney',
            'avara-rc-limited'                   => 'avara',
            'transsion-bfa'                      => 'transsion-bfa',
            'civ-bunge'                          => 'civ',
            'togo-bunge'                         => 'togobunge',
            'benin-bunge'                        => 'beninbunge',
        ];

        return $map[$client->slug] ?? $client->slug;
    }

    private function getFormalSlug(string $routeSlug): string
    {
        $map = [
            'transsion'     => 'good-ease-technology-gh-ltd',
            'tecno'         => 'unitycorp',
            'melcom'        => 'melcom',
            'driver'        => 'melcom-drivers',
            'century'       => 'century',
            'nyonkopa'      => 'nyonkopa-cocoa-buying-ltd',
            'jaykay'        => 'jay-kay',
            'pz'            => 'pz-cussons-ghana-ltd',
            'twellium'      => 'twellium-industrial-company-ghana',
            'twelliumaccra' => 'twellium-industrial-company-accra',
            'shefer'        => 'shefer-plastics-ltd',
            'fon'           => 'fon-packaging',
            'a2feed'        => 'a2-group-of-companies-a2-feed',
            'a2soya'        => 'a2-soya',
            'ofibwh'        => 'ofi-bean-warehouse',
            'ofioffload'    => 'ofi-offloads',
            'ofiexternal'   => 'ofi-hygiene-external',
            'ofihygiene'    => 'ofi-hygiene',
            'ofitemp'       => 'ofi-tempering',
            'honda'         => 'honda-manufacturing-gh-ltd',
            'bunge'         => 'bunge',
            'inhouse'       => 'inhouse',
            'xiaomi'        => 'xiaomi-ghana',
            'olamfood'      => 'olam-food-ingredients',
            'gmoney'        => 'g-money-financial-services-limited',
            'avara'         => 'avara-rc-limited',
            'civ'           => 'civ-bunge',
            'togobunge'     => 'togo-bunge',
            'beninbunge'    => 'benin-bunge',
        ];

        return $map[$routeSlug] ?? $routeSlug;
    }

    private function resolveClient(string $routeSlug)
    {
        $formalSlug = $this->getFormalSlug($routeSlug);

        $client = Client::where('is_active', 1)
            ->where(function ($q) use ($routeSlug, $formalSlug) {
                $q->where('slug', $formalSlug)
                    ->orWhere('slug', $routeSlug)
                    ->orWhere('client_dbname', $routeSlug)
                    ->orWhere('short_name', $routeSlug)
                    ->orWhere('client_name', $routeSlug);
            })
            ->first();

        if ($client) {
            return $client;
        }

        // Fallback: search by database name match
        $client = Client::where('is_active', 1)
            ->where('client_dbname', 'like', "%{$routeSlug}%")
            ->first();

        if ($client) {
            return $client;
        }

        abort(404, "Client '{$routeSlug}' not found or inactive.");
    }

    private function getTableName($client, Carbon $date): string
    {
        $baseAliases = match ($client->client_dbname) {
            'ofiexternal' => ['hygieneexternal', 'ofiexternal'],
            'ofibwh'      => ['olambwh', 'ofibwh'],
            'ofitemp'     => ['olamtem', 'ofitemp'],
            'ofioffload'  => ['offload', 'ofioffload'],
            'ofihygiene'  => ['olamhygiene', 'hygiene', 'ofihygiene'],
            'fon'         => ['fonpackage', 'fon'],
            'century'     => ['centurypay', 'century'],
            'tecno'       => ['transsiontec', 'tecno', 'unitycorp'],
            'transsion'   => ['transsion', 'goodease'],
            'driver'      => ['driverssnit', 'driver'],
            default       => [$client->client_dbname],
        };

        $fy = strtolower($date->format('Fy')); // e.g. january25
        $my = strtolower($date->format('My')); // e.g. jan25
        $fY = strtolower($date->format('FY')); // e.g. january2025
        $mY = strtolower($date->format('MY')); // e.g. jan2025
        $f  = strtolower($date->format('F'));  // e.g. january
        $m  = strtolower($date->format('M'));  // e.g. jan

        foreach ($baseAliases as $base) {
            $candidates = [
                "{$base}{$fy}pay",
                "{$base}{$my}pay",
                "{$base}{$fy}",
                "{$base}{$my}",
                "{$base}{$fY}",
                "{$base}{$mY}",
                "{$base}{$my}payslip",
                "{$base}{$fy}payslip",
                "{$base}{$m}payslip",
                "{$base}{$f}payslip",
                "{$base}_{$fy}",
                "{$base}_{$my}",
                "{$base}",
            ];

            foreach ($candidates as $candidate) {
                if (Schema::hasTable($candidate)) {
                    return $candidate;
                }
            }
        }

        // Default fallback table name
        return $baseAliases[0] . $fy;
    }

    private function detectColumns(string $tableName, $client): array
    {
        if (!Schema::hasTable($tableName)) {
            return ['staffida', 'name'];
        }

        $columns = Schema::getColumnListing($tableName);
        $colMap = [];
        foreach ($columns as $col) {
            $colMap[strtolower($col)] = $col;
        }

        // Detect Staff ID field
        $staffIdCandidates = ['staffida', 'staffid', 'staff_id', 'employ', 'id', 'staff_number', 'employee_id'];
        $staffIdCol = null;
        foreach ($staffIdCandidates as $candidate) {
            if (isset($colMap[$candidate])) {
                $staffIdCol = $colMap[$candidate];
                break;
            }
        }
        if (!$staffIdCol) {
            $staffIdCol = $columns[0] ?? 'staffida';
        }

        // Detect Name field
        $nameCandidates = ['name', 'empoyeename', 'employeename', 'employee_name', 'fullname', 'name1', 'full_name'];
        $nameCol = null;
        foreach ($nameCandidates as $candidate) {
            if (isset($colMap[$candidate])) {
                $nameCol = $colMap[$candidate];
                break;
            }
        }
        if (!$nameCol) {
            $nameCol = $staffIdCol;
        }

        return [$staffIdCol, $nameCol];
    }

    private function resolvePayslipView($client): string
    {
        $dbName    = strtolower((string)($client->client_dbname ?? ''));
        $slug      = strtolower((string)($client->slug ?? ''));
        $shortName = strtolower((string)($client->short_name ?? ''));

        if (str_contains($dbName, 'avara') || str_contains($slug, 'avara') || str_contains($shortName, 'avara')) {
            return 'payslip.avara-pdf';
        }
        if (str_contains($dbName, 'melcom') || str_contains($slug, 'melcom')) {
            return 'payslip.melcom-pdf';
        }
        if (str_contains($dbName, 'honda') || str_contains($slug, 'honda')) {
            return 'payslip.honda-pdf';
        }
        if (str_contains($dbName, 'jkay') || str_contains($dbName, 'jaykay') || str_contains($slug, 'jaykay')) {
            return 'payslip.jaykay-pdf';
        }
        if (str_contains($dbName, 'pz') || str_contains($slug, 'pz')) {
            return 'payslip.pz-pdf';
        }
        if (str_contains($dbName, 'twelliumaccra') || str_contains($slug, 'twelliumaccra')) {
            return 'payslip.twelliumaccra-pdf';
        }
        if (str_contains($dbName, 'twellium') || str_contains($slug, 'twellium')) {
            return 'payslip.twellium-pdf';
        }
        if (str_contains($dbName, 'shefer') || str_contains($slug, 'shefer')) {
            return 'payslip.shefer-pdf';
        }
        if (str_contains($dbName, 'inhouse') || str_contains($slug, 'inhouse')) {
            return 'payslip.inhouse-pdf';
        }
        if (str_contains($dbName, 'civ') || str_contains($slug, 'civ')) {
            return 'payslip.civ-pdf';
        }
        if (str_contains($dbName, 'ofiexternal') || str_contains($slug, 'ofi-hygiene-external') || str_contains($slug, 'ofiexternal') || str_contains($shortName, 'hygiene external') || str_contains($dbName, 'hygieneexternal')) {
            return 'payslip.ofiexternal-pdf';
        }
        if (str_contains($dbName, 'ofihygiene') || str_contains($slug, 'ofi-hygiene') || str_contains($slug, 'ofihygiene') || str_contains($shortName, 'hygiene') || str_contains($dbName, 'olamhygiene')) {
            return 'payslip.ofihygiene-pdf';
        }
        if (str_contains($dbName, 'driver') || str_contains($slug, 'driver') || str_contains($shortName, 'driver')) {
            return 'payslip.drivers-pdf';
        }

        return 'payslip.pdf';
    }

    /**
     * Convert CIV Bunge's payroll column names to the common payslip
     * fields expected by resources/views/payslip.pdf.
     */
    private function normalizeCivPayslip($payslip, $client, $ssnit = '-')
    {
        if ($client->client_dbname !== 'civ' || !$payslip) {
            return $payslip;
        }

        $payslip->staffida       = $payslip->id ?? '-';
        $payslip->name           = $payslip->fullname ?? '-';
        $payslip->position       = $payslip->jobtitle ?? '-';
        $payslip->brandname      = $client->short_name ?? 'CIV Bunge';
        $payslip->ssnit          = $ssnit;
        $payslip->department     = $payslip->{'1stlevel'} ?? ($payslip->city ?? '-');
        $payslip->workdays       = $payslip->actualwork ?? 0;
        $payslip->basic          = $payslip->basic ?? ($payslip->actualbasic ?? 0);
        $payslip->coefficient    = $payslip->coefficient ?? 0;
        $payslip->performstand   = $payslip->monthlykpi ?? 0;
        $payslip->allowance      = (float)($payslip->transport ?? 0)
            + (float)($payslip->callallowance ?? 0)
            + (float)($payslip->leaveallowance ?? 0);
        $payslip->otworkingday   = $payslip->overtime ?? 0;
        $payslip->otholiday      = $payslip->overtimeallowance ?? 0;
        $payslip->other          = 0;
        $payslip->commission     = 0;
        $payslip->grosspay       = $payslip->grosssalary ?? ($payslip->grosssalary1 ?? 0);
        $payslip->annaulbonus    = 0;
        $payslip->paye           = $payslip->itsalaries ?? 0;
        $payslip->leavedays      = 0;
        $payslip->leavededuction = 0;
        $payslip->otherdeduction = $payslip->otherdeductions ?? 0;
        $payslip->loan           = 0;
        $payslip->annaultax      = 0;
        $payslip->netpay         = $payslip->netpay ?? 0;

        return $payslip;
    }

    private function normalizePayslip($payslip, $client, $ssnit = '-')
    {
        if (!$payslip) {
            return $payslip;
        }

        if ($client->client_dbname === 'civ') {
            $payslip = $this->normalizeCivPayslip($payslip, $client, $ssnit);
        }
    
        // Convert object/array to object if needed and helper for property/array access with case-insensitivity
        $getP = function($key) use ($payslip) {
            $source = is_array($payslip) ? $payslip : (array) $payslip;
            
            // 1. Direct match
            if (isset($source[$key])) {
                return $source[$key];
            }

            $normalize = function($s) {
                return str_replace([' ', '_', '-', 'ghc', '%', 'percent', '.'], '', strtolower((string)$s));
            };

            // 2. Case-insensitive / normalized match
            $normalizedSearch = $normalize($key);
            foreach ($source as $k => $val) {
                if ($normalize($k) === $normalizedSearch) {
                    return $val;
                }
            }

            return null;
        };

        // ---------- EMPLOYEE DETAILS ----------
        $payslip->name = $getP('name') 
            ?? $getP('names') 
            ?? $getP('employeename') 
            ?? $getP('empoyeename') 
            ?? $getP('fullname') 
            ?? $getP('name1') 
            ?? $getP('employee_name') 
            ?? '-';
    
        $staffId = $getP('staffida') 
            ?? $getP('staffid') 
            ?? $getP('staff_id') 
            ?? $getP('id') 
            ?? $getP('employ') 
            ?? $getP('STAFF_ID') 
            ?? $getP('STAFFID') 
            ?? $getP('staffno') 
            ?? '-';
    
        $payslip->staffida = $staffId;
        $payslip->staffid  = $staffId;
        $payslip->staff_id = $staffId;
        $payslip->employ   = $staffId;
        $payslip->id       = $staffId;
        $payslip->STAFFID  = $staffId;
        $payslip->STAFF_ID = $staffId;
    
        $payslip->position = $getP('position') 
            ?? $getP('position1') 
            ?? $getP('jobtitle') 
            ?? $getP('title') 
            ?? $getP('workarea')
            ?? $getP('dept')
            ?? '-';
    
        $payslip->brandname = $getP('brandname') 
            ?? $getP('company') 
            ?? $getP('brand') 
            ?? $getP('shopname') 
            ?? $client->short_name 
            ?? $client->client_name 
            ?? '-';
    
        $payslip->department = $getP('department') 
            ?? $getP('dept') 
            ?? $getP('shop') 
            ?? $getP('shopname') 
            ?? $getP('department1') 
            ?? $getP('workarea') 
            ?? $getP('1stlevel') 
            ?? ($getP('city') ?? '-');
    
        // ---------- BASIC & WORK DAYS ----------
        $basicEarned = $getP('basicsalaryearned') ?? $getP('basicsalaryearnedghc') ?? $getP('basic_earned') ?? $getP('total2');
        $basicBase   = $getP('basicsalary') ?? $getP('basicsalaryghc') ?? $getP('basic') ?? $getP('actualbasic') ?? $getP('rate');
        $payslip->BASIC_SALARY_GHC        = (float) ($basicBase ?: ($basicEarned ?: 0));
        $payslip->BASIC_SALARY_EARNED_GHC = (float) ($basicEarned ?: ($basicBase ?: 0));
        $payslip->basic_earned            = $payslip->BASIC_SALARY_EARNED_GHC;
        $payslip->basic                   = $payslip->BASIC_SALARY_GHC;
    
        $payslip->workdays = $getP('noofdayspresent')
            ?? $getP('workdays') 
            ?? $getP('numberofdaysworked') 
            ?? $getP('legalworkdays') 
            ?? $getP('actualdays') 
            ?? $getP('noofdays') 
            ?? $getP('total1')
            ?? $getP('actualwork') 
            ?? $getP('total_weekdays') 
            ?? 0;
    
        $payslip->transport = (float)($getP('transport') ?? $getP('transportghc') ?? 0);
        $payslip->allowance = (float)($getP('allowance') 
            ?? ($payslip->transport > 0 ? $payslip->transport : 0)
            ?? $getP('total_allowance') 
            ?? 0);
    
        // ---------- OVERTIME HOURS MAPPING ----------
        $normalOtHrs = $getP('weekdayovertimehr')
            ?? $getP('weekdayovertimehrs')
            ?? $getP('overtimehr')
            ?? $getP('weekdaysOT') 
            ?? $getP('Normal OT HRS') 
            ?? $getP('normal_ot_hrs') 
            ?? $getP('normalOThrs') 
            ?? $getP('otworkdays') 
            ?? $getP('normal_ot_days') 
            ?? 0;
    
        $saturdayOtHrs = $getP('weekendovertimehr')
            ?? $getP('weekendovertimehrs')
            ?? $getP('overtimeweekend')
            ?? $getP('weekendOT') 
            ?? $getP('Saturday OT HRS') 
            ?? $getP('saturday_ot_hrs') 
            ?? $getP('saturdayOThrs') 
            ?? $getP('weekendOThrs') 
            ?? 0;
    
        $holidayOtHrs = $getP('holidayovertimehr')
            ?? $getP('holidayovertimehrs')
            ?? $getP('holiday') 
            ?? $getP('Holiday OT HRS') 
            ?? $getP('holiday_ot_hrs') 
            ?? $getP('holidayOThrs') 
            ?? $getP('otweekend') 
            ?? 0;
    
        $payslip->weekdaysOT       = $normalOtHrs;
        $payslip->weekendOT        = $saturdayOtHrs;
        $payslip->holiday          = $holidayOtHrs;
    
        // ---------- OVERTIME AMOUNTS MAPPING ----------
        $normalOtAmt = $getP('weekdayovertimetotal')
            ?? $getP('weekdayovertimeamount') 
            ?? $getP('weekdayovertimeamountghc') 
            ?? $getP('overtimetotal')
            ?? $getP('normalovertime') 
            ?? $getP('Normal Overtime') 
            ?? $getP('normal_overtime') 
            ?? $getP('normalot') 
            ?? $getP('normalotamt') 
            ?? 0;
    
        // Fallback calculation if monetary normal overtime value is absent
        if (($normalOtAmt == 0 || $normalOtAmt === null) && $payslip->weekdaysOT > 0) {
            $basicForCalc = $payslip->basic ?? 0;
            if ($basicForCalc > 0) {
                $roundedBasic = ceil($basicForCalc * 100) / 100;
                $normalOtAmt = ($roundedBasic / 216) * $payslip->weekdaysOT * 1.25;
                $normalOtAmt = round($normalOtAmt, 2);
            }
        }
    
        $saturdayOtAmt = $getP('weekendovertimetotal')
            ?? $getP('weekendovertimeamount') 
            ?? $getP('weekendovertimeamountghc') 
            ?? $getP('overtimetotal2')
            ?? $getP('saturdayovertime') 
            ?? $getP('Saturday Overtime') 
            ?? $getP('saturday_overtime') 
            ?? $getP('weekendot') 
            ?? 0;
    
        $holidayOtAmt = $getP('holidayovertimetotal')
            ?? $getP('holidayovertimeamount') 
            ?? $getP('holidayovertimeamountghc') 
            ?? $getP('holidayotamt') 
            ?? $getP('Holiday Overtime') 
            ?? $getP('holidayot') 
            ?? 0;
        
        $payslip->normalovertime   = $normalOtAmt;
        $payslip->saturdayovertime = $saturdayOtAmt;
        $payslip->holidayotamt     = $holidayOtAmt;
        
        $totalOvertime = (float)$normalOtAmt + (float)$saturdayOtAmt + (float)$holidayOtAmt;
        $payslip->overtime = $getP('overtime') ?? $getP('totalovertimeamount') ?? $getP('totalovertimeamountghc') ?? $totalOvertime;
    
        // ---------- TOTALS & DEDUCTIONS ----------
        $grossVal = (float) (
            $getP('totalamount')
            ?? $getP('grosspay') 
            ?? $getP('grosssalary') 
            ?? $getP('grosssalaryghc') 
            ?? $getP('gross') 
            ?? $getP('totalgrosspay') 
            ?? $getP('gross_pay') 
            ?? 0
        );

        if ($grossVal == 0) {
            $grossVal = (float)($payslip->BASIC_SALARY_EARNED_GHC ?: ($payslip->basic ?? 0))
                + (float)($payslip->allowance ?? 0)
                + (float)($payslip->overtime ?? 0)
                + (float)($getP('performancebonus') ?? $getP('bonus') ?? 0)
                + (float)($getP('otherincome') ?? 0)
                + (float)($getP('commission') ?? 0);
        }

        $payslip->grosspay = round($grossVal, 2);

        $netVal = (float) (
            $getP('takehomepay') 
            ?? $getP('takehome') 
            ?? $getP('netpay') 
            ?? $getP('takehomepayghc') 
            ?? $getP('banktransfer') 
            ?? $getP('net_pay') 
            ?? $getP('actualnetpay') 
            ?? 0
        );

        $payslip->paye = (float) (
            $getP('totaltaxdeductions') 
            ?? $getP('totaltaxdeduction') 
            ?? $getP('totaltax') 
            ?? $getP('paye') 
            ?? $getP('payeghc') 
            ?? $getP('itsalaries') 
            ?? 0
        );

        $payslip->ssnitee = (float) (
            $getP('ssnitee') 
            ?? $getP('ssnitee05percent') 
            ?? $getP('ssnitee55percent') 
            ?? $getP('ssnit_ee_0_5_percent') 
            ?? $getP('ssnit_ee_5_5_percent') 
            ?? $getP('ssnit_5_5_percent') 
            ?? $getP('ssf_13_percent') 
            ?? $getP('ssf') 
            ?? 0
        );

        $payslip->otherdeduction = (float) (
            $getP('deductions_surcharge_student_loan_ghc')
            ?? $getP('otherdeduction') 
            ?? $getP('otherdeductions') 
            ?? $getP('student_loan')
            ?? $getP('surcharge') 
            ?? 0
        );

        $payslip->overtimetax = (float) (
            $getP('overtime_tax_ghc')
            ?? $getP('overtimetax')
            ?? 0
        );

        $payslip->totaldeductions = (float) (
            $getP('totaldeductions')
            ?? $getP('totaldeduction')
            ?? 0
        );

        if ($netVal == 0 && $payslip->grosspay > 0) {
            $deductionsSum = (float)($payslip->ssnitee ?? 0)
                + (float)($payslip->paye ?? 0)
                + (float)($payslip->otherdeduction ?? 0)
                + (float)($getP('loan') ?? 0);
            $netVal = max(0, $payslip->grosspay - $deductionsSum);
        }

        $payslip->netpay = round($netVal, 2);
        
        $basicVal = (float)($payslip->basic ?? 0);
        $rawPetraFromDb = $getP('petra') ?? $getP('petra_5_percent') ?? $getP('petrafund') ?? $getP('tier3') ?? $getP('tier2') ?? null;

        if ($rawPetraFromDb !== null && (float)$rawPetraFromDb > 0) {
            $payslip->petra = round((float)$rawPetraFromDb, 2);
        } elseif ($basicVal > 0) {
            $payslip->petra = round($basicVal <= 587.79 ? 29.39 : ($basicVal * 0.05), 2);
        } else {
            $payslip->petra = 0;
        }

        $payslip->trust = (float)($getP('trust') ?? 0);
        $payslip->taxable = (float)($getP('taxableincome') ?? $getP('taxable') ?? 0);
        $payslip->bank = $getP('bank') ?? $getP('bankname') ?? null;
        $payslip->accountnumber = $getP('accountnumber') ?? $getP('account') ?? $getP('alcnum') ?? null;
        $payslip->branch = $getP('branch') ?? null;
        $payslip->nightallowance = (float)($getP('nightallowance') ?? 0);
        $payslip->night = (float)($getP('night') ?? 0);
        $payslip->medical = (float)($getP('medical') ?? 0);
    
        // Preserve the SSNIT string identity number safely without overwriting data properties
        if ((!isset($ssnit) || $ssnit === '-' || empty($ssnit)) && !empty($getP('ssnit'))) {
            $ssnit = (string) $getP('ssnit');
        }
        $payslip->ssnit_number = $ssnit;
        $payslip->SSNIT = $ssnit;
    
        return $payslip;
    }

    public function index(Request $request, string $clientSlug)
    {
        $client = $this->resolveClient($clientSlug);

        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $date = Carbon::createFromFormat('Y-m', $month);
        $formattedMonth = $date->format('F Y');
        $tableName = $this->getTableName($client, $date);

        if (!Schema::hasTable($tableName)) {
            return Inertia::render('Payslips/Index', [
                'clientSlug'     => $clientSlug,
                'client'         => $client,
                'month'          => $month,
                'formattedMonth' => $formattedMonth,
                'employees'      => collect(),
                'flash'          => ['error' => "No payslip data available for {$formattedMonth}"],
            ]);
        }

        [$staffIdField, $nameField] = $this->detectColumns($tableName, $client);

        $employees = DB::table($tableName)
            ->select(
                DB::raw("CAST(`{$staffIdField}` AS CHAR) as staff_id"),
                DB::raw("COALESCE(CAST(`{$nameField}` AS CHAR), '-') as name")
            )
            ->orderBy($nameField)
            ->get();

        return Inertia::render('Payslips/Index', [
            'clientSlug'     => $clientSlug,
            'client'         => $client,
            'month'          => $month,
            'formattedMonth' => $formattedMonth,
            'employees'      => $employees,
        ]);
    }

    public function view(Request $request, string $clientSlug, string $month, string $staffId)
    {
        $client = $this->resolveClient($clientSlug);
        $date = Carbon::createFromFormat('Y-m', $month);
        $formattedMonth = $date->format('F Y');
        $tableName = $this->getTableName($client, $date);

        if (!Schema::hasTable($tableName)) {
            return Inertia::render('Payslips/Index', [
                'clientSlug'     => $clientSlug,
                'client'         => $client,
                'month'          => $month,
                'formattedMonth' => $formattedMonth,
                'employees'      => collect(),
                'flash'          => ['error' => "No data for {$formattedMonth}"],
            ]);
        }

        [$staffIdField, $nameField] = $this->detectColumns($tableName, $client);

        $payslip = DB::table($tableName)
            ->where($staffIdField, $staffId)
            ->first();

        if (!$payslip) {
            $employees = DB::table($tableName)
                ->select(
                    DB::raw("`{$staffIdField}` as staff_id"),
                    DB::raw("`{$nameField}` as name")
                )
                ->orderBy('name')
                ->get();

            return Inertia::render('Payslips/Index', [
                'clientSlug'     => $clientSlug,
                'client'         => $client,
                'month'          => $month,
                'formattedMonth' => $formattedMonth,
                'employees'      => $employees,
                'flash'          => ['error' => "No payslip found for Staff ID: {$staffId}"],
            ]);
        }

        $ssnit = DB::table('ssnit')->where('staffid', $staffId)->value('ssnit1a') ?? '-';
        $payslip = $this->normalizePayslip($payslip, $client, $ssnit);

        $employees = DB::table($tableName)
            ->select(
                DB::raw("`{$staffIdField}` as staff_id"),
                DB::raw("`{$nameField}` as name")
            )
            ->orderBy('name')
            ->get();

        return Inertia::render('Payslips/Index', [
            'clientSlug'     => $clientSlug,
            'client'         => $client,
            'payslip'        => $payslip,
            'month'          => $month,
            'formattedMonth' => $formattedMonth,
            'employees'      => $employees,
        ]);
    }

    public function publicView(Request $request, string $clientSlug, string $month, string $staffId)
    {
        return $this->generatePayslipPdf($request, $clientSlug, $month, $staffId, false);
    }

    public function download(Request $request, string $clientSlug, string $month, string $staffId)
    {
        $isDownload = $request->boolean('download', true) && !$request->has('stream');
        return $this->generatePayslipPdf($request, $clientSlug, $month, $staffId, $isDownload);
    }

    private function generatePayslipPdf(Request $request, string $clientSlug, string $month, string $staffId, bool $isDownload = false)
    {
        $client = $this->resolveClient($clientSlug);
        $date = Carbon::createFromFormat('Y-m', $month);
        $formattedMonth = $date->format('F Y');
        $tableName = $this->getTableName($client, $date);

        if (!Schema::hasTable($tableName)) {
            return response()->json(['error' => "No payroll data available for {$formattedMonth}"], 404);
        }

        [$staffIdField, $nameField] = $this->detectColumns($tableName, $client);

        $payslip = DB::table($tableName)
            ->where($staffIdField, $staffId)
            ->first();

        if (!$payslip) {
            return response()->json(['error' => 'Payslip not found'], 404);
        }

        $ssnit = DB::table('ssnit')->where('staffid', $staffId)->value('ssnit1a') ?? '-';

        $payslip = $this->normalizePayslip($payslip, $client, $ssnit);

        $bankRecord = DB::table('bankdetails')->where('employid', $staffId)->first();

        $bankData = [
            'bankname' => (!empty(data_get($bankRecord, 'bank')) && data_get($bankRecord, 'bank') !== '-') ? data_get($bankRecord, 'bank') : ($payslip->bank ?? '-'),
            'name'     => (!empty(data_get($bankRecord, 'name')) && data_get($bankRecord, 'name') !== '-') ? data_get($bankRecord, 'name') : ($payslip->name ?? '-'),
            'alcnum'   => (!empty(data_get($bankRecord, 'account')) && data_get($bankRecord, 'account') !== '-') ? data_get($bankRecord, 'account') : ($payslip->accountnumber ?? '-'),
            'branch'   => (!empty(data_get($bankRecord, 'branch')) && data_get($bankRecord, 'branch') !== '-') ? data_get($bankRecord, 'branch') : ($payslip->branch ?? '-'),
        ];

        $view = $this->resolvePayslipView($client);

        $pdf = Pdf::loadView($view, [
            'payslip'        => $payslip,
            'formattedMonth' => $formattedMonth,
            'ssnit'          => $ssnit,
            'bank'           => $bankData,
        ]);

        $safeStaffId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $staffId);
        $filename = "{$client->short_name}_Payslip_{$safeStaffId}_{$month}.pdf";

        if ($isDownload || $request->query('download') === '1') {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }

    public function sendEmail(string $clientSlug, Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        $diagLog = function (string $msg) {
            Log::channel('single')->error('[PAYSLIP-EMAIL] ' . $msg);
        };

        try {
            $client = $this->resolveClient($clientSlug);

            $month    = $request->input('month');
            $staffIds = $request->input('staff_ids', []);

            if (empty($staffIds)) {
                $diagLog("No staff_ids provided. Payload: " . json_encode($request->all()));
                return response()->json([
                    'success' => false,
                    'sent' => 0,
                    'failed' => 0,
                    'message' => 'No employees selected',
                ], 200);
            }

            $date           = Carbon::createFromFormat('Y-m', $month);
            $formattedMonth = $date->format('F Y');
            $tableName      = $this->getTableName($client, $date);

            if (!Schema::hasTable($tableName)) {
                $diagLog("Table {$tableName} does not exist for client {$clientSlug}, month {$month}");
                return response()->json([
                    'success' => false,
                    'sent' => 0,
                    'failed' => count($staffIds),
                    'message' => "No data for {$formattedMonth}",
                ], 200);
            }

            [$staffIdField, $nameField] = $this->detectColumns($tableName, $client);

            $view = $this->resolvePayslipView($client);

            $payslips = DB::table($tableName)
                ->whereIn($staffIdField, $staffIds)
                ->get()
                ->keyBy($staffIdField);

            $employees = DB::table('newemployeedata')
                ->whereIn('staffid', $staffIds)
                ->select('staffid', 'employeename', 'email')
                ->get()
                ->keyBy('staffid');

            $ssnits = DB::table('ssnit')
                ->whereIn('staffid', $staffIds)
                ->pluck('ssnit1a', 'staffid');

            $banks = DB::table('bankdetails')
                ->whereIn('employid', $staffIds)
                ->get()
                ->keyBy('employid');

            $sentCount    = 0;
            $failedCount  = 0;
            $noEmailCount = 0;
            $details      = [];

            foreach ($staffIds as $id) {
                $payslip  = $payslips->get($id);
                $employee = $employees->get($id);

                if (!$payslip) {
                    $diagLog("No payslip row for staff ID {$id} in table {$tableName}");
                    $details[] = ['id' => $id, 'status' => 'failed', 'reason' => 'no_payslip'];
                    $failedCount++;
                    continue;
                }

                $email = $employee?->email ?? null;
                if (!$email) {
                    $diagLog("No email on file for staff ID {$id} (employee record " . ($employee ? 'found, email blank' : 'not found in newemployeedata') . ")");
                    $details[] = ['id' => $id, 'status' => 'no_email', 'reason' => $employee ? 'blank_email' : 'no_employee_record'];
                    $noEmailCount++;
                    continue;
                }

                $name       = $employee?->employeename ?? 'Employee';
                $bankRecord = $banks->get($id);

                $bankData = [
                    'bankname' => data_get($bankRecord, 'bank',    '-'),
                    'name'     => data_get($bankRecord, 'name',    '-'),
                    'alcnum'   => data_get($bankRecord, 'account', '-'),
                    'branch'   => data_get($bankRecord, 'branch',  '-'),
                ];

                $payslip = $this->normalizePayslip($payslip, $client, $ssnits->get($id) ?? '-');

                try {
                    $pdf = Pdf::loadView($view, [
                        'payslip'        => $payslip,
                        'formattedMonth' => $formattedMonth,
                        'ssnit'          => $ssnits->get($id) ?? '-',
                        'bank'           => $bankData,
                    ]);

                    $pdfContent = $pdf->output();

                    $downloadUrl = route('payslips.download', [
                        'client'  => $clientSlug,
                        'month'   => $month,
                        'staffId' => $id,
                    ]);

                    $emailBody = "Dear {$name},\n\nYour {$client->short_name} payslip for {$formattedMonth} is ready.\n\nStaff ID: {$id}\nMonth: {$formattedMonth}\n\nPlease find your PDF payslip attached to this email.\n\nYou can also download your payslip directly:\n{$downloadUrl}\n\nThank you.";

                    Mail::raw(
                        $emailBody,
                        function ($message) use ($email, $name, $client, $formattedMonth, $pdfContent, $id, $date) {
                            $message->to($email)
                                ->subject("[Staff ID: {$id}] {$client->short_name} Payslip - {$formattedMonth}")
                                ->attachData(
                                    $pdfContent,
                                    "{$id}_{$client->short_name}_Payslip_{$date->format('Y-m')}.pdf",
                                    ['mime' => 'application/pdf']
                                );
                        }
                    );

                    $sentCount++;
                    $details[] = ['id' => $id, 'status' => 'sent'];

                    unset($pdf, $pdfContent);
                    gc_collect_cycles();

                } catch (\Throwable $e) {
                    $diagLog("Email FAILED for {$email} (Staff ID {$id}): " . $e->getMessage() . " | " . $e->getFile() . ':' . $e->getLine());
                    $details[] = ['id' => $id, 'status' => 'failed', 'reason' => $e->getMessage()];
                    $failedCount++;
                }
            }

            $diagLog("Batch complete for {$clientSlug} {$month}: sent={$sentCount} noEmail={$noEmailCount} failed={$failedCount} staffIds=" . implode(',', $staffIds));

            return response()->json([
                'success'      => $failedCount === 0,
                'sent'         => $sentCount,
                'no_email'     => $noEmailCount,
                'failed'       => $failedCount,
                'message'      => "Sent {$sentCount}, {$noEmailCount} skipped (no email), {$failedCount} failed.",
                'details'      => $details,
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('single')->error('[PAYSLIP-EMAIL] FATAL: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());

            return response()->json([
                'success' => false,
                'sent'    => 0,
                'failed'  => count($request->input('staff_ids', [])),
                'message' => 'Server error: ' . $e->getMessage(),
            ], 200);
        }
    }

    public function sendSms(string $clientSlug, Request $request)
    {
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        try {
            $client = $this->resolveClient($clientSlug);

            $month    = $request->input('month');
            $staffIds = array_values(array_filter((array) $request->input('staff_ids', [])));

            if (empty($staffIds)) {
                return response()->json([
                    'success'  => false,
                    'sent'     => 0,
                    'no_phone' => 0,
                    'failed'   => 0,
                    'message'  => 'No employees selected',
                ], 200);
            }

            $date           = Carbon::createFromFormat('Y-m', $month);
            $formattedMonth = $date->format('F Y');

            $contacts = DB::table('employeecontact')
                ->whereIn('staffid', $staffIds)
                ->pluck('phone', 'staffid');

            $employees = DB::table('newemployeedata')
                ->whereIn('staffid', $staffIds)
                ->get()
                ->keyBy('staffid');

            $apiKey   = (string) config('services.mnotify.api_key', config('services.mnotify.key'));
            $senderId = (string) config('services.mnotify.sender_id', 'SBPAFRICA');

            // For Twellium GH, fetch PIN codes from userstwi table
            $isTwellium = $client->client_dbname === 'twellium';
            $pinCodes   = collect();
            if ($isTwellium) {
                $pinCodes = DB::table('userstwi')
                    ->whereIn('staff_id', $staffIds)
                    ->pluck('password', 'staff_id');
            }

            $sentCount    = 0;
            $failedCount  = 0;
            $noPhoneCount = 0;
            $details      = [];

            foreach ($staffIds as $id) {
                $employee = $employees->get($id);
                $phone = $contacts->get($id)
                    ?? $employee?->phone
                    ?? $employee?->phonenumber
                    ?? $employee?->contact
                    ?? null;

                if (!$phone) {
                    $noPhoneCount++;
                    $details[] = ['id' => $id, 'status' => 'no_phone', 'reason' => 'No phone number on file'];
                    continue;
                }

                $cleanPhone = preg_replace('/\D/', '', (string)$phone);
                if (str_starts_with($cleanPhone, '0') && strlen($cleanPhone) === 10) {
                    $formattedPhone = '+233' . substr($cleanPhone, 1);
                } elseif (str_starts_with($cleanPhone, '233') && strlen($cleanPhone) === 12) {
                    $formattedPhone = '+' . $cleanPhone;
                } else {
                    $formattedPhone = '+' . $cleanPhone;
                }

                $name = $employee?->employeename ?? 'Employee';
                $downloadUrl = route('payslips.download', [
                    'client'  => $clientSlug,
                    'month'   => $month,
                    'staffId' => $id,
                ]);

                $msgText = "Dear {$name}, your {$client->short_name} payslip for {$formattedMonth} is ready.\nStaff ID: {$id}";

                // Append PIN code for Twellium GH employees
                if ($isTwellium && $pinCodes->has($id)) {
                    $msgText .= "\nPIN: {$pinCodes->get($id)}";
                }

                $msgText .= "\nDownload Payslip: {$downloadUrl}";

                try {
                    $response = Http::withoutVerifying()->timeout(15)->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept'       => 'application/json',
                    ])->post("https://api.mnotify.com/api/sms/quick?key=" . urlencode($apiKey), [
                        'recipient'     => [$formattedPhone],
                        'sender'        => $senderId,
                        'message'       => $msgText,
                        'is_schedule'   => false,
                        'schedule_date' => '',
                    ]);

                    if ($response->successful()) {
                        $data = $response->json();
                        $status = strtolower((string)($data['status'] ?? ''));
                        $code = (string)($data['code'] ?? ($data['response_code'] ?? ''));
                        if ($status === 'success' || $code === '1000' || $code === '200' || $code === '2000') {
                            $sentCount++;
                            $details[] = ['id' => $id, 'status' => 'sent'];
                            continue;
                        }
                    }

                    // Fallback: legacy HTTP endpoint
                    $fallback = Http::withoutVerifying()->timeout(15)->get('http://apps.mnotify.net/smsapi', [
                        'key'       => $apiKey,
                        'to'        => $formattedPhone,
                        'msg'       => $msgText,
                        'sender_id' => $senderId,
                    ]);

                    if ($fallback->successful() || str_contains(strtolower($fallback->body()), '1000') || str_contains(strtolower($fallback->body()), 'success')) {
                        $sentCount++;
                        $details[] = ['id' => $id, 'status' => 'sent'];
                    } else {
                        $failedCount++;
                        $details[] = ['id' => $id, 'status' => 'failed', 'reason' => 'SMS gateway returned error'];
                    }
                } catch (\Throwable $e) {
                    $failedCount++;
                    $details[] = ['id' => $id, 'status' => 'failed', 'reason' => $e->getMessage()];
                }
            }

            return response()->json([
                'success'  => $failedCount === 0,
                'sent'     => $sentCount,
                'no_phone' => $noPhoneCount,
                'failed'   => $failedCount,
                'message'  => "Sent {$sentCount} SMS, {$noPhoneCount} skipped (no phone), {$failedCount} failed.",
                'details'  => $details,
            ], 200);

        } catch (\Throwable $e) {
            Log::channel('single')->error('[PAYSLIP-SMS] FATAL: ' . $e->getMessage());

            return response()->json([
                'success'  => false,
                'sent'     => 0,
                'no_phone' => 0,
                'failed'   => count($request->input('staff_ids', [])),
                'message'  => 'Server error: ' . $e->getMessage(),
            ], 200);
        }
    }

    public function downloadMultiple(string $clientSlug, Request $request)
    {
        set_time_limit(600); // Allow up to 10 minutes execution for large batches
        ini_set('memory_limit', '1024M');

        try {
            $mode    = $request->input('mode', 'direct');
            $batchId = (string) $request->input('batch_id', '');
            $month   = $request->input('month');

            if (!$month) {
                return response()->json(['error' => 'Month is required'], 400);
            }

            $client    = $this->resolveClient($clientSlug);
            $safeShort = preg_replace('/[^A-Za-z0-9_-]/', '_', $client->short_name);

            // ---------- DIRECT MODE: Generate all PDFs and stream ZIP in 1 request ----------
            if ($mode === 'direct' || empty($batchId)) {
                $staffIds = array_values(array_filter((array) $request->input('staff_ids', [])));
                if (empty($staffIds)) {
                    return response()->json(['error' => 'No employees selected'], 400);
                }

                $date           = Carbon::createFromFormat('Y-m', $month);
                $formattedMonth = $date->format('F Y');
                $tableName      = $this->getTableName($client, $date);

                if (!Schema::hasTable($tableName)) {
                    return response()->json(['error' => "No payroll data found for {$formattedMonth}"], 404);
                }

                [$staffIdField] = $this->detectColumns($tableName, $client);
                $view           = $this->resolvePayslipView($client);

                $payslips = DB::table($tableName)->whereIn($staffIdField, $staffIds)->get()->keyBy($staffIdField);
                $ssnits   = DB::table('ssnit')->whereIn('staffid', $staffIds)->pluck('ssnit1a', 'staffid');
                $banks    = DB::table('bankdetails')->whereIn('employid', $staffIds)->get()->keyBy('employid');

                $tempDir = storage_path('app/tmp');
                if (!is_dir($tempDir)) {
                    @mkdir($tempDir, 0775, true);
                }

                $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'payslips_direct_' . uniqid() . '.zip';
                $zip     = new ZipArchive();

                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    return response()->json(['error' => 'Cannot create temporary ZIP file.'], 500);
                }

                $generated = 0;

                foreach ($staffIds as $id) {
                    $payslip = $payslips->get($id);
                    if (!$payslip) continue;

                    $ssnit   = $ssnits->get($id) ?? '-';
                    $payslip = $this->normalizePayslip($payslip, $client, $ssnit);
                    $bank    = $banks->get($id);

                    try {
                        $pdf = Pdf::loadView($view, [
                            'payslip'        => $payslip,
                            'formattedMonth' => $formattedMonth,
                            'ssnit'          => $ssnit,
                            'bank'           => [
                                'bankname' => (!empty(data_get($bank, 'bank')) && data_get($bank, 'bank') !== '-') ? data_get($bank, 'bank') : ($payslip->bank ?? '-'),
                                'name'     => (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') ? data_get($bank, 'name') : ($payslip->name ?? '-'),
                                'alcnum'   => (!empty(data_get($bank, 'account')) && data_get($bank, 'account') !== '-') ? data_get($bank, 'account') : ($payslip->accountnumber ?? '-'),
                                'branch'   => (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') ? data_get($bank, 'branch') : ($payslip->branch ?? '-'),
                            ],
                        ]);

                        $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $id);
                        $zip->addFromString(
                            "{$safeShort}_Payslip_{$safeId}_{$month}.pdf",
                            $pdf->output()
                        );
                        $generated++;

                        unset($pdf);
                        gc_collect_cycles();
                    } catch (\Throwable $e) {
                        Log::channel('single')->error(
                            "[PAYSLIP-BULK-DOWNLOAD] Direct PDF failed for {$id}: " . $e->getMessage() .
                            ' | ' . $e->getFile() . ':' . $e->getLine()
                        );
                    }
                }

                $zip->close();

                if ($generated === 0 || !file_exists($zipPath) || filesize($zipPath) === 0) {
                    if (file_exists($zipPath)) @unlink($zipPath);
                    return response()->json(['error' => 'No payslips could be generated for the selected employees.'], 500);
                }

                return response()->download($zipPath, "{$safeShort}_Payslips_{$month}.zip", [
                    'Content-Type' => 'application/zip',
                ])->deleteFileAfterSend(true);
            }

            // batch_id validation for chunked batching mode
            if (!preg_match('/^[A-Za-z0-9\-]{8,64}$/', $batchId)) {
                return response()->json(['error' => 'Invalid batch id'], 400);
            }

            $root     = storage_path('app/payslip_batches');
            $batchDir = $root . DIRECTORY_SEPARATOR . $request->user()->id . '_' . $batchId;

            // ---------- FINALIZE: zip everything generated so far ----------
            if ($mode === 'finalize') {
                $files = is_dir($batchDir) ? glob($batchDir . DIRECTORY_SEPARATOR . '*.pdf') : [];

                if (empty($files)) {
                    return response()->json(['error' => 'No generated payslips found for this batch'], 404);
                }

                $tempDir = storage_path('app/tmp');
                if (!is_dir($tempDir)) {
                    @mkdir($tempDir, 0775, true);
                }
                $zipPath = $tempDir . DIRECTORY_SEPARATOR . 'payslips_' . uniqid() . '.zip';
                $zip     = new ZipArchive();

                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    return response()->json(['error' => 'Cannot create temporary ZIP file.'], 500);
                }

                foreach ($files as $file) {
                    $zip->addFile($file, basename($file));
                }
                $zip->close();

                foreach ($files as $file) {
                    @unlink($file);
                }
                @rmdir($batchDir);

                return response()->download($zipPath, "{$safeShort}_Payslips_{$month}.zip", [
                    'Content-Type' => 'application/zip',
                ])->deleteFileAfterSend(true);
            }

            // ---------- BATCH: generate PDFs and save them, return JSON ----------
            $staffIds = array_values(array_filter((array) $request->input('staff_ids', [])));
            if (empty($staffIds)) {
                return response()->json(['error' => 'No employees selected'], 400);
            }

            $date           = Carbon::createFromFormat('Y-m', $month);
            $formattedMonth = $date->format('F Y');
            $tableName      = $this->getTableName($client, $date);

            if (!Schema::hasTable($tableName)) {
                return response()->json(['error' => "No payroll data found for {$formattedMonth}"], 404);
            }

            [$staffIdField] = $this->detectColumns($tableName, $client);
            $view           = $this->resolvePayslipView($client);

            $payslips = DB::table($tableName)->whereIn($staffIdField, $staffIds)->get()->keyBy($staffIdField);
            $ssnits   = DB::table('ssnit')->whereIn('staffid', $staffIds)->pluck('ssnit1a', 'staffid');
            $banks    = DB::table('bankdetails')->whereIn('employid', $staffIds)->get()->keyBy('employid');

            if (!is_dir($batchDir) && !@mkdir($batchDir, 0775, true) && !is_dir($batchDir)) {
                return response()->json(['error' => 'Cannot create temp folder. Check server permissions.'], 500);
            }

            // Clean up abandoned batch folders older than 2 hours
            foreach (glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
                if (filemtime($dir) < time() - 7200) {
                    array_map('unlink', glob($dir . DIRECTORY_SEPARATOR . '*') ?: []);
                    @rmdir($dir);
                }
            }

            $generated = 0;
            $failed    = [];

            foreach ($staffIds as $id) {
                $payslip = $payslips->get($id);
                if (!$payslip) {
                    $failed[] = $id;
                    continue;
                }

                $ssnit   = $ssnits->get($id) ?? '-';
                $payslip = $this->normalizePayslip($payslip, $client, $ssnit);
                $bank    = $banks->get($id);

                try {
                    $pdf = Pdf::loadView($view, [
                        'payslip'        => $payslip,
                        'formattedMonth' => $formattedMonth,
                        'ssnit'          => $ssnit,
                        'bank'           => [
                            'bankname' => (!empty(data_get($bank, 'bank')) && data_get($bank, 'bank') !== '-') ? data_get($bank, 'bank') : ($payslip->bank ?? '-'),
                            'name'     => (!empty(data_get($bank, 'name')) && data_get($bank, 'name') !== '-') ? data_get($bank, 'name') : ($payslip->name ?? '-'),
                            'alcnum'   => (!empty(data_get($bank, 'account')) && data_get($bank, 'account') !== '-') ? data_get($bank, 'account') : ($payslip->accountnumber ?? '-'),
                            'branch'   => (!empty(data_get($bank, 'branch')) && data_get($bank, 'branch') !== '-') ? data_get($bank, 'branch') : ($payslip->branch ?? '-'),
                        ],
                    ]);

                    $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $id);
                    file_put_contents(
                        $batchDir . DIRECTORY_SEPARATOR . "{$safeShort}_Payslip_{$safeId}_{$month}.pdf",
                        $pdf->output()
                    );
                    $generated++;

                    unset($pdf);
                    gc_collect_cycles();
                } catch (\Throwable $e) {
                    Log::channel('single')->error(
                        "[PAYSLIP-BULK-DOWNLOAD] PDF failed for {$id}: " . $e->getMessage() .
                        ' | ' . $e->getFile() . ':' . $e->getLine()
                    );
                    $failed[] = $id;
                }
            }

            return response()->json(['generated' => $generated, 'failed' => $failed]);

        } catch (\Throwable $e) {
            Log::channel('single')->error(
                '[PAYSLIP-BULK-DOWNLOAD] FATAL: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine()
            );
            return response()->json(['error' => 'Bulk download failed: ' . $e->getMessage()], 500);
        }
    }
}
