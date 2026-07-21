<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Concerns\ResourceResponses;
use App\Http\Controllers\Controller;
use App\Models\FundCluster;
use App\Models\Release;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class FundClusterController extends Controller
{
    use ResourceResponses;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::eloquent(FundCluster::query())
                ->addColumn('status', fn (FundCluster $f) => view('setup.partials.toggle', [
                    'model' => $f, 'url' => route('fund-clusters.toggle', $f),
                ])->render())
                ->addColumn('action', fn (FundCluster $f) => view('setup.partials.actions', [
                    'edit' => ['fund_clusters', $f->only(['id', 'code', 'name'])],
                    'deleteUrl' => route('fund-clusters.destroy', $f),
                    'label' => 'fund cluster',
                    'resource' => 'fund_clusters',
                ])->render())
                ->rawColumns(['status', 'action'])
                ->toJson();
        }

        return view('setup.fund-clusters');
    }

    public function store(Request $request)
    {
        FundCluster::create($this->validateData($request));

        return $this->ok($request, 'Fund cluster created.');
    }

    public function update(Request $request, FundCluster $fundCluster)
    {
        $fundCluster->update($this->validateData($request, $fundCluster->id));

        return $this->ok($request, 'Fund cluster updated.');
    }

    public function destroy(Request $request, FundCluster $fundCluster)
    {
        if (Release::where('fund_cluster_id', $fundCluster->id)->exists()) {
            return $this->fail($request, 'Cannot delete: this fund cluster is used by releases.', 409);
        }

        $fundCluster->delete();

        return $this->ok($request, 'Fund cluster deleted.');
    }

    public function toggle(Request $request, FundCluster $fundCluster)
    {
        $fundCluster->update(['is_active' => $request->boolean('is_active')]);

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('fund_clusters', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
}
