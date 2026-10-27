<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ITAsset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ITAssetApiController extends BaseApiController
{
    /**
     * List all IT assets and allocations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ITAsset::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('asset_tag', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('staff_name', 'like', "%{$search}%")
                  ->orWhere('staff_id', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('allocation_status', $status);
        }

        if ($staffId = $request->input('staff_id')) {
            $query->where('staff_id', $staffId);
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $assets = $query->paginate($perPage);

        return $this->sendResponse([
            'items'        => $assets->items(),
            'current_page' => $assets->currentPage(),
            'total_pages'  => $assets->lastPage(),
            'total_items'  => $assets->total(),
        ], 'IT Assets retrieved.');
    }

    /**
     * Create or register new IT asset.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name'              => ['required', 'string', 'max:255'],
            'asset_tag'         => ['nullable', 'string', 'unique:assets,asset_tag'],
            'brand'             => ['nullable', 'string', 'max:100'],
            'model'             => ['nullable', 'string', 'max:100'],
            'serial_number'     => ['nullable', 'string', 'max:100'],
            'allocation_status' => ['nullable', 'string', 'in:Available,Assigned,Maintenance,Decommissioned'],
            'staff_id'          => ['nullable', 'string', 'max:50'],
            'staff_name'        => ['nullable', 'string', 'max:255'],
            'location'          => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation error.', $validator->errors(), 422);
        }

        $asset = ITAsset::create($request->all());

        return $this->sendResponse($asset, 'IT asset registered successfully.', 201);
    }

    /**
     * Show single asset details.
     */
    public function show($id): JsonResponse
    {
        $asset = ITAsset::find($id);

        if (!$asset) {
            return $this->sendError('Asset not found.', null, 404);
        }

        return $this->sendResponse($asset, 'Asset details retrieved.');
    }

    /**
     * Update asset assignment or details.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $asset = ITAsset::find($id);

        if (!$asset) {
            return $this->sendError('Asset not found.', null, 404);
        }

        $asset->update($request->only([
            'name',
            'brand',
            'model',
            'serial_number',
            'allocation_status',
            'staff_id',
            'staff_name',
            'location',
            'condition',
            'notes',
        ]));

        return $this->sendResponse($asset, 'Asset updated successfully.');
    }

    /**
     * Delete asset.
     */
    public function destroy($id): JsonResponse
    {
        $asset = ITAsset::find($id);

        if (!$asset) {
            return $this->sendError('Asset not found.', null, 404);
        }

        $asset->delete();

        return $this->sendResponse(null, 'Asset deleted.');
    }
}
