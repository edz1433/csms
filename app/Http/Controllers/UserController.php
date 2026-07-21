<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResourceResponses;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class UserController extends Controller
{
    use ResourceResponses;

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return DataTables::eloquent(User::query())
                ->editColumn('role', fn (User $u) => view('users.partials.role-badge', ['role' => $u->role])->render())
                ->addColumn('access', fn (User $u) => view('users.partials.access-badges', ['user' => $u])->render())
                ->addColumn('status', fn (User $u) => $u->is_active
                    ? '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cpsu-green/10 text-cpsu-green">Active</span>'
                    : '<span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-500">Inactive</span>')
                ->addColumn('action', fn (User $u) => view('users.partials.actions', ['user' => $u])->render())
                ->filterColumn('role', fn ($q, $kw) => $q->where('role', 'like', "%{$kw}%"))
                ->rawColumns(['role', 'access', 'status', 'action'])
                ->toJson();
        }

        return view('users.index');
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['password'] = Hash::make($data['password']);
        $data['access'] = $this->normalizeAccess($data);

        User::create($data);

        return $this->ok($request, 'User created.');
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validateData($request, $user->id);

        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['access'] = $this->normalizeAccess($data);

        $user->update($data);

        return $this->ok($request, 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return $this->fail($request, 'You cannot delete your own account.', 409);
        }

        $user->delete();

        return $this->ok($request, 'User deleted.');
    }

    public function resetPassword(Request $request, User $user)
    {
        $temp = Str::password(10, symbols: false);
        $user->update(['password' => Hash::make($temp)]);

        return response()->json([
            'ok' => true,
            'temp_password' => $temp,
            'name' => $user->name,
        ]);
    }

    /** Administrators are full-access, so their access array is cleared. */
    private function normalizeAccess(array $data): ?array
    {
        if (($data['role'] ?? null) === User::ROLE_ADMIN) {
            return null;
        }

        return array_values(array_intersect($data['access'] ?? [], config('access.pages')));
    }

    private function validateData(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$id ? 'nullable' : 'required', 'string', 'min:8'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_SUPPLY, User::ROLE_ACCOUNTING])],
            'access' => ['nullable', 'array'],
            'access.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
