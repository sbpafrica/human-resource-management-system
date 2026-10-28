<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Leave\StoreLeaveRequest;
use App\Http\Resources\Api\V1\LeaveResource;
use App\Models\LeaveApplication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeaveController extends BaseApiController
{
    /**
     * List leave applications with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeaveApplication::query()->latest('id');

        // Filter by employee ID (ida)
        if ($staffId = $request->input('staff_id')) {
            $query->where('ida', $staffId);
        }

        // Filter by status (pending, approved, rejected)
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by leave type
        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $leaves = $query->paginate($perPage);

        return $this->sendResponse([
            'items'        => LeaveResource::collection($leaves->items()),
            'current_page' => $leaves->currentPage(),
            'total_pages'  => $leaves->lastPage(),
            'total_items'  => $leaves->total(),
            'per_page'     => $leaves->perPage(),
        ], 'Leave applications retrieved.');
    }

    /**
     * Submit a new leave application.
     */
    public function store(StoreLeaveRequest $request): JsonResponse
    {
        $leave = LeaveApplication::create([
            'ida'         => $request->staff_id,
            'name'        => $request->name,
            'type'        => $request->type,
            'date_start'  => $request->date_start,
            'date_end'    => $request->date_end,
            'reason'      => $request->reason,
            'managername' => $request->manager_name,
            'phone'       => $request->phone,
            'status'      => 'pending',
        ]);

        return $this->sendResponse(
            new LeaveResource($leave),
            'Leave application submitted successfully.',
            201
        );
    }

    /**
     * View details of a single leave application.
     */
    public function show($id): JsonResponse
    {
        $leave = LeaveApplication::find($id);

        if (!$leave) {
            return $this->sendError('Leave application not found.', null, 404);
        }

        return $this->sendResponse(new LeaveResource($leave), 'Leave details retrieved.');
    }

    /**
     * Approve or reject a leave application.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $leave = LeaveApplication::find($id);

        if (!$leave) {
            return $this->sendError('Leave application not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', 'in:approved,rejected,pending'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $leave->status = $request->status;
        $leave->save();

        return $this->sendResponse(
            new LeaveResource($leave),
            "Leave application marked as {$request->status}."
        );
    }
}
