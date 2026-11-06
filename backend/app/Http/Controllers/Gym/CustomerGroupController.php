<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsCustomers;
use App\Models\CustomerGroup;
use App\Models\Gym;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** R-71: named customer groups for later announcement audiences. */
class CustomerGroupController extends Controller
{
    use PresentsCustomers;

    public function index(Gym $gym): JsonResponse
    {
        $groups = $gym->customerGroups()->with('members.membership')->orderBy('name')->orderBy('id')->get();

        return response()->json([
            'groups' => $groups->map(fn (CustomerGroup $group) => $this->groupPayload($group))->values(),
        ]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate($this->rules($gym));
        $group = $gym->customerGroups()->create($data)->load('members.membership');

        return response()->json(['group' => $this->groupPayload($group)], 201);
    }

    public function update(Request $request, Gym $gym, int $group): JsonResponse
    {
        $model = $gym->customerGroups()->findOrFail($group);
        $data = $request->validate($this->rules($gym, $model));

        $model->fill($data)->save();

        return response()->json(['group' => $this->groupPayload($model->load('members.membership'))]);
    }

    public function destroy(Gym $gym, int $group): JsonResponse|Response
    {
        $gym->customerGroups()->findOrFail($group)->delete();

        return response()->noContent();
    }

    /**
     * Replaces the whole member set. Customer ids attach directly. A user id
     * resolves to that person's existing customer row, or creates one (R-71).
     */
    public function syncMembers(Request $request, Gym $gym, int $group): JsonResponse
    {
        $model = $gym->customerGroups()->findOrFail($group);

        $data = $request->validate([
            'customer_ids' => ['sometimes', 'array'],
            'customer_ids.*' => ['integer', 'distinct', 'min:1'],
            'members' => ['sometimes', 'array'],
            'members.*' => ['integer', 'distinct', 'min:1'],
            'user_ids' => ['sometimes', 'array'],
            'user_ids.*' => ['integer', 'distinct', 'min:1'],
        ]);

        $customerIds = array_values(array_unique([
            ...($data['customer_ids'] ?? []),
            ...($data['members'] ?? []),
        ]));
        $userIds = array_values(array_unique($data['user_ids'] ?? []));

        DB::transaction(function () use ($request, $gym, $model, $customerIds, $userIds) {
            $customers = $gym->customers()->whereIn('id', $customerIds)->get();
            abort_if($customers->count() !== count($customerIds), 404);

            foreach ($userIds as $userId) {
                $user = $gym->people()->whereKey($userId)->first();
                abort_if(! $user, 404);

                $customer = $gym->customers()->where('user_id', $user->id)->first();

                if (! $customer) {
                    $customer = $gym->customers()->create([
                        'name' => trim("{$user->first_name} {$user->last_name}"),
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'source' => 'other',
                        'user_id' => $user->id,
                        'created_by' => $request->user()->id,
                    ]);
                }

                $customers->push($customer);
            }

            $model->members()->sync($customers->pluck('id')->unique()->values()->all());
        });

        $groupPayload = $this->groupPayload($model->load('members.membership'));

        return response()->json([
            'group' => $groupPayload,
            'members' => $groupPayload['members'],
        ]);
    }

    private function rules(Gym $gym, ?CustomerGroup $ignore = null): array
    {
        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('customer_groups', 'name')->where('gym_id', $gym->id)->ignore($ignore?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
