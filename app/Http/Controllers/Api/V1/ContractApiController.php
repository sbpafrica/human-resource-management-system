<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractApiController extends BaseApiController
{
    /**
     * List employee contracts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Contract::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%")
                  ->orWhere('employeename', 'like', "%{$search}%")
                  ->orWhere('client', 'like', "%{$search}%");
            });
        }

        if ($client = $request->input('client')) {
            $query->where('client', $client);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $contracts = $query->paginate($perPage);

        return $this->sendResponse([
            'items'        => $contracts->items(),
            'current_page' => $contracts->currentPage(),
            'total_pages'  => $contracts->lastPage(),
            'total_items'  => $contracts->total(),
        ], 'Contracts retrieved.');
    }

    /**
     * View single contract details.
     */
    public function show($id): JsonResponse
    {
        $contract = Contract::find($id);

        if (!$contract) {
            return $this->sendError('Contract not found.', null, 404);
        }

        return $this->sendResponse($contract, 'Contract details retrieved.');
    }

    /**
     * Activate or deactivate contract.
     */
    public function toggleActivation(Request $request, $id): JsonResponse
    {
        $contract = Contract::find($id);

        if (!$contract) {
            return $this->sendError('Contract not found.', null, 404);
        }

        $contract->activate = $contract->activate === '1' ? '0' : '1';
        $contract->save();

        return $this->sendResponse([
            'id'       => $contract->id,
            'activate' => $contract->activate,
            'status'   => $contract->activate === '1' ? 'Active' : 'Inactive',
        ], 'Contract status updated.');
    }
}
