<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeController extends BaseApiController
{
    /**
     * List employees with filtering, searching, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employee::query();

        // Search by staff ID or name
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('staffid', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%");
            });
        }

        // Filter by company
        if ($company = $request->input('company')) {
            $query->where('company', $company);
        }

        // Filter by position
        if ($position = $request->input('position')) {
            $query->where('position', 'like', "%{$position}%");
        }

        // Client scope restriction for non-super-admins
        $user = $request->user();
        if ($user && !$user->is_super_admin && method_exists($user, 'clients') && $user->clients()->exists()) {
            $allowedCompanies = $user->clients()->pluck('client_name')->toArray();
            if (!empty($allowedCompanies)) {
                $query->whereIn('company', $allowedCompanies);
            }
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $employees = $query->paginate($perPage);

        return $this->sendResponse([
            'items'        => EmployeeResource::collection($employees->items()),
            'current_page' => $employees->currentPage(),
            'total_pages'  => $employees->lastPage(),
            'total_items'  => $employees->total(),
            'per_page'     => $employees->perPage(),
        ], 'Employees retrieved successfully.');
    }

    /**
     * Retrieve a single employee's detailed profile.
     */
    public function show(string $staffid): JsonResponse
    {
        $employee = Employee::with(['bankDetail', 'petra', 'ssnit'])->find($staffid);

        if (!$employee) {
            return $this->sendError('Employee not found.', null, 404);
        }

        return $this->sendResponse(new EmployeeResource($employee), 'Employee details retrieved.');
    }

    /**
     * Update an employee's self-service contact / profile info.
     */
    public function updateProfile(Request $request, string $staffid): JsonResponse
    {
        $employee = Employee::find($staffid);

        if (!$employee) {
            return $this->sendError('Employee not found.', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'email'           => ['nullable', 'email'],
            'phone1'          => ['nullable', 'string', 'max:20'],
            'phone2'          => ['nullable', 'string', 'max:20'],
            'gpsaddress'      => ['nullable', 'string', 'max:255'],
            'nextofkin'       => ['nullable', 'string', 'max:255'],
            'relation'        => ['nullable', 'string', 'max:100'],
            'contact'         => ['nullable', 'string', 'max:20'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $employee->fill($request->only([
            'email',
            'phone1',
            'phone2',
            'gpsaddress',
            'nextofkin',
            'relation',
            'contact',
        ]));

        $employee->save();

        return $this->sendResponse(new EmployeeResource($employee), 'Profile updated successfully.');
    }
}
