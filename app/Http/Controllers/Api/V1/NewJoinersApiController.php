<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\EmployeeResource;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewJoinersApiController extends BaseApiController
{
    /**
     * List newly onboarded employees.
     */
    public function index(Request $request): JsonResponse
    {
        $days = (int) $request->input('days', 90);
        $sinceDate = now()->subDays($days)->toDateString();

        $query = Employee::whereNotNull('startdate')
            ->where('startdate', '>=', $sinceDate)
            ->orderBy('startdate', 'desc');

        if ($company = $request->input('company')) {
            $query->where('company', $company);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $newJoiners = $query->paginate($perPage);

        return $this->sendResponse([
            'since_date'   => $sinceDate,
            'days_window'  => $days,
            'items'        => EmployeeResource::collection($newJoiners->items()),
            'current_page' => $newJoiners->currentPage(),
            'total_pages'  => $newJoiners->lastPage(),
            'total_items'  => $newJoiners->total(),
        ], 'New joiners retrieved.');
    }
}
