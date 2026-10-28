<?php

namespace App\Http\Controllers\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttendanceApiController extends BaseApiController
{
    /**
     * List attendance records with date and employee filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $fromDate = $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDate   = $request->input('to_date', now()->toDateString());
        $employeeId = $request->input('employee_id');
        $status   = $request->input('status');

        $query = DB::table('attendanceinhouse as a')
            ->leftJoin('newemployeedata as e', 'a.employee_id', '=', 'e.staffid')
            ->select([
                'a.id',
                'a.employee_id',
                DB::raw("COALESCE(e.employeename, a.employee_id) as employee_name"),
                'e.company',
                'e.shop',
                'a.date',
                'a.status',
                'a.check_in_time',
                'a.check_out_time',
                'a.notes',
            ])
            ->whereBetween('a.date', [$fromDate, $toDate]);

        if ($employeeId) {
            $query->where('a.employee_id', $employeeId);
        }

        if ($status) {
            $query->where('a.status', $status);
        }

        $query->orderBy('a.date', 'desc')->orderBy('a.id', 'desc');

        $perPage = min((int) $request->input('per_page', 25), 100);
        $records = $query->paginate($perPage);

        return $this->sendResponse([
            'from_date'    => $fromDate,
            'to_date'      => $toDate,
            'items'        => $records->items(),
            'current_page' => $records->currentPage(),
            'total_pages'  => $records->lastPage(),
            'total_items'  => $records->total(),
        ], 'Attendance records retrieved.');
    }

    /**
     * Log or update attendance record (Check-in / Status marking).
     */
    public function record(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id'    => ['required', 'string'],
            'date'           => ['required', 'date'],
            'status'         => ['required', 'string', 'in:Present,Absent,Off,Leave,Half-Day'],
            'check_in_time'  => ['nullable', 'date_format:H:i:s,H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i:s,H:i'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $existing = DB::table('attendanceinhouse')
            ->where('employee_id', $request->employee_id)
            ->where('date', $request->date)
            ->first();

        $data = [
            'status'         => $request->status,
            'check_in_time'  => $request->check_in_time ?? ($existing?->check_in_time ?? now()->format('H:i:s')),
            'check_out_time' => $request->check_out_time ?? $existing?->check_out_time,
            'notes'          => $request->notes ?? $existing?->notes,
            'updated_at'     => now(),
        ];

        if ($existing) {
            DB::table('attendanceinhouse')->where('id', $existing->id)->update($data);
            $recordId = $existing->id;
        } else {
            $data['employee_id'] = $request->employee_id;
            $data['date']        = $request->date;
            $data['created_at']  = now();
            $recordId = DB::table('attendanceinhouse')->insertGetId($data);
        }

        $record = DB::table('attendanceinhouse')->find($recordId);

        return $this->sendResponse($record, 'Attendance recorded successfully.', $existing ? 200 : 201);
    }

    /**
     * Get attendance statistical summary for a date range.
     */
    public function summary(Request $request): JsonResponse
    {
        $fromDate = $request->input('from_date', now()->startOfMonth()->toDateString());
        $toDate   = $request->input('to_date', now()->toDateString());

        $stats = DB::table('attendanceinhouse')
            ->whereBetween('date', [$fromDate, $toDate])
            ->selectRaw("
                COUNT(*) as total_logged,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_count,
                SUM(CASE WHEN status = 'Off' THEN 1 ELSE 0 END) as off_count
            ")
            ->first();

        return $this->sendResponse([
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            'summary'   => $stats,
        ], 'Attendance summary retrieved.');
    }
}
