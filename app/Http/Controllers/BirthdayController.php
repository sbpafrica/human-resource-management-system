<?php

namespace App\Http\Controllers;

use App\Models\BirthdayLog;
use App\Models\BirthdaySetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Response;

class BirthdayController extends Controller
{
    // ── Reports Page ─────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $date   = $request->input('date', now()->toDateString());
        $status = $request->input('status');   // sent | failed | null = all
        $search = $request->input('search');

        $query = BirthdayLog::query()->where('sent_date', $date);

        if ($status) {
            $query->where('status', $status);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid', 'like', "%{$search}%")
                  ->orWhere('name',    'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('shop',    'like', "%{$search}%")
                  ->orWhere('phone',   'like', "%{$search}%");
            });
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();

        // Daily summary for the selected date
        $summary = [
            'total'   => BirthdayLog::where('sent_date', $date)->count(),
            'sent'    => BirthdayLog::where('sent_date', $date)->where('status', 'sent')->count(),
            'failed'  => BirthdayLog::where('sent_date', $date)->where('status', 'failed')->count(),
            'skipped' => BirthdayLog::where('sent_date', $date)->where('status', 'skipped')->count(),
        ];

        return Inertia::render('Birthday/Reports', [
            'logs'           => $logs,
            'summary'        => $summary,
            'selectedDate'   => $date,
            'selectedStatus' => $status,
            'search'         => $search,
            'flash'          => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    // ── Settings Page ─────────────────────────────────────────────────────────
    public function settings()
    {
        return Inertia::render('Birthday/Settings', [
            'settings' => BirthdaySetting::current(),
            'flash'    => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    // ── Save Settings ─────────────────────────────────────────────────────────
    public function saveSettings(Request $request)
    {
        $validated = $request->validate([
            'message_template' => ['required', 'string', 'max:320'],
            'send_time'        => ['required', 'date_format:H:i'],
            'is_active'        => ['required', 'boolean'],
            'alert_email'      => ['required', 'email'],
        ]);

        $settings = BirthdaySetting::current();
        $settings->update($validated);

        return redirect()->route('birthday.settings')
            ->with('success', 'Birthday SMS settings saved successfully.');
    }

    // ── Export Report to Excel ─────────────────────────────────────────────────
    public function export(Request $request)
    {
        $date   = $request->input('date', now()->toDateString());
        $status = $request->input('status');

        $query = BirthdayLog::query()->where('sent_date', $date);
        if ($status) {
            $query->where('status', $status);
        }
        $logs = $query->orderBy('created_at', 'desc')->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Birthday Report');

        // Headers
        $headers = ['Staff ID', 'Name', 'Company', 'Shop', 'Phone', 'Status', 'Failure Reason', 'Sent At'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD9EAD3');

        $row = 2;
        foreach ($logs as $log) {
            $sheet->setCellValue("A{$row}", $log->staffid);
            $sheet->setCellValue("B{$row}", $log->name);
            $sheet->setCellValue("C{$row}", $log->company ?? '');
            $sheet->setCellValue("D{$row}", $log->shop ?? '');
            $sheet->setCellValue("E{$row}", $log->status === 'skipped' ? 'N/A' : $log->phone);
            $sheet->setCellValue("F{$row}", strtoupper($log->status));
            $sheet->setCellValue("G{$row}", $log->failure_reason ?? '');
            $sheet->setCellValue("H{$row}", $log->created_at?->format('Y-m-d H:i:s') ?? '');
            $row++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer   = new Xlsx($spreadsheet);
        $filename = "birthday_report_{$date}.xlsx";
        $tmpFile  = tempnam(sys_get_temp_dir(), $filename);
        $writer->save($tmpFile);

        return Response::download($tmpFile, $filename)->deleteFileAfterSend(true);
    }
}
