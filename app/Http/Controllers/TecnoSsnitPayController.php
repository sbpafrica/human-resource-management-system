<?php

namespace App\Http\Controllers;

use App\Models\Tecnopay;
use App\Models\Ssnit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use Illuminate\Support\Facades\Schema;

class TecnoSsnitPayController extends Controller
{
    private function resolveTableName(Carbon $date): ?string
    {
        $fullMonth = strtolower($date->format('F')); // april
        $shortMonth = strtolower($date->format('M')); // apr
        $year = $date->format('y'); // 25

        $candidates = [
            "transsiontec{$fullMonth}{$year}",
            "transsiontec{$shortMonth}{$year}",
            "transsiontecsept{$year}",
            "tecno{$fullMonth}{$year}",
            "tecno{$shortMonth}{$year}",
            "tecnosept{$year}",
            "unitycorp{$fullMonth}{$year}",
            "unitycorp{$shortMonth}{$year}",
            "unitycorpsept{$year}",
        ];

        foreach ($candidates as $table) {
            if (Schema::hasTable($table)) {
                return $table;
            }
        }

        return null;
    }

    private function getLatestAvailableMonth(): ?string
    {
        $tables = DB::select('SHOW TABLES');
        $matching = [];

        foreach ($tables as $t) {
            foreach ((array)$t as $name) {
                if (preg_match('/^(?:transsiontec|tecno|unitycorp)([a-z]+)(\d{2})$/', $name, $matches)) {
                    $monthName = $matches[1];
                    $year = $matches[2];
                    try {
                        $parsed = Carbon::parse("1 {$monthName} 20{$year}");
                        $matching[$parsed->format('Y-m')] = $name;
                    } catch (\Exception $e) {
                        // ignore
                    }
                }
            }
        }

        if (empty($matching)) {
            return null;
        }

        krsort($matching);
        return array_key_first($matching);
    }

    public function index(Request $request)
    {
        $request->validate([
            'month' => ['nullable', 'date_format:Y-m', 'after_or_equal:2020-01'],
        ]);

        $month = $request->input('month');

        if (!$month) {
            $now = Carbon::now();
            $tableName = $this->resolveTableName($now);
            if ($tableName) {
                $month = $now->format('Y-m');
            } else {
                $month = $this->getLatestAvailableMonth() ?? $now->format('Y-m');
            }
        }

        $date = Carbon::createFromFormat('Y-m', $month);
        $tableName = $this->resolveTableName($date);

        if (!$tableName) {
            Log::warning("TecnoSsnit Index: Table for {$month} does not exist");
            return Inertia::render('TecnoSsnit/Index', [
                'records' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10),
                'selectedMonth' => $month,
                'formattedMonth' => $date->format('F Y'),
                'totalRecords' => 0,
                'searchCount' => null,
                'flash' => [
                    'error' => "No data available for {$date->format('F Y')}.",
                ],
            ]);
        }

        $query = DB::table($tableName . ' as tp')
            ->leftJoin('ssnit as s', 'tp.staffida', '=', 's.staffid')
            ->select(
                'tp.staffida as staff_id',
                DB::raw('COALESCE(s.shop, "Tecno") as shop'),
                DB::raw('COALESCE(s.lastname, tp.name) as lastname'),
                DB::raw('COALESCE(s.firstname, "") as firstname'),
                DB::raw('COALESCE(s.othername, "") as othername'),
                DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                DB::raw('COALESCE(s.national, "") as gh_card_number'),
                'tp.basic',
                DB::raw('ROUND(tp.basic * 0.135, 2) as ssnit_amount')
            );

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffida', 'like', "%{$search}%")
                  ->orWhere('s.lastname', 'like', "%{$search}%")
                  ->orWhere('s.firstname', 'like', "%{$search}%")
                  ->orWhere('s.othername', 'like', "%{$search}%")
                  ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                  ->orWhere('s.national', 'like', "%{$search}%");
            });
        }

        $sort = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');
        $sortColumn = match($sort) {
            'staff_id' => 'tp.staffida',
            'shop' => 's.shop',
            'lastname' => 's.lastname',
            'firstname' => 's.firstname',
            'othername' => 's.othername',
            'ssnit_number' => 's.ssnit1a',
            'gh_card_number' => 's.national',
            'basic' => 'tp.basic',
            'ssnit_amount' => 'ssnit_amount',
            default => 'tp.staffida',
        };

        $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

        $perPage = 10;
        $records = $query->paginate($perPage)->appends([
            'month' => $month,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
        ]);

        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        return Inertia::render('TecnoSsnit/Index', [
            'records' => $records,
            'selectedMonth' => $month,
            'formattedMonth' => $date->format('F Y'),
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => [
                'success' => session('success'),
                'error' => session('error'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        try {
            $request->validate([
                'month' => ['required', 'date_format:Y-m', 'after_or_equal:2020-01'],
            ]);

            $month = $request->input('month');
            $date = Carbon::createFromFormat('Y-m', $month);
            $tableName = $this->resolveTableName($date);

            if (!$tableName) {
                Log::warning("TecnoSsnitPay Export: Table for {$month} does not exist");
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('ssnit as s', 'tp.staffida', '=', 's.staffid')
                ->select(
                    'tp.staffida as staff_id',
                    DB::raw('COALESCE(s.shop, "Tecno") as shop'),
                    DB::raw('COALESCE(s.lastname, tp.name) as lastname'),
                    DB::raw('COALESCE(s.firstname, "") as firstname'),
                    DB::raw('COALESCE(s.othername, "") as othername'),
                    DB::raw('COALESCE(s.ssnit1a, "") as ssnit_number'),
                    DB::raw('COALESCE(s.national, "") as gh_card_number'),
                    'tp.basic',
                    DB::raw('ROUND(tp.basic * 0.135, 2) as ssnit_amount')
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staffida', 'like', "%{$search}%")
                      ->orWhere('s.lastname', 'like', "%{$search}%")
                      ->orWhere('s.firstname', 'like', "%{$search}%")
                      ->orWhere('s.othername', 'like', "%{$search}%")
                      ->orWhere('s.ssnit1a', 'like', "%{$search}%")
                      ->orWhere('s.national', 'like', "%{$search}%");
                });
            }

            $sort = $request->input('sort', 'staff_id');
            $order = $request->input('order', 'asc');
            $sortColumn = match($sort) {
                'staff_id' => 'tp.staffida',
                'shop' => 's.shop',
                'lastname' => 's.lastname',
                'firstname' => 's.firstname',
                'othername' => 's.othername',
                'ssnit_number' => 's.ssnit1a',
                'gh_card_number' => 's.national',
                'basic' => 'tp.basic',
                'ssnit_amount' => 'ssnit_amount',
                default => 'tp.staffida',
            };

            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $headers = ['Staff ID', 'Shop', 'Last Name', 'First Name', 'Other Name', 'SSNIT Number', 'Ghana Card', 'Basic Salary', 'SSNIT (13.5%)'];
            $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            $totalBasic = 0;
            $totalSsnit = 0;

            foreach ($records as $r) {
                $basicVal = (float)($r->basic ?? 0);
                $ssnitVal = (float)($r->ssnit_amount ?? 0);
                $totalBasic += $basicVal;
                $totalSsnit += $ssnitVal;

                $sheet->setCellValueExplicit("A{$row}", $r->staff_id ?? '', DataType::TYPE_STRING);
                $sheet->setCellValue("B{$row}", $r->shop ?? '');
                $sheet->setCellValue("C{$row}", $r->lastname ?? '');
                $sheet->setCellValue("D{$row}", $r->firstname ?? '');
                $sheet->setCellValue("E{$row}", $r->othername ?? '');
                $sheet->setCellValueExplicit("F{$row}", $r->ssnit_number ?? '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("G{$row}", $r->gh_card_number ?? '', DataType::TYPE_STRING);
                $sheet->setCellValue("H{$row}", number_format($basicVal, 2, '.', ''));
                $sheet->setCellValue("I{$row}", number_format($ssnitVal, 2, '.', ''));
                $row++;
            }

            // Total row
            $sheet->setCellValue("A{$row}", "TOTAL");
            $sheet->setCellValue("H{$row}", number_format($totalBasic, 2, '.', ''));
            $sheet->setCellValue("I{$row}", number_format($totalSsnit, 2, '.', ''));

            $sheet->getParent()->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
            $headerStyle = $sheet->getStyle('A1:I1');
            $headerStyle->getFont()->setBold(true);
            $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('D9E1F2');

            $totalStyle = $sheet->getStyle("A{$row}:I{$row}");
            $totalStyle->getFont()->setBold(true);

            foreach (range('A', 'I') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = "tecno_ssnit_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Exception $e) {
            Log::error("TecnoSsnitPay Export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
