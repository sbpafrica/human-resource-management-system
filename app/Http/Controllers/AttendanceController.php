<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use DateTime;
use DatePeriod;
use DateInterval;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $fromDate = $request->input('from_date', date('Y-m-01'));
        $toDate   = $request->input('to_date', date('Y-m-d'));
        $search   = $request->input('search', '');

        // Fetch employees
        $employeesQuery = DB::table('employees')->orderBy('employee_name', 'asc');
        if (!empty($search)) {
            $employeesQuery->where(function ($q) use ($search) {
                $q->where('employee_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }
        $employeesList = $employeesQuery->get();

        $employeesMap = [];
        foreach ($employeesList as $emp) {
            $employeesMap[$emp->employee_id] = $emp->employee_name;
        }

        // Generate working dates (Monday to Friday)
        $allDates = [];
        if ($fromDate && $toDate && $fromDate <= $toDate) {
            $start = new DateTime($fromDate);
            $end   = new DateTime($toDate);
            $end->modify('+1 day');
            $period = new DatePeriod($start, new DateInterval('P1D'), $end);

            foreach ($period as $d) {
                // ISO-8601 numeric representation of the day (1 for Monday to 7 for Sunday)
                if ($d->format('N') < 6) {
                    $allDates[] = $d->format('Y-m-d');
                }
            }
        }

        // Fetch attendance records in date range
        $attendanceRaw = DB::table('attendanceinhouse as a')
            ->leftJoin('employees as e', 'a.employee_id', '=', 'e.employee_id')
            ->whereBetween('a.date', [$fromDate, $toDate])
            ->select('a.employee_id', 'e.employee_name', 'a.date', 'a.clock_in', 'a.clock_out')
            ->orderBy('e.employee_name', 'asc')
            ->orderBy('a.date', 'asc')
            ->get();

        $attendanceIndexed = [];
        foreach ($attendanceRaw as $row) {
            $attendanceIndexed[$row->employee_id][$row->date] = $row;
        }

        // Build structured attendance records
        $flatRecords = [];
        $totalPresentCount = 0;
        $totalEarlyOtHours = 0;
        $totalOvertimeHours = 0;

        foreach ($employeesMap as $empId => $empName) {
            foreach ($allDates as $date) {
                $rec = $attendanceIndexed[$empId][$date] ?? null;
                $clockIn  = $rec ? $rec->clock_in : null;
                $clockOut = $rec ? $rec->clock_out : null;

                $calculated = $this->calculateAttendance($clockIn, $clockOut);

                if ($calculated['present']) {
                    $totalPresentCount++;
                }
                $totalEarlyOtHours += $calculated['early_overtime'];
                $totalOvertimeHours += $calculated['overtime'];

                $flatRecords[] = [
                    'employee_id'     => $empId,
                    'employee_name'   => $empName,
                    'date'            => $date,
                    'legal_work_days' => 1,
                    'present'         => $calculated['present'],
                    'clock_in'        => $calculated['clock_in'],
                    'clock_out'       => $calculated['clock_out'],
                    'early_overtime'  => round($calculated['early_overtime'], 2),
                    'overtime'        => round($calculated['overtime'], 2),
                ];
            }
        }

        // Paginate structured attendance records
        $page      = (int) $request->input('page', 1);
        $perPage   = (int) $request->input('per_page', 25);
        $offset    = ($page - 1) * $perPage;
        $totalRows = count($flatRecords);

        $currentPageItems = array_slice($flatRecords, $offset, $perPage);

        $paginatedAttendance = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $totalRows,
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        return Inertia::render('Attendance/Index', [
            'attendanceRecords' => $paginatedAttendance,
            'filters'           => [
                'from_date' => $fromDate,
                'to_date'   => $toDate,
                'search'    => $search,
            ],
            'stats'             => [
                'total_employees'      => count($employeesMap),
                'total_working_days'   => count($allDates),
                'total_present_logs'   => $totalPresentCount,
                'total_early_ot_hours' => round($totalEarlyOtHours, 2),
                'total_overtime_hours' => round($totalOvertimeHours, 2),
            ],
            'flash'             => [
                'success' => session('success'),
                'error'   => session('error'),
                'warning' => session('warning'),
            ],
        ]);
    }

    public function uploadEmployees(Request $request)
    {
        $request->validate([
            'user_file' => 'required|file|mimes:txt,csv|max:10240',
        ]);

        try {
            $file = $request->file('user_file');
            $handle = fopen($file->getRealPath(), 'r');
            $inserted = 0;

            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $data = preg_split('/\t+/', $line);
                    if (count($data) < 2) continue;

                    $employeeId   = trim($data[0]);
                    $employeeName = trim($data[1]);

                    if (empty($employeeId) || empty($employeeName)) continue;

                    DB::table('employees')->upsert(
                        [
                            'employee_id'   => $employeeId,
                            'employee_name' => $employeeName,
                        ],
                        ['employee_id'],
                        ['employee_name']
                    );
                    $inserted++;
                }
                fclose($handle);

                return redirect()->back()->with('success', "User data imported successfully! ({$inserted} records processed)");
            }

            return redirect()->back()->with('error', 'Unable to open user data file.');
        } catch (\Exception $e) {
            Log::error('Upload employees error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error processing file: ' . $e->getMessage());
        }
    }

    public function uploadAttendance(Request $request)
    {
        $request->validate([
            'attendance_file' => 'required|file|mimes:txt|max:20480',
        ]);

        try {
            $file = $request->file('attendance_file');
            $handle = fopen($file->getRealPath(), 'r');
            $processed = 0;
            $missingEmployees = [];

            if ($handle) {
                while (($line = fgets($handle)) !== false) {
                    $line = trim($line);
                    if (empty($line)) continue;

                    $data = preg_split('/\t+/', $line);
                    if (count($data) < 6) continue;

                    $rawEmployeeId = trim($data[0]);
                    $timestamp     = trim($data[1]);
                    $state         = trim($data[4]);

                    if (empty($rawEmployeeId) || empty($timestamp)) continue;

                    $possibleName = isset($data[2]) && !empty(trim($data[2])) ? trim($data[2]) : "Employee {$rawEmployeeId}";

                    $timeVal = strtotime($timestamp);
                    if (!$timeVal) continue;

                    $date     = date('Y-m-d', $timeVal);
                    $timeFull = date('Y-m-d H:i:s', $timeVal);
                    $timeOnly = date('H:i:s', $timeVal);

                    // Check if employee exists
                    $empExists = DB::table('employees')->where('employee_id', $rawEmployeeId)->exists();
                    if (!$empExists) {
                        if (!isset($missingEmployees[$rawEmployeeId])) {
                            $missingEmployees[$rawEmployeeId] = $possibleName;
                        }

                        // Auto-create employee record
                        DB::table('employees')->upsert(
                            [
                                'employee_id'   => $rawEmployeeId,
                                'employee_name' => $possibleName,
                            ],
                            ['employee_id'],
                            ['employee_name']
                        );
                    }

                    // Process attendance record
                    $existing = DB::table('attendanceinhouse')
                        ->where('employee_id', $rawEmployeeId)
                        ->where('date', $date)
                        ->first();

                    if (!$existing) {
                        if ($state === '1') {
                            $clockIn  = ($timeOnly <= '17:00:00') ? $timeFull : null;
                            $clockOut = ($timeOnly > '17:00:00')  ? $timeFull : null;

                            DB::table('attendanceinhouse')->insert([
                                'employee_id' => $rawEmployeeId,
                                'date'        => $date,
                                'clock_in'    => $clockIn,
                                'clock_out'   => $clockOut,
                            ]);
                            $processed++;
                        }
                    } else {
                        if (is_null($existing->clock_out) && !is_null($existing->clock_in)) {
                            DB::table('attendanceinhouse')
                                ->where('id', $existing->id)
                                ->update(['clock_out' => $timeFull]);
                            $processed++;
                        }
                    }
                }
                fclose($handle);

                $msg = "Attendance log imported successfully! ({$processed} records processed).";
                if (!empty($missingEmployees)) {
                    $missingList = [];
                    foreach ($missingEmployees as $id => $name) {
                        $missingList[] = "ID {$id} ({$name})";
                    }
                    $msg .= " New employee(s) auto-added: " . implode(', ', $missingList) . ".";
                }

                return redirect()->back()->with('success', $msg);
            }

            return redirect()->back()->with('error', 'Unable to open attendance log file.');
        } catch (\Exception $e) {
            Log::error('Upload attendance log error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Error processing log file: ' . $e->getMessage());
        }
    }

    public function export(Request $request)
    {
        $fromDate = $request->input('from_date', date('Y-m-01'));
        $toDate   = $request->input('to_date', date('Y-m-d'));
        $search   = $request->input('search', '');

        $employeesQuery = DB::table('employees')->orderBy('employee_name', 'asc');
        if (!empty($search)) {
            $employeesQuery->where(function ($q) use ($search) {
                $q->where('employee_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }
        $employeesList = $employeesQuery->get();

        $allDates = [];
        if ($fromDate && $toDate && $fromDate <= $toDate) {
            $start = new DateTime($fromDate);
            $end   = new DateTime($toDate);
            $end->modify('+1 day');
            $period = new DatePeriod($start, new DateInterval('P1D'), $end);

            foreach ($period as $d) {
                if ($d->format('N') < 6) {
                    $allDates[] = $d->format('Y-m-d');
                }
            }
        }

        $attendanceRaw = DB::table('attendanceinhouse as a')
            ->leftJoin('employees as e', 'a.employee_id', '=', 'e.employee_id')
            ->whereBetween('a.date', [$fromDate, $toDate])
            ->select('a.employee_id', 'e.employee_name', 'a.date', 'a.clock_in', 'a.clock_out')
            ->orderBy('e.employee_name', 'asc')
            ->orderBy('a.date', 'asc')
            ->get();

        $attendanceIndexed = [];
        foreach ($attendanceRaw as $row) {
            $attendanceIndexed[$row->employee_id][$row->date] = $row;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance Summary');

        // Styles
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']], // Primary blue
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];

        $headers = ['Employee ID', 'Employee Name', 'Date', 'Legal Work Days', 'Status', 'Clock In', 'Clock Out', 'Early OT (hrs)', 'Overtime (hrs)'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $rowNum = 2;
        $totalPresent = 0;
        $totalEarlyOt = 0;
        $totalOvertime = 0;

        foreach ($employeesList as $emp) {
            foreach ($allDates as $date) {
                $rec = $attendanceIndexed[$emp->employee_id][$date] ?? null;
                $clockIn  = $rec ? $rec->clock_in : null;
                $clockOut = $rec ? $rec->clock_out : null;

                $calculated = $this->calculateAttendance($clockIn, $clockOut);

                if ($calculated['present']) $totalPresent++;
                $totalEarlyOt += $calculated['early_overtime'];
                $totalOvertime += $calculated['overtime'];

                $sheet->setCellValue("A{$rowNum}", $emp->employee_id);
                $sheet->setCellValue("B{$rowNum}", $emp->employee_name);
                $sheet->setCellValue("C{$rowNum}", $date);
                $sheet->setCellValue("D{$rowNum}", 1);
                $sheet->setCellValue("E{$rowNum}", $calculated['present'] ? 'Present' : 'Absent');
                $sheet->setCellValue("F{$rowNum}", $calculated['clock_in']);
                $sheet->setCellValue("G{$rowNum}", $calculated['clock_out']);
                $sheet->setCellValue("H{$rowNum}", round($calculated['early_overtime'], 2));
                $sheet->setCellValue("I{$rowNum}", round($calculated['overtime'], 2));

                $rowNum++;
            }
        }

        // Total Row
        $sheet->setCellValue("A{$rowNum}", "TOTAL SUMMARY");
        $sheet->setCellValue("D{$rowNum}", count($allDates) * count($employeesList));
        $sheet->setCellValue("E{$rowNum}", "Present: {$totalPresent}");
        $sheet->setCellValue("H{$rowNum}", round($totalEarlyOt, 2));
        $sheet->setCellValue("I{$rowNum}", round($totalOvertime, 2));

        $totalStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E2E8F0']],
        ];
        $sheet->getStyle("A{$rowNum}:I{$rowNum}")->applyFromArray($totalStyle);

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = "Attendance_Report_" . date('Y-m-d_H-i') . ".xlsx";

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function calculateAttendance($clockIn, $clockOut)
    {
        $workStart = "08:00:00";
        $workEnd   = "17:00:00";

        $result = [
            'present'        => 0,
            'clock_in'       => '-',
            'clock_out'      => '-',
            'early_overtime' => 0,
            'overtime'       => 0,
        ];

        if ($clockIn || $clockOut) {
            $result['present']   = 1;
            $result['clock_in']  = $clockIn  ? date('H:i', strtotime($clockIn))  : '-';
            $result['clock_out'] = $clockOut ? date('H:i', strtotime($clockOut)) : '-';

            if ($clockIn) {
                $ci = new DateTime($clockIn);
                $ws = new DateTime(date('Y-m-d', strtotime($clockIn)) . " {$workStart}");
                if ($ci < $ws) {
                    $diff = $ws->diff($ci);
                    $result['early_overtime'] = $diff->h + ($diff->i / 60);
                }
            }

            if ($clockOut) {
                $co = new DateTime($clockOut);
                $we = new DateTime(date('Y-m-d', strtotime($clockOut)) . " {$workEnd}");
                if ($co > $we) {
                    $diff = $co->diff($we);
                    $result['overtime'] = $diff->h + ($diff->i / 60);
                }
            }
        }

        return $result;
    }
}
