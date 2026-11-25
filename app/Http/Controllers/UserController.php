<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Centre;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index');
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);

        return view('users.create', ['roles' => Role::query()->orderBy('label')->get(), 'centres' => Centre::query()->where('is_active', true)->orderBy('name')->get(), 'selectedCentreIds' => []]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('roles.manage'), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', 'distinct', 'exists:roles,id'],
            'centre_ids' => ['nullable', 'array'],
            'centre_ids.*' => ['integer', 'distinct', 'exists:centres,id'],
        ]);
        $data['email'] = mb_strtolower($data['email']);
        $roleIds = $data['roles'];
        $centreIds = $data['centre_ids'] ?? [];
        unset($data['roles'], $data['centre_ids'], $data['password_confirmation']);

        DB::transaction(function () use ($data, $roleIds, $centreIds, $request): void {
            $user = User::query()->create($data);
            $user->roles()->sync($roleIds);
            $user->centres()->sync($centreIds);
            AuditLog::record($request, 'user.created', $user, null, [
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->all(),
            ]);
        });

        return redirect()->route('users.index')->with('status', 'User account created.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', [
            'user' => $user->load('roles'),
            'roles' => Role::query()->orderBy('label')->get(),
            'centres' => Centre::query()->where('is_active', true)->orderBy('name')->get(),
            'selectedCentreIds' => $user->centres()->pluck('centres.id')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(12)->mixedCase()->numbers()],
            'roles' => ['sometimes', 'array', 'min:1'],
            'roles.*' => ['integer', 'distinct', 'exists:roles,id'],
            'centre_ids' => ['sometimes', 'nullable', 'array'],
            'centre_ids.*' => ['integer', 'distinct', 'exists:centres,id'],
        ]);
        $canManageRoles = $request->user()->hasPermission('roles.manage');
        abort_if((isset($data['roles']) || array_key_exists('centre_ids', $data)) && ! $canManageRoles, 403);
        abort_if($user->is($request->user()) && isset($data['roles']), 403, 'You cannot change your own roles.');
        DB::transaction(function () use ($data, $request, $user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->is_active && $user->hasRole('administrator') && isset($data['roles'])) {
                $activeAdmins = User::query()->where('is_active', true)
                    ->whereHas('roles', fn ($query) => $query->where('roles.name', 'administrator'))
                    ->orderBy('users.id')->lockForUpdate()->pluck('users.id');
                $keepsAdministrator = Role::query()->whereKey($data['roles'])->where('name', 'administrator')->exists();

                abort_if(! $keepsAdministrator && $activeAdmins->count() <= 1, 422, 'The last active administrator cannot lose the administrator role.');
            }

            $before = [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->roles()->pluck('name')->all(),
            ];
            $user->name = $data['name'];
            $user->email = mb_strtolower($data['email']);

            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            $user->save();

            if (isset($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }

            if (array_key_exists('centre_ids', $data)) {
                $user->centres()->sync($data['centre_ids'] ?? []);
            }

            AuditLog::record($request, 'user.updated', $user, $before, [
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'roles' => $user->roles()->pluck('name')->all(),
            ]);
        });

        return redirect()->route('users.index')->with('status', 'User account updated.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 403, 'You cannot deactivate your own account.');
        DB::transaction(function () use ($request, $user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            if ($user->is_active && $user->hasRole('administrator')) {
                $activeAdmins = User::query()->where('is_active', true)
                    ->whereHas('roles', fn ($query) => $query->where('roles.name', 'administrator'))
                    ->orderBy('users.id')->lockForUpdate()->pluck('users.id');
                abort_if($activeAdmins->count() <= 1, 422, 'The last active administrator cannot be deactivated.');
            }

            $wasActive = $user->is_active;
            $user->forceFill(['is_active' => false])->save();
            AuditLog::record($request, 'user.deactivated', $user, ['is_active' => $wasActive], ['is_active' => false]);
        });

        return redirect()->route('users.index')->with('status', 'User account deactivated.');
    }

    public function activate(Request $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $wasActive = $user->is_active;
            $user->forceFill(['is_active' => true])->save();
            AuditLog::record($request, 'user.activated', $user, ['is_active' => $wasActive], ['is_active' => true]);
        });

        return redirect()->route('users.index')->with('status', 'User account activated.');
    }
}
