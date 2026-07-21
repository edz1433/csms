<?php

namespace App\Http\Controllers\Setup;

use App\Http\Controllers\Concerns\ResourceResponses;
use App\Http\Controllers\Controller;
use App\Models\AccountTitle;
use App\Models\Item;
use App\Models\ReleaseItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class AccountTitleController extends Controller
{
    use ResourceResponses;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::eloquent(AccountTitle::query())
                ->editColumn('rca_code', fn (AccountTitle $a) => '<span class="font-mono text-cpsu-green font-semibold">'.e($a->rca_code).'</span>')
                ->addColumn('status', fn (AccountTitle $a) => view('setup.partials.toggle', [
                    'model' => $a, 'url' => route('account-titles.toggle', $a),
                ])->render())
                ->addColumn('action', fn (AccountTitle $a) => view('setup.partials.actions', [
                    'edit' => ['account_titles', $a->only(['id', 'rca_code', 'name'])],
                    'deleteUrl' => route('account-titles.destroy', $a),
                    'label' => 'account title',
                    'resource' => 'account_titles',
                ])->render())
                ->rawColumns(['rca_code', 'status', 'action'])
                ->toJson();
        }

        return view('setup.account-titles');
    }

    public function store(Request $request)
    {
        AccountTitle::create($this->validateData($request));

        return $this->ok($request, 'Account title created.');
    }

    public function update(Request $request, AccountTitle $accountTitle)
    {
        $accountTitle->update($this->validateData($request, $accountTitle->id));

        return $this->ok($request, 'Account title updated.');
    }

    public function destroy(Request $request, AccountTitle $accountTitle)
    {
        if (ReleaseItem::where('account_title_id', $accountTitle->id)->exists()
            || Item::where('account_title_id', $accountTitle->id)->exists()) {
            return $this->fail($request, 'Cannot delete: this account title is in use.', 409);
        }

        $accountTitle->delete();

        return $this->ok($request, 'Account title deleted.');
    }

    public function toggle(Request $request, AccountTitle $accountTitle)
    {
        $accountTitle->update(['is_active' => $request->boolean('is_active')]);

        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'rca_code' => ['required', 'string', 'max:50', Rule::unique('account_titles', 'rca_code')->ignore($id)],
            'name' => ['required', 'string', 'max:255'],
        ]);
    }
}
