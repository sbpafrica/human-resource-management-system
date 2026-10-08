<?php
namespace App\Http\Controllers;

use App\Models\BankCode;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BankCodeController extends Controller
{
    public function index(Request $request)
    {
        $query = BankCode::query();
        $totalRecords = BankCode::count();
        $searchCount = null;

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('bankname', 'like', "%{$search}%")
                  ->orWhere('branchcodes', 'like', "%{$search}%")
                  ->orWhere('branch', 'like', "%{$search}%");
            });
            $searchCount = $query->count();
        }

        $sort = $request->input('sort', 'bankname');
        $order = $request->input('order', 'asc');
        $query->orderBy($sort, $order);
        $bankCodes = $query->paginate(10)->withQueryString();

        return Inertia::render('BankCodes/Index', [
            'bankCodes' => $bankCodes,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'order' => $order,
            ],
            'totalRecords' => $totalRecords,
            'searchCount' => $searchCount,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bankname' => ['required', 'string', 'max:50'],
            'branchcodes' => ['required', 'string', 'max:50', 'unique:bankcodes,branchcodes'],
            'branch' => ['nullable', 'string', 'max:50'], // Changed to nullable
        ]);

        if ($validator->fails()) {
            Log::error('Store validation failed', ['errors' => $validator->errors()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to create bank code record.');
        }

        BankCode::create($request->all());
        return redirect()->route('bankcodes.index')->with('success', 'Bank code record created successfully.');
    }

    public function update(Request $request, $id)
    {
        $bankCode = BankCode::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'bankname' => ['required', 'string', 'max:50'],
            'branchcodes' => ['required', 'string', 'max:50', Rule::unique('bankcodes')->ignore($bankCode->id)],
            'branch' => ['nullable', 'string', 'max:50'], // Changed to nullable
        ]);

        if ($validator->fails()) {
            Log::error('Update validation failed', ['errors' => $validator->errors(), 'input' => $request->all()]);
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Failed to update bank code record.');
        }

        $bankCode->update($request->all());
        return redirect()->route('bankcodes.index')->with('success', 'Bank code record updated successfully.');
    }

    public function destroy($id)
    {
        $bankCode = BankCode::findOrFail($id);
        $bankCode->delete();
        return redirect()->route('bankcodes.index')->with('success', 'Bank code record deleted successfully.');
    }

    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:bankcodes,id'],
        ]);

        $ids = $request->input('ids');
        Log::info('Bulk destroy called', ['ids' => $ids]);

        $deletedCount = BankCode::whereIn('id', $ids)->delete();

        if ($deletedCount > 0) {
            return redirect()->route('bankcodes.index')->with('success', "Successfully deleted $deletedCount bank code record(s).");
        }

        return redirect()->route('bankcodes.index')->with('error', 'No bank code records were deleted.');
    }
}
