<?php

namespace App\Http\Controllers\Gym\Concerns;

use App\Enums\GymPersonType;
use App\Models\Customer;
use App\Models\CustomerFollowUp;
use App\Models\CustomerGroup;
use App\Models\CustomerInteraction;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Shared response shapes and staff checks for the CRM screens (R-69 to R-71). */
trait PresentsCustomers
{
    protected function customerPayload(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'source' => $customer->source?->value,
            'inquiry' => $customer->inquiry,
            'user_id' => $customer->user_id,
            'membership_id' => $customer->membership_id,
            'linked_at' => $customer->linked_at?->toIso8601String(),
            'status' => $customer->status?->value,
            'stage' => $customer->stage,
            'assigned_to' => $customer->assigned_to,
            'assigned_to_name' => $this->personName($customer->assignedTo),
            'created_at' => $customer->created_at?->toIso8601String(),
            'updated_at' => $customer->updated_at?->toIso8601String(),
        ];
    }

    protected function interactionPayload(CustomerInteraction $interaction): array
    {
        return [
            'id' => $interaction->id,
            'customer_id' => $interaction->customer_id,
            'kind' => $interaction->kind->value,
            'channel' => $interaction->channel->value,
            'body' => $interaction->body,
            'occurred_at' => $interaction->occurred_at?->toIso8601String(),
            'recorded_by' => $interaction->recorded_by,
            'recorded_by_name' => $this->personName($interaction->recordedBy),
            'resolved_at' => $interaction->resolved_at?->toIso8601String(),
            'resolved_by' => $interaction->resolved_by,
            'resolved_by_name' => $this->personName($interaction->resolvedBy),
            'resolution' => $interaction->resolution,
        ];
    }

    protected function followUpPayload(CustomerFollowUp $followUp): array
    {
        return [
            'id' => $followUp->id,
            'customer_id' => $followUp->customer_id,
            'customer_name' => $followUp->customer?->name,
            'assigned_to' => $followUp->assigned_to,
            'assigned_to_name' => $this->personName($followUp->assignedTo),
            'due_at' => $followUp->due_at?->toIso8601String(),
            'note' => $followUp->note,
            'status' => $followUp->status->value,
            'done_at' => $followUp->done_at?->toIso8601String(),
            'done_by' => $followUp->done_by,
            'done_by_name' => $this->personName($followUp->doneBy),
            'outcome' => $followUp->outcome,
            'created_at' => $followUp->created_at?->toIso8601String(),
            'updated_at' => $followUp->updated_at?->toIso8601String(),
        ];
    }

    protected function groupPayload(CustomerGroup $group): array
    {
        $members = $group->relationLoaded('members') ? $group->members : $group->members()->get();

        return [
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'members_count' => $members->count(),
            'customer_ids' => $members->pluck('id')->values()->all(),
            'members' => $members->sortBy('name')->values()->map(fn (Customer $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'user_id' => $member->user_id,
                'stage' => $member->stage,
            ])->all(),
            'created_at' => $group->created_at?->toIso8601String(),
            'updated_at' => $group->updated_at?->toIso8601String(),
        ];
    }

    /** Only the owner and employees may carry a CRM assignment (R-69). */
    protected function assertStaff(Gym $gym, ?int $userId, string $field = 'assigned_to'): void
    {
        if ($userId === null) {
            return;
        }

        $isStaff = $gym->people()
            ->whereKey($userId)
            ->wherePivotIn('role', [GymPersonType::Owner->value, GymPersonType::Employee->value])
            ->exists();

        if (! $isStaff) {
            throw ValidationException::withMessages([
                $field => 'Choose the owner or an employee of this gym.',
            ]);
        }
    }

    /** A linked customer account must already belong to this gym. */
    protected function assertGymPerson(Gym $gym, ?int $userId, string $field = 'user_id'): void
    {
        if ($userId === null) {
            return;
        }

        if (! $gym->people()->whereKey($userId)->exists()) {
            throw ValidationException::withMessages([
                $field => 'Choose a person who belongs to this gym.',
            ]);
        }
    }

    private function personName(?User $user): ?string
    {
        return $user ? trim("{$user->first_name} {$user->last_name}") : null;
    }
}
