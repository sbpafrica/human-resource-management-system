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
        $dbName = strtolower(trim((string)($client->client_dbname ?? '')));
        $slug = strtolower(trim((string)($client->slug ?? '')));
        $shortName = strtolower(trim((string)($client->short_name ?? '')));

        $baseAliases = match ($dbName) {
            'ofiexternal', 'hygieneexternal' => ['hygieneexternal', 'ofiexternal'],
            'ofibwh', 'olambwh', 'bwh'       => ['olambwh', 'ofibwh'],
            'ofitemp', 'olamtem'             => ['olamtem', 'ofitemp'],
            'nyonkopa'                       => ['nyonkopa'],
            'ofioffload', 'offload'          => ['offload', 'ofioffload'],
            'ofihygiene', 'olamhygiene', 'hygiene' => ['olamhygiene', 'hygiene', 'ofihygiene'],
            'fon', 'fonpackage'              => ['fonpackage', 'fon'],
            'century', 'centurypay'          => ['centurypay', 'century'],
            'tecno', 'transsiontec'          => ['transsiontec', 'tecno', 'unitycorp'],
            'transsion', 'goodease'          => ['transsion', 'goodease'],
            'driver', 'driverssnit', 'drivers' => ['driverssnit', 'driver', 'drivers', 'melcomdriver'],
            'jkay', 'jaykay'                 => ['jkay', 'jaykay'],
            'transburkina', 'transsionbfa'   => ['transburkina', 'transsionbfa', 'transsion-bfa'],
            'pz', 'pzcussons'                => ['pz', 'pzcussons'],
            'shefer', 'sheferplastics'       => ['shefer', 'sheferplastics'],
            'twelliumaccra'                  => ['twelliumaccra', 'twellium_accra'],
            'civ', 'civbunge'                => ['civ', 'civbunge'],
            default                          => array_values(array_unique(array_filter([
                $dbName,
                str_replace(['-', '_', ' '], '', $dbName),
                $slug,
                str_replace(['-', '_', ' '], '', $slug),
                $shortName,
                str_replace(['-', '_', ' '], '', $shortName),
            ]))),
        };

        if (str_contains($dbName, 'jaykay') || str_contains($slug, 'jaykay') || str_contains($shortName, 'jay kay') || str_contains($dbName, 'jkay')) {
            $baseAliases = array_values(array_unique(array_merge(['jkay', 'jaykay'], $baseAliases)));
        }

        $fy = strtolower($date->format('Fy')); // e.g. september26
        $my = strtolower($date->format('My')); // e.g. sep26
        $fY = strtolower($date->format('FY')); // e.g. september2026
        $mY = strtolower($date->format('MY')); // e.g. sep2026
        $f  = strtolower($date->format('F'));  // e.g. september
        $m  = strtolower($date->format('M'));  // e.g. sep

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

        // Dynamic database scan fallback: check all tables matching client base and month tokens
        try {
            $allTables = DB::select("SHOW TABLES");
            if (!empty($allTables)) {
                $firstProp = array_key_first((array)$allTables[0]);
                $tableList = array_map(fn($r) => strtolower((string)$r->$firstProp), $allTables);

                foreach ($baseAliases as $base) {
                    $cleanBase = strtolower(str_replace(['-', '_'], '', $base));
                    foreach ($tableList as $t) {
                        $cleanT = str_replace(['-', '_'], '', $t);
                        if (str_contains($cleanT, $cleanBase) && (str_contains($cleanT, $fy) || str_contains($cleanT, $my) || str_contains($cleanT, $f))) {
                            return $t;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[PAYSLIP-TABLE-DISCOVERY] Failed SHOW TABLES dynamic scan: " . $e->getMessage());
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
        $nameCandidates = ['name', 'names', 'empoyeename', 'employeename', 'employee_name', 'fullname', 'name1', 'full_name'];
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
        if (str_contains($dbName, 'ofitemp') || str_contains($slug, 'ofi-tempering') || str_contains($slug, 'ofitemp') || str_contains($dbName, 'olamtem') || str_contains($shortName, 'tempering')) {
            return 'payslip.ofitemp-pdf';
        }
        if (str_contains($dbName, 'ofibwh') || str_contains($slug, 'ofi-bean-warehouse') || str_contains($slug, 'ofibwh') || str_contains($dbName, 'olambwh') || str_contains($shortName, 'bean wh')) {
            return 'payslip.ofibwh-pdf';
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
    
        // Freeze raw source data so mutated $payslip properties (e.g. weekendOT hours) don't shadow raw DB fields
        $rawDbSource = is_array($payslip) ? $payslip : (array) $payslip;
        $getP = function($key) use ($rawDbSource) {
            // 1. Direct match
            if (isset($rawDbSource[$key])) {
                return $rawDbSource[$key];
            }

            $normalize = function($s) {
                return str_replace([' ', '_', '-', 'ghc', '%', 'percent', '.', '&', 'and'], '', strtolower((string)$s));
            };

            // 2. Case-insensitive / normalized match
            $normalizedSearch = $normalize($key);
            foreach ($rawDbSource as $k => $val) {
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
            ?? $getP('work_location')
            ?? $getP('worklocation')
            ?? $getP('location')
            ?? $getP('shop') 
            ?? $getP('shopname') 
            ?? $getP('department1') 
            ?? $getP('workarea') 
            ?? $getP('1stlevel') 
            ?? ($getP('city') ?? '-');
    
        // ---------- BASIC & WORK DAYS ----------
        $daysWorked = $getP('days_worked')
            ?? $getP('daysworked')
            ?? $getP('noofdayspresent')
            ?? $getP('workdays') 
            ?? $getP('numberofdaysworked') 
            ?? $getP('actualdays') 
            ?? $getP('noofdays') 
            ?? $getP('total1')
            ?? $getP('actualwork') 
            ?? $getP('total_weekdays') 
            ?? $getP('legalworkdays') 
            ?? 0;
        $payslip->workdays = is_numeric($daysWorked) ? (float)$daysWorked : $daysWorked;

        $rate = (float)(
            $getP('daily_rate') 
            ?? $getP('dailyrate') 
            ?? $getP('rate') 
            ?? $getP('rate_ghc') 
            ?? $getP('weekdayrate')
            ?? 0
        );
        $payslip->rate = $rate;
        $payslip->daily_rate = $rate;

        $basicEarned = $getP('earned_basic_salary')
            ?? $getP('earnedbasicsalary')
            ?? $getP('basicsalaryearned') 
            ?? $getP('basicsalaryearnedghc') 
            ?? $getP('basic_earned') 
            ?? $getP('basicentitlement')
            ?? $getP('total2') 
            ?? $getP('base_salary') 
            ?? $getP('basesalary') 
            ?? null;

        $rawBasic = $getP('base_salary') 
            ?? $getP('basesalary') 
            ?? $getP('basic_salary')
            ?? $getP('basicsalary') 
            ?? $getP('basicsalaryghc') 
            ?? $getP('basic') 
            ?? $getP('actualbasic') 
            ?? null;

        $hasFixedBasic = ($rawBasic !== null && is_numeric($rawBasic) && (float)$rawBasic > 0);
        $payslip->has_basic_salary = $hasFixedBasic;
        $payslip->is_casual        = !$hasFixedBasic;
        $payslip->raw_basic        = $hasFixedBasic ? (float)$rawBasic : 0;

        $basicBase = $hasFixedBasic ? (float)$rawBasic : null;

        // Support for OFI Bean Warehouse (olambwh) workers who are hourly/tonnage based without a fixed base salary
        $amtVal = $getP('amt');
        if (($basicEarned === null || (float)$basicEarned == 0) && ($basicBase === null || (float)$basicBase == 0) && $amtVal !== null && (float)$amtVal > 0) {
            $basicEarned = round((float)$amtVal, 2);
            $basicBase   = $basicEarned;
        }

        // If basic earned is absent but a base salary is already defined, default basic earned to base salary
        if (($basicEarned === null || (float)$basicEarned == 0) && $basicBase !== null && (float)$basicBase > 0) {
            $basicEarned = (float)$basicBase;
        }

        // If basic earned is absent but rate and workdays are present (and no fixed basic), calculate basic earned
        if (($basicEarned === null || (float)$basicEarned == 0) && ($basicBase === null || (float)$basicBase == 0) && $rate > 0 && (float)$payslip->workdays > 0) {
            $basicEarned = round($rate * (float)$payslip->workdays, 2);
        }

        $earnedVal = (float)($basicEarned ?: ($basicBase ?: 0));
        $baseVal   = (float)($basicBase ?: ($basicEarned ?: ($rate ?: 0)));

        $payslip->BASIC_SALARY_GHC        = $hasFixedBasic ? $baseVal : 0;
        $payslip->BASIC_SALARY_EARNED_GHC = $earnedVal;
        $payslip->basic_earned            = $earnedVal;
        $payslip->basic                   = $hasFixedBasic ? $baseVal : $earnedVal;

        // Extra details for OFI BWH & Tempering
        $payslip->amt             = (float)($getP('amt') ?? 0);
        $payslip->amount          = (float)($getP('amount') ?? 0);
        $payslip->weekdayhr       = (float)($getP('weekdayhr') ?? 0);
        $payslip->weekdaytonage   = (float)($getP('weekdaytonage') ?? 0);
        $payslip->weekdayrate     = (float)($getP('weekdayrate') ?? 0);
        $payslip->weekdayothr     = (float)($getP('weekdayothr') ?? 0);
        $payslip->weekdayotrate   = (float)($getP('weekdayotrate') ?? 0);
        $payslip->weekdayotamt1   = (float)($getP('weekdayotamt1') ?? 0);
        $payslip->weekendhr       = (float)($getP('weekendhr') ?? 0);
        $payslip->weekendtonage   = (float)($getP('weekendtonage') ?? 0);
        $payslip->weekendrate     = (float)($getP('weekendrate') ?? 0);
        $payslip->total1          = (float)($getP('total1') ?? 0);
        $payslip->total2          = (float)($getP('total2') ?? 0);
        $payslip->totalamount     = (float)($getP('totalamount') ?? 0);

        // ---------- ALLOWANCES ----------
        $payslip->fuel_and_rent_allowance = (float)(
            $getP('fuel_and_rent_allowance') 
            ?? $getP('fuelandrentallowance') 
            ?? $getP('fuel_and_rent') 
            ?? $getP('f&tamount') 
            ?? $getP('ftamount') 
            ?? 0
        );
        $payslip->ftamount = $payslip->fuel_and_rent_allowance;
        $payslip->f_and_t_amount = $payslip->fuel_and_rent_allowance;
        $payslip->transport = (float)($getP('transport') ?? $getP('transportghc') ?? 0);
        $payslip->nightallowance = (float)($getP('nightallowance') ?? $getP('night_allowance') ?? 0);
        $payslip->night = (float)($getP('night') ?? 0);
        $payslip->medical = (float)($getP('medical') ?? $getP('medical_allowance') ?? 0);
        $payslip->leave1 = (float)($getP('leave1') ?? $getP('leave_allowance') ?? $getP('leavepay') ?? 0);

        // If table has employer total cost column (e.g. costofolam, cost_to_nyonkopa), medical and leave1 are employer costs
        if ($getP('costofolam') !== null || $getP('cost_to_nyonkopa') !== null || $getP('cost_to_company') !== null) {
            $payslip->medical = 0;
            $payslip->leave1  = 0;
        }

        $payslip->general_allowance = (float)($getP('allowance') ?? $getP('total_allowance') ?? 0);

        $payslip->allowance = (float) (
            $payslip->general_allowance 
            ?: ($payslip->fuel_and_rent_allowance 
                ?: ($payslip->transport 
                    ?: ($payslip->fuel_and_rent_allowance + $payslip->transport + $payslip->nightallowance + $payslip->medical + $payslip->leave1)))
        );

        // ---------- OVERTIME HOURS MAPPING ----------
        $normalOtHrs = $getP('weekdayovertimehr')
            ?? $getP('weekdayovertimehrs')
            ?? $getP('overtimehr')
            ?? $getP('weekdaysOT') 
            ?? $getP('Normal OT HRS') 
            ?? $getP('normal_ot_hrs') 
            ?? $getP('normalOThrs') 
            ?? $getP('otworkdays') 
            ?? $getP('weekdayothr')
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
            ?? $getP('weekendhr')
            ?? $getP('weekendtonage')
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
            ?? $getP('weekdayotamt1')
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
            ?? ($getP('weekendhr') !== null || $getP('weekendtonage') !== null ? $getP('amount') : null)
            ?? $getP('weekendot') 
            ?? 0;

        $holidayOtAmt = $getP('holidayovertimetotal')
            ?? $getP('holidayovertimeamount') 
            ?? $getP('holidayovertimeamountghc') 
            ?? $getP('holidayotamt') 
            ?? $getP('Holiday Overtime') 
            ?? $getP('holidayot') 
            ?? 0;
        
        $payslip->normalovertime   = (float)$normalOtAmt;
        $payslip->saturdayovertime = (float)$saturdayOtAmt;
        $payslip->holidayotamt     = (float)$holidayOtAmt;
        
        $totalOvertime = (float)$normalOtAmt + (float)$saturdayOtAmt + (float)$holidayOtAmt;
        $payslip->overtime = (float)($getP('overtime') ?? $getP('totalovertimeamount') ?? $getP('totalovertimeamountghc') ?? $totalOvertime);

        // ---------- DEDUCTIONS ----------
        $payslip->paye = (float) (
            $getP('totaltaxdeductions') 
            ?? $getP('totaltaxdeduction') 
            ?? $getP('totaltax') 
            ?? $getP('total_taxation') 
            ?? $getP('paye') 
            ?? $getP('payeghc') 
            ?? $getP('itsalaries') 
            ?? 0
        );

        $payslip->ssnitee = (float) (
            $getP('ssnitee') 
            ?? $getP('ssnit_ee_5_5') 
            ?? $getP('ssnitee55') 
            ?? $getP('ssnitee55percent') 
            ?? $getP('ssnit_ee_5_5_percent') 
            ?? $getP('ssnit_5_5_percent') 
            ?? $getP('ssnit_ee_0_5_percent') 
            ?? $getP('ssnitee05percent') 
            ?? $getP('ssf_13_percent') 
            ?? $getP('ssf') 
            ?? 0
        );

        // Petra
        $rawPetraFromDb = $getP('petra') ?? $getP('petra_5_percent') ?? $getP('petrafund') ?? $getP('tier3') ?? $getP('tier2') ?? null;
        if ($rawPetraFromDb === null && $getP('ssnitee') !== null && $getP('ssnit') !== null && is_numeric($getP('ssnit')) && (float)$getP('ssnit') > 0) {
            $rawPetraFromDb = $getP('ssnit');
        }
        if ($rawPetraFromDb !== null) {
            $payslip->petra = round((float)$rawPetraFromDb, 2);
        } else {
            $payslip->petra = 0;
        }
        $payslip->petra_5_percent = $payslip->petra;
        $payslip->PETRA_5         = $payslip->petra;
        $payslip->petrafund       = $payslip->petra;
        $payslip->tier2           = $payslip->petra;

        // Employee Provident Fund (PF)
        $rawPf = $getP('employeepf') 
            ?? $getP('employee_pf') 
            ?? $getP('employeeprovidentfund') 
            ?? $getP('providentfund') 
            ?? $getP('provident_fund') 
            ?? $getP('pf') 
            ?? 0;
        $payslip->employeepf     = round((float)$rawPf, 2);
        $payslip->provident_fund = $payslip->employeepf;

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

        $totalDeductionsVal = $getP('total_deductions')
            ?? $getP('totaldeductions')
            ?? $getP('totaldeduction')
            ?? $getP('total_deduction')
            ?? null;

        if ($totalDeductionsVal !== null && (float)$totalDeductionsVal > 0) {
            $payslip->totaldeductions = round((float)$totalDeductionsVal, 2);
        } else {
            $payslip->totaldeductions = round(
                (float)($payslip->ssnitee ?? 0)
                + (float)($payslip->paye ?? 0)
                + (float)($payslip->petra ?? 0)
                + (float)($payslip->employeepf ?? 0)
                + (float)($payslip->otherdeduction ?? 0)
                + (float)($payslip->overtimetax ?? 0)
                + (float)($getP('loan') ?? 0)
                + (float)($getP('leavededuction') ?? 0)
                + (float)($getP('annaultax') ?? 0)
                + (float)($getP('commissiontax') ?? $getP('commission_tax') ?? 0),
                2
            );
        }

        $payslip->total_deductions = $payslip->totaldeductions;
        $payslip->totaldeduction   = $payslip->totaldeductions;

        // ---------- TOTALS (GROSS & NET) ----------
        $netVal = (float) (
            $getP('take_home_pay') 
            ?? $getP('takehomepay') 
            ?? $getP('takehome') 
            ?? $getP('netpay') 
            ?? $getP('takehomepayghc') 
            ?? $getP('banktransfer') 
            ?? $getP('net_pay') 
            ?? $getP('actualnetpay') 
            ?? $getP('finalnetpay')
            ?? $getP('nap')
            ?? 0
        );

        $grossVal = (float) (
            $getP('gross_salary') 
            ?? $getP('grosssalary') 
            ?? $getP('grosssalaryghc') 
            ?? $getP('grosspay') 
            ?? $getP('gross_pay') 
            ?? $getP('totalgrosspay') 
            ?? $getP('grossnew')
            ?? $getP('totalearn')
            ?? $getP('gross')
            ?? 0
        );

        // For OFI BWH (olambwh), true gross is totalamount + f&tamount
        if ($grossVal == 0 && $getP('totalamount') !== null && ($getP('f&tamount') !== null || $getP('ftamount') !== null)) {
            $grossVal = (float)$getP('totalamount') + (float)($getP('f&tamount') ?? $getP('ftamount'));
        }

        // For OFI Tempering (olamtem), true gross is totalamount + transport + nightallowance
        if ($grossVal == 0 && $getP('totalamount') !== null && ($getP('rate') !== null || $getP('total2') !== null)) {
            $grossVal = (float)$getP('totalamount') + (float)($getP('transport') ?? 0) + (float)($getP('nightallowance') ?? 0);
        }

        if ($grossVal == 0) {
            $earningsSum = (float)($payslip->basic_earned ?: ($payslip->basic ?? 0))
                + (float)($payslip->fuel_and_rent_allowance ?? 0)
                + (float)($payslip->transport ?? 0)
                + (float)($payslip->nightallowance ?? 0)
                + (float)($payslip->medical ?? 0)
                + (float)($payslip->leave1 ?? 0)
                + (float)($payslip->general_allowance ?? 0)
                + (float)($payslip->overtime ?? 0)
                + (float)($getP('performancebonus') ?? $getP('bonus') ?? 0)
                + (float)($getP('otherincome') ?? 0)
                + (float)($getP('commission') ?? 0);

            if ($earningsSum > 0) {
                $grossVal = $earningsSum;
            } elseif ($netVal > 0 && $payslip->totaldeductions > 0) {
                $grossVal = $netVal + $payslip->totaldeductions;
            } else {
                $grossVal = (float)($getP('totalamount') ?? 0);
            }
        }

        // If totalamount was an intermediate subtotal (e.g. basic + OT) and netVal + deductions is higher:
        if ($netVal > 0 && $payslip->totaldeductions > 0 && $grossVal < $netVal) {
            $grossVal = round($netVal + $payslip->totaldeductions, 2);
        }

        $payslip->grosspay = round($grossVal, 2);

        if ($netVal == 0 && $payslip->grosspay > 0) {
            $netVal = max(0, round($payslip->grosspay - $payslip->totaldeductions, 2));
        }

        $payslip->netpay        = round($netVal, 2);
        $payslip->take_home_pay = $payslip->netpay;
        $payslip->takehome      = $payslip->netpay;

        // ---------- BANK & EXTRA INFO ----------
        $payslip->trust         = (float)($getP('trust') ?? 0);
        $payslip->taxable       = (float)($getP('taxableincome') ?? $getP('taxable') ?? 0);
        $payslip->bank          = $getP('bank') ?? $getP('bankname') ?? $getP('bank1') ?? null;
        $payslip->accountnumber = $getP('accountnumber') ?? $getP('account') ?? $getP('alcnum') ?? $getP('account1') ?? $getP('account2') ?? $getP('account_number') ?? null;
        $payslip->branch        = $getP('branch') ?? $getP('branch1') ?? null;
        $payslip->bankname      = $payslip->bank;
        $payslip->alcnum        = $payslip->accountnumber;
    
        // Preserve the SSNIT string identity number safely without overwriting data properties
        $rawSsnit = $getP('ssnit') ?? $getP('ssnit12') ?? $getP('ssnit2') ?? $getP('ssnit_number') ?? $getP('ssnitno') ?? $getP('ssnit1a');
        if ((!isset($ssnit) || $ssnit === '-' || empty($ssnit)) && !empty($rawSsnit)) {
            $ssnit = (string) $rawSsnit;
        }
        $payslip->ssnit_number = $ssnit;
        $payslip->SSNIT        = $ssnit;
        $payslip->ghcard       = $getP('ghcard1') ?? $getP('ghcard') ?? $getP('ghana_card') ?? $getP('ghanacard') ?? null;

        // Cross-template compatibility aliases for Jay Kay and custom templates
        $payslip->basic_salary              = (float)($getP('basic_salary') ?? $payslip->BASIC_SALARY_GHC ?? $payslip->basic);
        $payslip->earned_basic_salary       = (float)($getP('earned_basic_salary') ?? $payslip->basic_earned);
        $payslip->days_worked               = $getP('days_worked') ?? $getP('daysworked') ?? $payslip->workdays;
        $payslip->gross_salary              = $payslip->grosspay;
        $payslip->take_home_pay             = $payslip->netpay;
        $payslip->ssnit_ee                  = $payslip->ssnitee;
        $payslip->total_deductions          = $payslip->totaldeductions;
        $payslip->overtime_tax              = $payslip->overtimetax;
        $payslip->surcharge_or_student_loan = $payslip->otherdeduction;
        $payslip->absentee_days             = $getP('absentee_days') ?? $getP('absent_days') ?? $getP('absentdays') ?? 0;
        $payslip->absent_deductions         = (float)($getP('absent_deductions') ?? $getP('absentdeductions') ?? $getP('absentee_deductions') ?? 0);
        $payslip->night_allowance           = (float)($getP('night_allowance') ?? $payslip->nightallowance ?? 0);
        $payslip->saturdays_worked          = $getP('saturdays_worked') ?? $getP('saturdaysworked') ?? 0;
        $payslip->saturday_amount           = (float)($getP('saturday_amount') ?? $payslip->saturdayovertime ?? 0);
        $payslip->overtime_hours            = $getP('overtime_hours') ?? $getP('overtimehrs') ?? 0;
        $payslip->overtime_amount           = (float)($getP('overtime_amount') ?? $payslip->overtime ?? 0);
        $payslip->commission                = (float)($getP('commission') ?? 0);
        $payslip->commissiontax             = (float)($getP('commissiontax') ?? $getP('commission_tax') ?? 0);

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

        $bankRecord = DB::table('bankdetails')->where('employid', $staffId)->first();
        if ($bankRecord) {
            if (empty($payslip->bank) || $payslip->bank === '-') {
                $payslip->bank = (!empty($bankRecord->bank) && $bankRecord->bank !== '-') ? $bankRecord->bank : ($payslip->bank ?? '-');
                $payslip->bankname = $payslip->bank;
            }
            if (empty($payslip->accountnumber) || $payslip->accountnumber === '-') {
                $payslip->accountnumber = (!empty($bankRecord->account) && $bankRecord->account !== '-') ? $bankRecord->account : ($payslip->accountnumber ?? '-');
                $payslip->alcnum = $payslip->accountnumber;
            }
            if (empty($payslip->branch) || $payslip->branch === '-') {
                $payslip->branch = (!empty($bankRecord->branch) && $bankRecord->branch !== '-') ? $bankRecord->branch : ($payslip->branch ?? '-');
            }
        }

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
        try {
            $client = $this->resolveClient($clientSlug);
            $date = Carbon::parse($month);
            $formattedMonth = $date->format('F Y');
            $tableName = $this->getTableName($client, $date);

            if (!Schema::hasTable($tableName)) {
                Log::channel('single')->warning("[PAYSLIP-SINGLE] Table not found: {$tableName} for client {$clientSlug} and month {$month}");
                return response()->json(['error' => "No payroll data available for {$formattedMonth} (table '{$tableName}' not found)"], 404);
            }

            [$staffIdField, $nameField] = $this->detectColumns($tableName, $client);

            $payslip = DB::table($tableName)
                ->where($staffIdField, $staffId)
                ->first();

            if (!$payslip) {
                // Fallback: search with trimmed / lowercase staff ID
                $payslip = DB::table($tableName)
                    ->whereRaw("TRIM(LOWER(`{$staffIdField}`)) = ?", [trim(strtolower($staffId))])
                    ->first();
            }

            if (!$payslip) {
                return response()->json(['error' => "Payslip not found for employee '{$staffId}' in {$formattedMonth}"], 404);
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
        } catch (\Throwable $e) {
            Log::channel('single')->error("[PAYSLIP-SINGLE] Failed generating PDF for {$clientSlug}/{$staffId}: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
            return response()->json(['error' => 'Failed to generate payslip: ' . $e->getMessage()], 500);
        }
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
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        @ini_set('memory_limit', '1024M');
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', 'Off');

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
                // Parse staff IDs flexibly from standard array, JSON string, or 'all'
                $rawStaffIds = $request->input('staff_ids');
                if (empty($rawStaffIds) && $request->has('staff_ids_json')) {
                    $rawStaffIds = json_decode((string)$request->input('staff_ids_json'), true);
                }
                if (is_string($rawStaffIds)) {
                    $rawStaffIds = json_decode($rawStaffIds, true) ?: explode(',', $rawStaffIds);
                }
                $staffIds = array_values(array_filter((array) $rawStaffIds));

                $date           = Carbon::parse($month);
                $formattedMonth = $date->format('F Y');
                $tableName      = $this->getTableName($client, $date);

                if (!Schema::hasTable($tableName)) {
                    return response()->json(['error' => "No payroll data found for {$formattedMonth}"], 404);
                }

                [$staffIdField] = $this->detectColumns($tableName, $client);
                $view           = $this->resolvePayslipView($client);

                if (empty($staffIds) || $request->boolean('all')) {
                    $staffIds = DB::table($tableName)->pluck($staffIdField)->toArray();
                }

                if (empty($staffIds)) {
                    return response()->json(['error' => 'No employees selected'], 400);
                }

                $payslips = DB::table($tableName)->whereIn($staffIdField, $staffIds)->get()->keyBy($staffIdField);
                $ssnits   = DB::table('ssnit')->whereIn('staffid', $staffIds)->pluck('ssnit1a', 'staffid');
                $banks    = DB::table('bankdetails')->whereIn('employid', $staffIds)->get()->keyBy('employid');

                $logoPath = public_path('images/sbpafricalogo.png');
                $cachedLogoData = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;

                $tempDir = storage_path('app/tmp/bulk_' . uniqid());
                if (!is_dir($tempDir)) {
                    @mkdir($tempDir, 0775, true);
                }

                $zipPath = storage_path('app/tmp/payslips_direct_' . uniqid() . '.zip');
                $zip     = new ZipArchive();

                if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    return response()->json(['error' => 'Cannot create temporary ZIP file.'], 500);
                }

                $generated = 0;
                $tempPdfFiles = [];

                try {
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
                                'logoData'       => $cachedLogoData,
                            ])->setOptions([
                                'isRemoteEnabled' => false,
                                'isFontSubsettingEnabled' => false,
                                'defaultFont' => 'Helvetica',
                            ])->setPaper('a4', 'portrait');

                            $safeId = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $id);
                            $filename = "{$safeShort}_Payslip_{$safeId}_{$month}.pdf";
                            $pdfFilePath = $tempDir . DIRECTORY_SEPARATOR . $filename;

                            file_put_contents($pdfFilePath, $pdf->output());
                            $zip->addFile($pdfFilePath, $filename);
                            $tempPdfFiles[] = $pdfFilePath;
                            $generated++;

                            unset($pdf);
                            if ($generated % 25 === 0) {
                                gc_collect_cycles();
                            }
                        } catch (\Throwable $e) {
                            Log::channel('single')->error(
                                "[PAYSLIP-BULK-DOWNLOAD] Direct PDF failed for {$id}: " . $e->getMessage() .
                                ' | ' . $e->getFile() . ':' . $e->getLine()
                            );
                        }
                    }

                    $zip->close();
                } finally {
                    foreach ($tempPdfFiles as $file) {
                        if (file_exists($file)) @unlink($file);
                    }
                    if (is_dir($tempDir)) @rmdir($tempDir);
                }

                if ($generated === 0 || !file_exists($zipPath) || filesize($zipPath) === 0) {
                    if (file_exists($zipPath)) @unlink($zipPath);
                    return response()->json(['error' => 'No payslips could be generated for the selected employees.'], 500);
                }

                return response()->download($zipPath, "{$safeShort}_Payslips_{$month}.zip", [
                    'Content-Type'        => 'application/zip',
                    'Content-Disposition' => "attachment; filename=\"{$safeShort}_Payslips_{$month}.zip\"",
                    'Cache-Control'       => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma'              => 'no-cache',
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

            $date           = Carbon::parse($month);
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
