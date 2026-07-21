<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Concerns\ResourceResponses;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class SupplierController extends Controller
{
    use ResourceResponses;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::eloquent(Supplier::query())
                ->editColumn('contact_person', fn (Supplier $s) => $s->contact_person ?: '<span class="text-gray-300">—</span>')
                ->editColumn('contact_number', fn (Supplier $s) => $s->contact_number ?: '<span class="text-gray-300">—</span>')
                ->addColumn('status', fn (Supplier $s) => view('setup.partials.toggle', [
                    'model' => $s, 'url' => route('suppliers.toggle', $s),
                ])->render())
                ->addColumn('action', fn (Supplier $s) => view('setup.partials.actions', [
                    'edit' => ['suppliers', $s->only(['id', 'name', 'contact_person', 'contact_number', 'email', 'address'])],
                    'deleteUrl' => route('suppliers.destroy', $s),
                    'label' => 'supplier',
                    'resource' => 'suppliers',
                ])->render())
                ->rawColumns(['contact_person', 'contact_number', 'status', 'action'])
                ->toJson();
        }

        return view('setup.suppliers');
    }

    public function store(Request $request)
    {
        Supplier::create($this->validateData($request));

        return $this->ok($request, 'Supplier created.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validateData($request));

        return $this->ok($request, 'Supplier updated.');
    }

    public function destroy(Request $request, Supplier $supplier)
    {
        if (Delivery::where('supplier_id', $supplier->id)->exists()) {
            return $this->fail($request, 'Cannot delete: this supplier has deliveries on record.', 409);
        }

        $supplier->delete();

        return $this->ok($request, 'Supplier deleted.');
    }

    public function toggle(Request $request, Supplier $supplier)
    {
        $supplier->update(['is_active' => $request->boolean('is_active')]);

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
