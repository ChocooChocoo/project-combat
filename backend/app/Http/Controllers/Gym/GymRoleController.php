<?php

namespace App\Http\Controllers\Gym;

use App\Enums\AccessLevel;
use App\Enums\GymPersonType;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymRole;
use App\Models\User;
use App\Support\GymRoleAssigner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * R-08 "dynamic RBAC": each owner creates, edits and deletes their gym's own roles
 * and gives them to employees. Nobody may create, change, delete or give a role
 * above their own access. Roles are found through the gym rule (R-09), so another
 * gym's role ids simply do not exist here.
 */
class GymRoleController extends Controller
{
    public function templates(): JsonResponse
    {
        return response()->json([
            'modules' => array_column(Module::cases(), 'value'),
            'levels' => array_column(AccessLevel::cases(), 'value'),
            'templates' => config('access.templates'),
        ]);
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'roles' => GymRole::withCount('users')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $this->validated($request, $gym);
        $this->ensureWithinOwnAccess($request->user(), $gym, $data['permissions']);

        return response()->json(['role' => GymRole::create($data)], 201);
    }

    public function update(Request $request, Gym $gym, int $role): JsonResponse
    {
        $role = GymRole::findOrFail($role);
        $data = $this->validated($request, $gym, $role);
        $this->ensureWithinOwnAccess($request->user(), $gym, $role->permissions);
        $this->ensureWithinOwnAccess($request->user(), $gym, $data['permissions']);

        $role->update($data);

        return response()->json(['role' => $role]);
    }

    public function destroy(Request $request, Gym $gym, int $role): JsonResponse
    {
        $role = GymRole::findOrFail($role);
        $this->ensureWithinOwnAccess($request->user(), $gym, $role->permissions);
        abort_if($role->users()->exists(), 422, 'This role is still given to employees. Take it away from them before deleting it.');
        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    public function staffRoles(Gym $gym, User $user): JsonResponse
    {
        $this->ensureEmployee($gym, $user);

        return response()->json(['roles' => $user->gymRoles($gym)->orderBy('name')->get()]);
    }

    /** Replaces the employee's roles with the ones sent. */
    public function assign(Request $request, Gym $gym, User $user): JsonResponse
    {
        $data = $request->validate([
            'role_ids' => ['present', 'array'],
            'role_ids.*' => ['integer', 'distinct'],
        ]);

        app(GymRoleAssigner::class)->replace($request->user(), $gym, $user, $data['role_ids']);

        return response()->json(['roles' => $user->gymRoles($gym)->orderBy('name')->get()]);
    }

    /** @return array{name: string, permissions: array<string, string>} */
    private function validated(Request $request, Gym $gym, ?GymRole $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60',
                Rule::unique('gym_roles')->where('gym_id', $gym->id)->ignore($role?->id)],
            'template' => ['nullable', Rule::in(array_keys(config('access.templates')))],
            'permissions' => ['array'],
            'permissions.*' => ['nullable', Rule::enum(AccessLevel::class)],
        ]);

        $unknown = array_diff(array_keys($data['permissions'] ?? []), array_column(Module::cases(), 'value'));
        abort_if($unknown !== [], 422, 'Unknown module: '.implode(', ', $unknown));

        // A template fills in the starting levels; anything sent explicitly wins over it.
        $permissions = array_filter(array_merge(
            isset($data['template']) ? config('access.templates.'.$data['template']) : [],
            $data['permissions'] ?? [],
        ));

        return ['name' => $data['name'], 'permissions' => $permissions];
    }

    /** @param array<string, string> $permissions */
    private function ensureWithinOwnAccess(User $editor, Gym $gym, array $permissions): void
    {
        foreach ($permissions as $module => $level) {
            $own = $editor->accessLevel($gym, Module::from($module));

            if (! $own?->covers(AccessLevel::from($level))) {
                abort(403, "You cannot handle a role with {$module} access above your own.");
            }
        }
    }

    private function ensureEmployee(Gym $gym, User $user): void
    {
        abort_unless($user->hasGymRole($gym, GymPersonType::Employee), 404);
    }
}
