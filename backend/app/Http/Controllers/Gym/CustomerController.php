<?php

namespace App\Http\Controllers\Gym;

use App\Enums\CustomerInteractionChannel;
use App\Enums\CustomerInteractionKind;
use App\Enums\CustomerSource;
use App\Enums\CustomerStage;
use App\Enums\CustomerStatus;
use App\Enums\MembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsCustomers;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\CustomerGroup;
use App\Models\CustomerInteraction;
use App\Models\Gym;
use App\Models\Membership;
use App\Support\CustomerLinker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-69 to R-71: customer records, history, follow-ups and membership links. */
class CustomerController extends Controller
{
    use PresentsCustomers;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'stage' => ['nullable', Rule::enum(CustomerStage::class)],
            'assigned_to' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = $gym->customers()
            ->with(['assignedTo', 'membership'])
            ->when($data['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $term = '%'.$search.'%';
                    $query->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('inquiry', 'like', $term);
                });
            })
            ->when($data['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            // The stage is derived, so it must be filtered before pagination or
            // the page count would describe a different set of rows.
            ->when($data['stage'] ?? null, fn ($query, string $stage) => $this->whereStage($query, $stage))
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'customers' => collect($page->items())->map(fn (Customer $customer) => $this->customerPayload($customer))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Gym $gym, int $customer): JsonResponse
    {
        $model = $this->find($gym, $customer);

        $followUps = $model->followUps
            ->sortBy(fn (CustomerFollowUp $followUp) => [
                $followUp->status->value === 'open' ? 0 : 1,
                $followUp->due_at?->timestamp ?? 0,
                $followUp->id,
            ])->values();

        return response()->json([
            'customer' => $this->customerPayload($model),
            'interactions' => $model->interactions->map(fn (CustomerInteraction $interaction) => $this->interactionPayload($interaction))->values(),
            'follow_ups' => $followUps->map(fn (CustomerFollowUp $followUp) => $this->followUpPayload($followUp))->values(),
            'groups' => $model->groups->sortBy('name')->values()
                ->map(fn (CustomerGroup $group) => $this->groupPayload($group))->values(),
        ]);
    }

    public function store(Request $request, Gym $gym, CustomerLinker $linker): JsonResponse
    {
        $data = $request->validate($this->rules());
        $this->assertGymPerson($gym, $data['user_id'] ?? null);
        $this->assertStaff($gym, $data['assigned_to'] ?? null);

        $membership = $linker->membershipForEmail($gym, $data['email'] ?? null);

        $customer = DB::transaction(function () use ($request, $gym, $data) {
            return $gym->customers()->create([
                ...$data,
                'status' => CustomerStatus::Open->value,
                'created_by' => $request->user()->id,
            ]);
        });

        if ($membership) {
            $customer = $linker->link($customer, $membership);
        }

        return response()->json([
            'customer' => $this->customerPayload($this->load($customer)),
        ], 201);
    }

    public function update(Request $request, Gym $gym, int $customer): JsonResponse
    {
        $model = $this->find($gym, $customer);
        $data = $request->validate($this->rules(true));

        if (array_key_exists('user_id', $data)) {
            $this->assertGymPerson($gym, $data['user_id']);
        }
        if (array_key_exists('assigned_to', $data)) {
            $this->assertStaff($gym, $data['assigned_to']);
        }

        $model->fill($data)->save();

        return response()->json(['customer' => $this->customerPayload($this->load($model))]);
    }

    public function addInteraction(Request $request, Gym $gym, int $customer): JsonResponse
    {
        $model = $this->find($gym, $customer);
        $data = $request->validate([
            'kind' => ['required', Rule::enum(CustomerInteractionKind::class)],
            'channel' => ['required', Rule::enum(CustomerInteractionChannel::class)],
            'body' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $interaction = $model->interactions()->create([
            'kind' => $data['kind'],
            'channel' => $data['channel'],
            'body' => $data['body'],
            'occurred_at' => isset($data['occurred_at']) ? Carbon::parse($data['occurred_at']) : now(),
            'recorded_by' => $request->user()->id,
        ]);

        return response()->json([
            'interaction' => $this->interactionPayload($interaction->load(['recordedBy', 'resolvedBy'])),
        ], 201);
    }

    public function resolveInteraction(Request $request, Gym $gym, int $interaction): JsonResponse
    {
        $model = $gym->customerInteractions()->with(['recordedBy', 'resolvedBy'])->findOrFail($interaction);

        if (! $model->isConcern()) {
            throw ValidationException::withMessages(['kind' => 'Only concerns can be resolved.']);
        }

        abort_if($model->isResolved(), 409, 'This concern is already resolved.');

        $data = $request->validate([
            'resolution' => ['required', 'string', 'max:5000'],
        ]);

        $model->update([
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
            'resolution' => $data['resolution'],
        ]);

        return response()->json([
            'interaction' => $this->interactionPayload($model->load(['recordedBy', 'resolvedBy'])),
        ]);
    }

    public function linkMembership(Request $request, Gym $gym, int $customer, CustomerLinker $linker): JsonResponse
    {
        $model = $this->find($gym, $customer);
        $data = $request->validate([
            'membership_id' => ['required', 'integer'],
        ]);

        // Scoped to this gym: another gym's application is a 404 (R-09, R-71).
        $membership = $gym->memberships()->findOrFail($data['membership_id']);
        $model = $linker->link($model, $membership);

        return response()->json(['customer' => $this->customerPayload($this->load($model))]);
    }

    /** Supporting route for the link control; mirrors only what the CRM screen needs. */
    public function linkableMemberships(Gym $gym): JsonResponse
    {
        $memberships = $gym->memberships()
            ->with(['user', 'plan'])
            ->whereIn('status', [MembershipStatus::Applied->value, MembershipStatus::AwaitingPayment->value])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (Membership $membership) => [
                'id' => $membership->id,
                'status' => $membership->status->value,
                'starts_on' => $membership->starts_on?->toDateString(),
                'created_at' => $membership->created_at?->toIso8601String(),
                'member_id' => $membership->user_id,
                'member_name' => $this->personName($membership->user),
                'email' => $membership->user?->email,
                'plan_name' => $membership->plan?->name,
            ])->values();

        return response()->json(['memberships' => $memberships]);
    }

    /** Mirrors Customer::getStageAttribute() in SQL so stage filters survive pagination. */
    private function whereStage($query, string $stage): void
    {
        $applicationStates = [
            MembershipStatus::Applied->value,
            MembershipStatus::Rejected->value,
            MembershipStatus::AwaitingPayment->value,
        ];

        match ($stage) {
            CustomerStage::Lost->value => $query->where('status', CustomerStatus::Lost->value),
            CustomerStage::Lead->value => $query->where('status', CustomerStatus::Open->value)
                ->whereNull('membership_id'),
            CustomerStage::Applied->value => $query->where('status', CustomerStatus::Open->value)
                ->whereNotNull('membership_id')
                ->whereHas('membership', fn ($membership) => $membership
                    ->where(fn ($membership) => $membership
                        ->whereIn('status', $applicationStates)
                        ->orWhereNull('starts_on')
                        ->orWhereDate('starts_on', '>', today()->toDateString()))),
            CustomerStage::Member->value => $query->where('status', CustomerStatus::Open->value)
                ->whereNotNull('membership_id')
                ->whereHas('membership', fn ($membership) => $membership
                    ->whereNotIn('status', $applicationStates)
                    ->whereNotNull('starts_on')
                    ->whereDate('starts_on', '<=', today()->toDateString())),
            default => null,
        };
    }

    private function find(Gym $gym, int $id): Customer
    {
        return $gym->customers()->with([
            'assignedTo', 'membership', 'interactions.recordedBy', 'interactions.resolvedBy',
            'followUps.assignedTo', 'followUps.doneBy', 'followUps.customer', 'groups.members.membership',
        ])->findOrFail($id);
    }

    private function load(Customer $customer): Customer
    {
        return $customer->fresh(['assignedTo', 'membership']);
    }

    private function rules(bool $updating = false): array
    {
        $presence = $updating ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'source' => [$presence, 'required', Rule::enum(CustomerSource::class)],
            'inquiry' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', Rule::enum(CustomerStatus::class)],
        ];
    }
}
