<?php

namespace App\Http\Controllers;

use App\Models\ITAsset;
use App\Models\ITAssetArchive;
use App\Models\ITAssetCategory;
use App\Models\ITAssetSpec;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ITAssetController extends Controller
{
    private array $categoryPrefixes = [
        1 => 'LAP',
        2 => 'DESK',
        3 => 'MOB',
        4 => 'PRT',
        5 => 'RT',
        6 => 'OTH',
    ];

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $query = ITAsset::with(['category', 'specs']);

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag',    'like', "%{$search}%")
                  ->orWhere('name',       'like', "%{$search}%")
                  ->orWhere('brand',      'like', "%{$search}%")
                  ->orWhere('model',      'like', "%{$search}%")
                  ->orWhere('staff_name', 'like', "%{$search}%")
                  ->orWhere('staff_id',   'like', "%{$search}%")
                  ->orWhere('location',   'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->input('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($allocationStatus = $request->input('allocation_status')) {
            $query->where('allocation_status', $allocationStatus);
        }

        if ($condition = $request->input('condition')) {
            $query->where('condition', $condition);
        }

        // Sorting
        $allowedSorts = ['asset_id', 'asset_tag', 'name', 'brand', 'location', 'allocation_status', 'condition', 'created_date'];
        $sortColumn   = in_array($request->input('sort'), $allowedSorts) ? $request->input('sort') : 'asset_id';
        $sortOrder    = $request->input('order', 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortColumn, $sortOrder);

        $totalRecords = ITAsset::count();
        $hasFilters   = $request->filled('search')
                     || $request->filled('category_id')
                     || $request->filled('allocation_status')
                     || $request->filled('condition');
        $searchCount  = $hasFilters ? $query->count() : null;

        $assets     = $query->paginate(15)->withQueryString();
        $categories = ITAssetCategory::orderBy('name')->get();

        // Transform for frontend
        $assets->getCollection()->transform(fn ($asset) => $this->transformAsset($asset));

        return Inertia::render('ITAssets/Index', [
            'assets'       => $assets,
            'categories'   => $categories,
            'totalRecords' => $totalRecords,
            'searchCount'  => $searchCount,
            'filters'      => $request->only(['search', 'category_id', 'allocation_status', 'condition', 'sort', 'order']),
            'flash'        => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $this->validateAssetRequest($request);

        if ($validated['category_id'] == 5 && empty($validated['router_ip'])) {
            return back()->withErrors(['router_ip' => 'IP Address is required for routers.'])->withInput();
        }

        if ($validated['allocation_status'] === 'Allocated'
            && (empty($validated['staff_id']) || empty($validated['staff_name']))) {
            return back()->withErrors(['staff_id' => 'Staff ID and Name are required for allocated assets.'])->withInput();
        }

        DB::transaction(function () use ($validated, $request) {
            $prefix   = $this->categoryPrefixes[$validated['category_id']] ?? 'OTH';
            $assetTag = ITAsset::generateAssetTag($prefix);

            $asset = ITAsset::create([
                'category_id'       => $validated['category_id'],
                'asset_tag'         => $assetTag,
                'name'              => $validated['name'],
                'brand'             => $validated['brand'] ?: null,
                'model'             => $validated['model'],
                'serial_number'     => $validated['serial_number'] ?: null,
                'purchase_date'     => $validated['purchase_date'] ?: null,
                'warranty_expiry'   => $validated['warranty_expiry'] ?: null,
                'location'          => $validated['location'],
                'allocation_status' => $validated['allocation_status'],
                'staff_id'          => $validated['allocation_status'] === 'Allocated' ? ($validated['staff_id'] ?: null) : null,
                'staff_name'        => $validated['allocation_status'] === 'Allocated' ? ($validated['staff_name'] ?: null) : null,
                'condition'         => $validated['condition'],
                'notes'             => $validated['notes'] ?: null,
            ]);

            $this->syncSpecs($asset->asset_id, $validated['category_id'], $request->all());
        });

        return back()->with('success', 'Asset added successfully!');
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Request $request, int $id)
    {
        $asset     = ITAsset::findOrFail($id);
        $validated = $this->validateAssetRequest($request);

        DB::transaction(function () use ($asset, $validated, $request) {
            $asset->update([
                'category_id'       => $validated['category_id'],
                'name'              => $validated['name'],
                'brand'             => $validated['brand'] ?: null,
                'model'             => $validated['model'],
                'serial_number'     => $validated['serial_number'] ?: null,
                'purchase_date'     => $validated['purchase_date'] ?: null,
                'warranty_expiry'   => $validated['warranty_expiry'] ?: null,
                'location'          => $validated['location'],
                'allocation_status' => $validated['allocation_status'],
                'staff_id'          => $validated['allocation_status'] === 'Allocated' ? ($validated['staff_id'] ?: null) : null,
                'staff_name'        => $validated['allocation_status'] === 'Allocated' ? ($validated['staff_name'] ?: null) : null,
                'condition'         => $validated['condition'],
                'notes'             => $validated['notes'] ?: null,
            ]);

            ITAssetSpec::where('asset_id', $asset->asset_id)->delete();
            $this->syncSpecs($asset->asset_id, $validated['category_id'], $request->all());
        });

        return back()->with('success', 'Asset updated successfully!');
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function destroy(int $id)
    {
        $asset = ITAsset::findOrFail($id);

        DB::transaction(function () use ($asset) {
            ITAssetSpec::where('asset_id', $asset->asset_id)->delete();
            $asset->delete();
        });

        return back()->with('success', 'Asset deleted successfully!');
    }

    // ─── Archive ──────────────────────────────────────────────────────────────

    public function archive(int $id)
    {
        $asset = ITAsset::with('specs')->findOrFail($id);
        $user  = Auth::user();

        DB::transaction(function () use ($asset, $user) {
            // Copy to archive table
            ITAssetArchive::create([
                'asset_id'         => $asset->asset_id,
                'category_id'      => $asset->category_id,
                'asset_tag'        => $asset->asset_tag,
                'name'             => $asset->name,
                'brand'            => $asset->brand,
                'model'            => $asset->model,
                'serial_number'    => $asset->serial_number,
                'purchase_date'    => $asset->purchase_date,
                'warranty_expiry'  => $asset->warranty_expiry,
                'location'         => $asset->location,
                'allocation_status'=> $asset->allocation_status,
                'staff_id'         => $asset->staff_id,
                'condition'        => $asset->condition,
                'notes'            => $asset->notes,
                'created_by'       => $user?->name ?? 'System',
                'last_updated_by'  => $user?->name ?? 'System',
                'archived_by'      => $user?->name ?? 'System',
                'archived_date'    => now(),
            ]);

            // Remove specs and asset
            ITAssetSpec::where('asset_id', $asset->asset_id)->delete();
            $asset->delete();
        });

        return back()->with('success', 'Asset archived successfully!');
    }

    // ─── Archived List ────────────────────────────────────────────────────────

    public function archived(Request $request): Response
    {
        $query = ITAssetArchive::with('category');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                  ->orWhere('name',     'like', "%{$search}%")
                  ->orWhere('staff_id', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $archives = $query->orderByDesc('archived_date')->paginate(15)->withQueryString();

        $archives->getCollection()->transform(fn ($a) => [
            'archive_id'        => $a->archive_id,
            'asset_id'          => $a->asset_id,
            'asset_tag'         => $a->asset_tag,
            'category_name'     => $a->category?->name ?? 'Unknown',
            'name'              => $a->name,
            'brand'             => $a->brand,
            'model'             => $a->model,
            'location'          => $a->location,
            'allocation_status' => $a->allocation_status,
            'condition'         => $a->condition,
            'archived_by'       => $a->archived_by,
            'archived_date'     => $a->archived_date?->format('Y-m-d H:i'),
        ]);

        return Inertia::render('ITAssets/Archived', [
            'archives' => $archives,
            'flash'    => ['success' => session('success'), 'error' => session('error')],
        ]);
    }

    // ─── Restore from Archive ─────────────────────────────────────────────────

    public function restore(int $archiveId)
    {
        $archived = ITAssetArchive::findOrFail($archiveId);

        // Check if asset_tag is already taken in active assets
        if (ITAsset::where('asset_tag', $archived->asset_tag)->exists()) {
            return back()->withErrors(['restore' => 'An active asset with tag ' . $archived->asset_tag . ' already exists.']);
        }

        DB::transaction(function () use ($archived) {
            ITAsset::create([
                'category_id'       => $archived->category_id,
                'asset_tag'         => $archived->asset_tag,
                'name'              => $archived->name,
                'brand'             => $archived->brand,
                'model'             => $archived->model,
                'serial_number'     => $archived->serial_number,
                'purchase_date'     => $archived->purchase_date,
                'warranty_expiry'   => $archived->warranty_expiry,
                'location'          => $archived->location,
                'allocation_status' => $archived->allocation_status,
                'staff_id'          => $archived->staff_id,
                'condition'         => $archived->condition,
                'notes'             => $archived->notes,
            ]);

            $archived->delete();
        });

        return back()->with('success', 'Asset restored successfully!');
    }

    // ─── Export ───────────────────────────────────────────────────────────────

    public function export(Request $request): StreamedResponse
    {
        $query = ITAsset::with(['category', 'specs']);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_tag', 'like', "%{$search}%")
                  ->orWhere('name',     'like', "%{$search}%")
                  ->orWhere('brand',    'like', "%{$search}%")
                  ->orWhere('staff_name', 'like', "%{$search}%");
            });
        }
        if ($cat = $request->input('category_id'))         $query->where('category_id', $cat);
        if ($st  = $request->input('allocation_status'))   $query->where('allocation_status', $st);
        if ($cnd = $request->input('condition'))           $query->where('condition', $cnd);

        $assets = $query->orderByDesc('asset_id')->get();

        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle('IT Assets');

        $headers = [
            'A' => 'Asset Tag',       'B' => 'Category',
            'C' => 'Name',            'D' => 'Brand',
            'E' => 'Model',           'F' => 'Serial Number',
            'G' => 'Location',        'H' => 'Allocation Status',
            'I' => 'Staff ID',        'J' => 'Staff Name',
            'K' => 'Condition',       'L' => 'Purchase Date',
            'M' => 'Warranty Expiry', 'N' => 'Notes',
            'O' => 'Date Added',
        ];

        foreach ($headers as $col => $label) {
            $cell = $col . '1';
            $sheet->setCellValue($cell, $label);
            $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(11);
            $sheet->getStyle($cell)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF3E64D4');
            $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        $row = 2;
        foreach ($assets as $asset) {
            $values = [
                'A' => $asset->asset_tag,
                'B' => $asset->category?->name ?? '',
                'C' => $asset->name,
                'D' => $asset->brand ?? '',
                'E' => $asset->model,
                'F' => $asset->serial_number ?? '',
                'G' => $asset->location,
                'H' => $asset->allocation_status,
                'I' => $asset->staff_id ?? '',
                'J' => $asset->staff_name ?? '',
                'K' => $asset->condition,
                'L' => $asset->purchase_date?->format('Y-m-d') ?? '',
                'M' => $asset->warranty_expiry?->format('Y-m-d') ?? '',
                'N' => $asset->notes ?? '',
                'O' => $asset->created_date?->format('Y-m-d') ?? '',
            ];

            foreach ($values as $col => $val) {
                $sheet->setCellValue($col . $row, $val);
            }

            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:O{$row}")
                      ->getFill()->setFillType(Fill::FILL_SOLID)
                      ->getStartColor()->setARGB('FFF0F4FF');
            }
            $row++;
        }

        foreach (array_keys($headers) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer   = new Xlsx($spreadsheet);
        $filename = 'IT_Assets_' . now()->format('Y-m-d') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // ─── Private Helpers ──────────────────────────────────────────────────────

    private function validateAssetRequest(Request $request): array
    {
        return $request->validate([
            'category_id'       => 'required|integer|exists:categoriesnew,category_id',
            'name'              => 'required|string|max:100',
            'brand'             => 'nullable|string|max:50',
            'model'             => 'required|string|max:50',
            'serial_number'     => 'nullable|string|max:50',
            'purchase_date'     => 'nullable|date',
            'warranty_expiry'   => 'nullable|date',
            'location'          => 'required|string|max:100',
            'allocation_status' => 'required|in:Allocated,Unallocated',
            'staff_id'          => 'nullable|string|max:50',
            'staff_name'        => 'nullable|string|max:100',
            'condition'         => 'required|in:New,Good,Needs Repair,Obsolete',
            'notes'             => 'nullable|string',
            // Specs
            'laptop_os'       => 'nullable|string|max:50',
            'laptop_ram'      => 'nullable|string|max:20',
            'laptop_storage'  => 'nullable|string|max:50',
            'desktop_os'      => 'nullable|string|max:50',
            'desktop_ram'     => 'nullable|string|max:20',
            'desktop_storage' => 'nullable|string|max:50',
            'desktop_monitor' => 'nullable|string|max:50',
            'mobile_os'       => 'nullable|string|max:50',
            'mobile_storage'  => 'nullable|string|max:20',
            'mobile_phone'    => 'nullable|string|max:20',
            'printer_type'    => 'nullable|string|max:20',
            'printer_ip'      => 'nullable|string|max:50',
            'router_ip'       => 'nullable|string|max:50',
            'router_ssid'     => 'nullable|string|max:100',
            'other_type'      => 'nullable|string|max:50',
            'other_specs'     => 'nullable|string|max:255',
        ]);
    }

    private function syncSpecs(int $assetId, int $categoryId, array $data): void
    {
        $specs = match ($categoryId) {
            1 => ['Operating System' => $data['laptop_os'] ?? '',   'RAM' => $data['laptop_ram'] ?? '',   'Storage' => $data['laptop_storage'] ?? ''],
            2 => ['Operating System' => $data['desktop_os'] ?? '',  'RAM' => $data['desktop_ram'] ?? '',  'Storage' => $data['desktop_storage'] ?? '',  'Monitor' => $data['desktop_monitor'] ?? ''],
            3 => ['Operating System' => $data['mobile_os'] ?? '',   'Storage' => $data['mobile_storage'] ?? '', 'Phone Number' => $data['mobile_phone'] ?? ''],
            4 => ['Type' => $data['printer_type'] ?? '',            'IP Address' => $data['printer_ip'] ?? ''],
            5 => ['IP Address' => $data['router_ip'] ?? '',         'SSID' => $data['router_ssid'] ?? ''],
            6 => ['Type' => $data['other_type'] ?? '',              'Specifications' => $data['other_specs'] ?? ''],
            default => [],
        };

        foreach ($specs as $key => $value) {
            $value = trim($value);
            if ($value === '') continue;
            ITAssetSpec::create([
                'asset_id'    => $assetId,
                'category_id' => $categoryId,
                'spec_key'    => $key,
                'spec_value'  => substr($value, 0, 100),
            ]);
        }
    }

    private function transformAsset(ITAsset $asset): array
    {
        $specsMap = $asset->specs->pluck('spec_value', 'spec_key')->toArray();
        return [
            'asset_id'          => $asset->asset_id,
            'asset_tag'         => $asset->asset_tag,
            'category_id'       => $asset->category_id,
            'category_name'     => $asset->category?->name ?? 'Unknown',
            'name'              => $asset->name,
            'brand'             => $asset->brand,
            'model'             => $asset->model,
            'serial_number'     => $asset->serial_number,
            'purchase_date'     => $asset->purchase_date?->format('Y-m-d'),
            'warranty_expiry'   => $asset->warranty_expiry?->format('Y-m-d'),
            'location'          => $asset->location,
            'allocation_status' => $asset->allocation_status,
            'staff_id'          => $asset->staff_id,
            'staff_name'        => $asset->staff_name,
            'condition'         => $asset->condition,
            'notes'             => $asset->notes,
            'specs'             => $specsMap,
            'created_at'        => $asset->created_date?->format('Y-m-d'),
        ];
    }
}
