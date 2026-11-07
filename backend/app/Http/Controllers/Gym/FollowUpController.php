<?php

namespace App\Http\Controllers\Gym;

use App\Enums\CustomerFollowUpStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsCustomers;
use App\Models\CustomerFollowUp;
use App\Models\Gym;
use App\Notifications\FollowUpAssigned;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-69: schedule, reassign, complete and cancel customer follow-ups. */
class FollowUpController extends Controller
{
    use PresentsCustomers;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(CustomerFollowUpStatus::class)],
            'assigned_to' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = $gym->customerFollowUps()
            ->with(['customer', 'assignedTo', 'doneBy'])
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->orderByRaw("case when status = 'open' then 0 when status = 'done' then 1 else 2 end")
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json([
            'followUps' => collect($page->items())->map(fn (CustomerFollowUp $row) => $this->followUpPayload($row))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request, Gym $gym, int $customer): JsonResponse
    {
        $model = $gym->customers()->findOrFail($customer);
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'min:1'],
            'due_at' => ['required', 'date'],
            'note' => ['required', 'string', 'max:5000'],
        ]);

        // The customer's default owner keeps the follow-up list useful when the form omits it.
        $assignedTo = $data['assigned_to'] ?? $model->assigned_to ?? $request->user()->id;
        $this->assertStaff($gym, $assignedTo);

        $followUp = $model->followUps()->create([
            'assigned_to' => $assignedTo,
            'due_at' => Carbon::parse($data['due_at']),
            'note' => $data['note'],
            'status' => CustomerFollowUpStatus::Open->value,
        ])->load(['customer', 'assignedTo', 'doneBy']);

        $followUp->assignedTo->notify(new FollowUpAssigned($gym, $followUp, $model));

        return response()->json(['followUp' => $this->followUpPayload($followUp)], 201);
    }

    public function update(Request $request, Gym $gym, int $followUp): JsonResponse
    {
        $model = $gym->customerFollowUps()->findOrFail($followUp);

        $data = $request->validate([
            'assigned_to' => ['sometimes', 'required', 'integer', 'min:1'],
            'due_at' => ['sometimes', 'required', 'date'],
            'note' => ['sometimes', 'required', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', Rule::in([CustomerFollowUpStatus::Done->value, CustomerFollowUpStatus::Cancelled->value])],
            'outcome' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $status = $data['status'] ?? null;

        if ($status === CustomerFollowUpStatus::Done->value && trim((string) ($data['outcome'] ?? '')) === '') {
            throw ValidationException::withMessages(['outcome' => 'Record the outcome before closing this follow-up.']);
        }

        abort_unless($model->isOpen(), 409, 'Only an open follow-up can be changed.');

        if (array_key_exists('assigned_to', $data)) {
            $this->assertStaff($gym, $data['assigned_to']);
        }

        $previousAssignee = $model->assigned_to;

        $model = DB::transaction(function () use ($request, $gym, $model, $data, $status) {
            $row = $gym->customerFollowUps()->lockForUpdate()->findOrFail($model->id);
            abort_unless($row->isOpen(), 409, 'Only an open follow-up can be changed.');

            $changes = Arr::only($data, ['assigned_to', 'due_at', 'note']);

            if ($status !== null) {
                $changes['status'] = $status;

                if ($status === CustomerFollowUpStatus::Done->value) {
                    $changes['done_at'] = now();
                    $changes['done_by'] = $request->user()->id;
                    $changes['outcome'] = $data['outcome'];
                } elseif (array_key_exists('outcome', $data)) {
                    // A cancellation note is kept in the same field, so the reason survives.
                    $changes['outcome'] = $data['outcome'];
                }
            }

            $row->fill($changes);
            if ($row->isDirty(['assigned_to', 'due_at'])) {
                $row->reminded_at = null;
            }
            $row->save();

            return $row;
        });

        $model->load(['customer', 'assignedTo', 'doneBy']);

        if ($model->assigned_to !== $previousAssignee) {
            $model->assignedTo->notify(new FollowUpAssigned($gym, $model, $model->customer));
        }

        return response()->json(['followUp' => $this->followUpPayload($model)]);
    }
}
