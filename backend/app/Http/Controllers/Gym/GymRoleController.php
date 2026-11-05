<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymPersonType;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\Permission;
use App\Models\User;
use App\Support\GymRoleAssigner;
use App\Support\PermissionCatalog;
use App\Support\RoleGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class GymRoleController extends Controller
{
    public function catalog(): JsonResponse
    {
        $permissions = Permission::active()->orderBy('id')
            ->get(['id', 'module', 'resource', 'action', 'permission_string', 'name', 'description']);

        return response()->json([
            'permissions' => $permissions,
            'templates' => collect(config('access.templates'))->map(fn (array $levels, string $key) => [
                'name' => config("access.names.{$key}", Str::headline($key)),
                'permissions' => array_values(array_intersect(PermissionCatalog::expandLevels($levels), $permissions->pluck('permission_string')->all())),
            ]),
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json(['roles' => GymRole::withCount('users')->with('permissions')->orderBy('name')->get()
            ->map(fn (GymRole $role) => $this->payload($role))]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $this->validated($request, $gym);
        RoleGuard::ensureWithin($request->user()->permissionsIn($gym), $data['permissions']);
        $role = DB::transaction(function () use ($data) {
            $role = GymRole::create(['name' => $data['name'], 'description' => $data['description']]);
            $role->syncPermissionStrings($data['permissions']);

            return $role;
        });

        return response()->json(['role' => $this->payload($role->fresh())], 201);
    }

    public function update(Request $request, Gym $gym, int $role): JsonResponse
    {
        $role = DB::transaction(function () use ($request, $gym, $role) {
            $role = GymRole::lockForUpdate()->findOrFail($role);
            RoleGuard::ensureNotHeld($request->user(), $role);
            $data = $this->validated($request, $gym, $role);
            $own = $request->user()->permissionsIn($gym);
            RoleGuard::ensureCleanupWithin($own, $role->permissionStrings());
            $added = array_diff($data['permissions'], $role->permissionStrings());
            RoleGuard::ensureWithin($own, $added);
            RoleGuard::ensureCleanupWithin($own, $data['permissions']);
            $role->update(['name' => $data['name'], 'description' => $data['description']]);
            $role->syncPermissionStrings($data['permissions']);

            return $role;
        });

        return response()->json(['role' => $this->payload($role->fresh())]);
    }

    public function destroy(Request $request, Gym $gym, int $role): JsonResponse
    {
        DB::transaction(function () use ($request, $gym, $role) {
            $role = GymRole::lockForUpdate()->findOrFail($role);
            RoleGuard::ensureNotHeld($request->user(), $role);
            RoleGuard::ensureCleanupWithin($request->user()->permissionsIn($gym), $role->permissionStrings());
            abort_if($role->is_system, 422, 'Starter roles cannot be deleted. Turn the role off instead.');
            abort_if($role->users()->exists(), 422, 'This role is still given to employees. Take it away from them before deleting it.');
            $role->delete();
        });

        return response()->json(['message' => 'Role deleted.']);
    }

    public function staffRoles(Gym $gym, User $user): JsonResponse
    {
        $this->ensureEmployee($gym, $user);

        return response()->json(['roles' => $this->rolesOf($gym, $user)]);
    }

    public function toggleStatus(Request $request, Gym $gym, int $role): JsonResponse
    {
        $role = DB::transaction(function () use ($request, $gym, $role) {
            $role = GymRole::lockForUpdate()->findOrFail($role);
            RoleGuard::ensureNotHeld($request->user(), $role);
            $own = $request->user()->permissionsIn($gym);
            if ($role->status === 'active') {
                RoleGuard::ensureCleanupWithin($own, $role->permissionStrings());
            } else {
                RoleGuard::ensureWithin($own, $role->permissionStrings());
            }
            $role->update(['status' => $role->status === 'active' ? 'inactive' : 'active']);

            return $role;
        });

        return response()->json(['role' => $this->payload($role->fresh())]);
    }

    public function assign(Request $request, Gym $gym, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer', 'distinct'],
            'expires_at' => ['nullable', 'array'],
            'expires_at.*' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);
        abort_if(array_diff(array_keys($data['expires_at'] ?? []), $data['role_ids']) !== [], 422, 'End dates must belong to selected roles.');
        app(GymRoleAssigner::class)->replace($request->user(), $gym, $user, $data['role_ids'], $data['expires_at'] ?? []);

        return response()->json(['roles' => $this->rolesOf($gym, $user)]);
    }

    private function rolesOf(Gym $gym, User $user): array
    {
        return $user->gymRoles($gym)->orderBy('name')->get()->map(fn (GymRole $role) => [
            ...$this->payload($role),
            'expires_at' => $role->users()->whereKey($user->id)->first()?->pivot->expires_at,
        ])->all();
    }

    private function payload(GymRole $role): array
    {
        return [
            'id' => $role->id, 'name' => $role->name, 'description' => $role->description,
            'status' => $role->status, 'is_system' => $role->is_system,
            'permissions' => $role->permissions->pluck('permission_string')->values(),
            'inactive_permissions' => $role->permissions->where('status', 'inactive')->pluck('permission_string')->values(),
            'users_count' => $role->users_count ?? $role->users()->count(),
            'held_by_me' => auth()->check() && $role->users()->whereKey(auth()->id())->exists(),
        ];
    }

    private function validated(Request $request, Gym $gym, ?GymRole $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60',
                function (string $attribute, string $value, $fail) use ($gym, $role) {
                    if (GymRole::withoutGlobalScope('gym')->where('gym_id', $gym->id)
                        ->when($role, fn ($query) => $query->where('id', '!=', $role->id))
                        ->whereRaw('LOWER(name) = ?', [Str::lower($value)])->exists()) {
                        $fail('This role name is already in use.');
                    }
                }],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::exists('permissions', 'permission_string')],
        ]);

        return ['name' => $data['name'], 'description' => $data['description'] ?? null, 'permissions' => array_values($data['permissions'])];
    }

    private function ensureEmployee(Gym $gym, User $user): void
    {
        abort_unless($user->hasGymRole($gym, GymPersonType::Employee), 404);
    }
}
