<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Concerns\ResourceResponses;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class UnitController extends Controller
{
    use ResourceResponses;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::eloquent(Unit::query())
                ->addColumn('action', fn (Unit $u) => view('setup.partials.actions', [
                    'edit' => ['units', $u->only(['id', 'name', 'abbreviation'])],
                    'deleteUrl' => route('units.destroy', $u),
                    'label' => 'unit',
                    'resource' => 'units',
                ])->render())
                ->rawColumns(['action'])
                ->toJson();
        }

        return view('setup.units');
    }

    public function store(Request $request)
    {
        Unit::create($this->validateData($request));

        return $this->ok($request, 'Unit created.');
    }

    public function update(Request $request, Unit $unit)
    {
        $unit->update($this->validateData($request));

        return $this->ok($request, 'Unit updated.');
    }

    public function destroy(Request $request, Unit $unit)
    {
        if ($unit->id && (\App\Models\Item::where('unit_id', $unit->id)->exists())) {
            return $this->fail($request, 'Cannot delete: this unit is used by one or more items.', 409);
        }

        $unit->delete();

        return $this->ok($request, 'Unit deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abbreviation' => ['required', 'string', 'max:20'],
        ]);
    }
}
