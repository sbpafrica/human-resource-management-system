<?php
namespace App\Http\Controllers;

use App\Models\Petra;
use App\Models\Tecnopay;
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

class TecnoPetraPayController extends Controller
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
            Log::warning("TecnoPetraPay Index: Table for {$month} does not exist");
            return Inertia::render('TecnoPetraPay/Index', [
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
            ->leftJoin('petraorg as p', 'tp.staffida', '=', 'p.staffid')
            ->select(
                'tp.staffida as staff_id',
                DB::raw('COALESCE(p.shop, "TRANSSION") as shop'),
                DB::raw('COALESCE(p.lastname, tp.name) as lastname'),
                DB::raw('COALESCE(p.firstname, "") as firstname'),
                DB::raw('COALESCE(p.othername, "") as othername'),
                DB::raw('COALESCE(p.ssnit1a, "") as petra_id'),
                DB::raw('COALESCE(p.national, "") as ghana_card'),
                'tp.basic',
                DB::raw('ROUND(tp.basic * 0.05, 2) as petra_amount')
            );

        // Apply search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('tp.staffida', 'like', "%{$search}%")
                  ->orWhere('p.lastname', 'like', "%{$search}%")
                  ->orWhere('p.firstname', 'like', "%{$search}%")
                  ->orWhere('p.othername', 'like', "%{$search}%")
                  ->orWhere('p.ssnit1a', 'like', "%{$search}%")
                  ->orWhere('p.national', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sort = $request->input('sort', 'staff_id');
        $order = $request->input('order', 'asc');
        Log::info("TecnoPetraPay Index: Sorting by {$sort} {$order}");
        $sortColumn = match($sort) {
            'staff_id' => 'tp.staffida',
            'shop' => 'p.shop',
            'lastname' => 'p.lastname',
            'firstname' => 'p.firstname',
            'othername' => 'p.othername',
            'petra_id' => 'p.ssnit1a',
            'ghana_card' => 'p.national',
            'basic' => 'tp.basic',
            'petra_amount' => 'petra_amount',
            default => 'tp.staffida',
        };

        // Use ISNULL to push NULLs to the end for MariaDB compatibility
        $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

        // Paginate results
        $perPage = 10;
        $records = $query->paginate($perPage)->appends([
            'month' => $month,
            'search' => $search,
            'sort' => $sort,
            'order' => $order,
        ]);

        // Get total records and search count
        $totalRecords = DB::table($tableName)->count();
        $searchCount = $search ? $query->count() : null;

        Log::info("TecnoPetraPay Index: Retrieved records", ['count' => $records->total(), 'sample' => $records->items()[0] ?? null]);

        return Inertia::render('TecnoPetraPay/Index', [
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
            Log::info("TecnoPetraPay Export: Resolved table name: {$tableName}");

            if (!$tableName) {
                Log::warning("TecnoPetraPay Export: Table for {$month} does not exist");
                return response()->json(['error' => "No data available for {$date->format('F Y')}"], 404);
            }

            $query = DB::table($tableName . ' as tp')
                ->leftJoin('petraorg as p', 'tp.staffida', '=', 'p.staffid')
                ->select(
                    'tp.staffida as staff_id',
                    DB::raw('COALESCE(p.shop, "TRANSSION") as shop'),
                    DB::raw('COALESCE(p.lastname, tp.name) as lastname'),
                    DB::raw('COALESCE(p.firstname, "") as firstname'),
                    DB::raw('COALESCE(p.othername, "") as othername'),
                    DB::raw('COALESCE(p.ssnit1a, "") as petra_id'),
                    DB::raw('COALESCE(p.national, "") as ghana_card'),
                    'tp.basic',
                    DB::raw('ROUND(tp.basic * 0.05, 2) as petra_amount')
                );

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('tp.staffida', 'like', "%{$search}%")
                      ->orWhere('p.lastname', 'like', "%{$search}%")
                      ->orWhere('p.firstname', 'like', "%{$search}%")
                      ->orWhere('p.othername', 'like', "%{$search}%")
                      ->orWhere('p.ssnit1a', 'like', "%{$search}%")
                      ->orWhere('p.national', 'like', "%{$search}%");
                });
            }

            $sort = $request->input('sort', 'staff_id');
            $order = $request->input('order', 'asc');
            $sortColumn = match($sort) {
                'staff_id' => 'tp.staffida',
                'shop' => 'p.shop',
                'lastname' => 'p.lastname',
                'firstname' => 'p.firstname',
                'othername' => 'p.othername',
                'petra_id' => 'p.ssnit1a',
                'ghana_card' => 'p.national',
                'basic' => 'tp.basic',
                'petra_amount' => 'petra_amount',
                default => 'tp.staffida',
            };

            $query->orderByRaw("ISNULL({$sortColumn}), {$sortColumn} {$order}");

            $records = $query->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $headers = ['Staff ID', 'Shop', 'Last Name', 'First Name', 'Other Name', 'PETRA ID', 'Ghana Card', 'Basic Salary', 'PETRA Amount (5%)'];
            $sheet->fromArray($headers, null, 'A1');

            $row = 2;
            $totalBasic = 0;
            $totalPetra = 0;

            foreach ($records as $r) {
                $basicVal = (float)($r->basic ?? 0);
                $petraVal = (float)($r->petra_amount ?? 0);
                $totalBasic += $basicVal;
                $totalPetra += $petraVal;

                $sheet->setCellValueExplicit("A{$row}", $r->staff_id ?? '', DataType::TYPE_STRING);
                $sheet->setCellValue("B{$row}", $r->shop ?? '');
                $sheet->setCellValue("C{$row}", $r->lastname ?? '');
                $sheet->setCellValue("D{$row}", $r->firstname ?? '');
                $sheet->setCellValue("E{$row}", $r->othername ?? '');
                $sheet->setCellValueExplicit("F{$row}", $r->petra_id ?? '', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit("G{$row}", $r->ghana_card ?? '', DataType::TYPE_STRING);
                $sheet->setCellValue("H{$row}", number_format($basicVal, 2, '.', ''));
                $sheet->setCellValue("I{$row}", number_format($petraVal, 2, '.', ''));
                $row++;
            }

            // Total row
            $sheet->setCellValue("A{$row}", "TOTAL");
            $sheet->setCellValue("H{$row}", number_format($totalBasic, 2, '.', ''));
            $sheet->setCellValue("I{$row}", number_format($totalPetra, 2, '.', ''));

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
            $filename = "tecno_petra_{$month}.xlsx";

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
        } catch (\Exception $e) {
            Log::error("TecnoPetraPay Export failed: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Failed to export data: ' . $e->getMessage()], 500);
        }
    }
}
