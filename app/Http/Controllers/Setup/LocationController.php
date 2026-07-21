<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Concerns\ResourceResponses;
use App\Http\Controllers\Controller;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class LocationController extends Controller
{
    use ResourceResponses;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::eloquent(Location::query())
                ->editColumn('type', fn (Location $l) => view('components.ui.badge', [
                    'color' => $l->type === 'campus' ? 'green' : 'blue',
                    'slot' => ucfirst($l->type),
                ])->render())
                ->addColumn('status', fn (Location $l) => view('setup.partials.toggle', [
                    'model' => $l, 'url' => route('locations.toggle', $l),
                ])->render())
                ->addColumn('action', fn (Location $l) => view('setup.partials.actions', [
                    'edit' => ['locations', $l->only(['id', 'type', 'code', 'name'])],
                    'deleteUrl' => route('locations.destroy', $l),
                    'label' => 'location',
                    'resource' => 'locations',
                ])->render())
                ->filterColumn('type', fn ($q, $kw) => $q->where('type', 'like', "%{$kw}%"))
                ->rawColumns(['type', 'status', 'action'])
                ->toJson();
        }

        return view('setup.locations');
    }

    public function store(Request $request)
    {
        Location::create($this->validateData($request));

        return $this->ok($request, 'Location created.');
    }

    public function update(Request $request, Location $location)
    {
        $location->update($this->validateData($request, $location->id));

        return $this->ok($request, 'Location updated.');
    }

    public function destroy(Request $request, Location $location)
    {
        if ($location->releases()->exists()) {
            return $this->fail($request, 'Cannot delete: this location has releases on record.', 409);
        }

        $location->delete();

        return $this->ok($request, 'Location deleted.');
    }

    public function toggle(Request $request, Location $location)
    {
        $location->update(['is_active' => $request->boolean('is_active')]);

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'type' => ['required', Rule::in(['campus', 'office'])],
            'code' => ['required', 'string', 'max:50', Rule::unique('locations', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
}
