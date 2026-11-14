<?php

namespace App\Http\Controllers\Gym;

use App\Enums\GymStatus;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\User;
use App\Notifications\GymNotice;
use App\Rules\ClosingTime;
use App\Support\Verification;
use App\Support\CaviteLocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * R-11 to R-14: a confirmed user applies to run a gym, picks a tier, and follows
 * the application. The person becomes an owner only on approval (R-17).
 */
class GymApplicationController extends Controller
{
    /** Plain-language messages for the fields whose default wording shows raw field names. */
    private const MESSAGES = [
        'operating_hours.*.open.required' => 'Add an opening time.',
        'operating_hours.*.close.required' => 'Add a closing time.',
        'operating_hours.*.open.date_format' => 'Use a time such as 08:00.',
        'operating_hours.*.close.date_format' => 'Use a time such as 20:00.',
        'operating_hours.array' => 'Operating hours can only be set for Monday to Sunday.',
        'disciplines.*.in' => 'Choose combat sports from the list.',
    ];

    /** The tier lists, file rules and choices the application screens show. */
    public function requirements(): JsonResponse
    {
        return response()->json(collect(config('verification'))->only([
            'documents', 'kinds', 'tiers', 'checklist', 'upload_rules', 'disciplines',
        ]));
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'applications' => Gym::where('applicant_id', $request->user()->id)
                ->latest()
                ->get(['id', 'name', 'status', 'tier', 'approved_tier', 'submitted_at', 'created_at']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $gym = new Gym(CaviteLocations::apply($request->validate($this->rules(), self::MESSAGES)));
        $gym->applicant_id = $request->user()->id;
        $gym->status = GymStatus::Draft;
        $gym->save();

        return response()->json(['application' => $gym->fresh()->load('documents')], 201);
    }

    public function show(Gym $gym): JsonResponse
    {
        Gate::authorize('view', $gym);

        return response()->json(['application' => $gym->load('documents')]);
    }

    /** Changing tier never deletes files already sent (R-12); they are only ignored. */
    public function update(Request $request, Gym $gym): JsonResponse
    {
        Gate::authorize('update', $gym);
        $gym->update(CaviteLocations::apply($request->validate($this->rules(updating: true), self::MESSAGES)));

        return response()->json(['application' => $gym->load('documents')]);
    }

    /** R-13: nothing incomplete can be sent. */
    public function submit(Gym $gym): JsonResponse
    {
        Gate::authorize('update', $gym);

        $missing = [];
        foreach (Gym::REQUIRED_PROFILE as $field => $label) {
            // Latitude and longitude share one label, so the map location is reported once.
            if (blank($gym->{$field}) && ! in_array("Add the {$label}.", $missing, true)) {
                $missing[$field] = "Add the {$label}.";
            }
        }

        if ($gym->tier) {
            $counts = $gym->documents()->toBase()->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type');

            foreach (Verification::requirements($gym->tier) as $requirement) {
                $have = collect($requirement['types'])->sum(fn ($type) => $counts[$type] ?? 0);

                if ($have < $requirement['min']) {
                    $labels = collect($requirement['types'])->map(fn ($t) => config("verification.documents.{$t}.label"))->join(' or ');
                    $missing['documents.'.implode('_or_', $requirement['types'])] = $requirement['min'] > 1
                        ? "Upload at least {$requirement['min']} {$labels} ({$have} so far)."
                        : "Upload the {$labels}.";
                }
            }
        }

        if ($gym->tier) {
            // (R-133) Required expiry dates must be present and not past.
            $types = Verification::typesFor($gym->tier);
            foreach ($gym->documents()->whereIn('type', $types)->get() as $doc) {
                if (config("verification.documents.{$doc->type}.expiry") !== 'required') {
                    continue;
                }
                $label = config("verification.documents.{$doc->type}.label");
                if (! $doc->expires_on) {
                    $missing["expiry.{$doc->id}"] = "Add the expiry date of the {$label}.";
                } elseif ($doc->expires_on->lt(today())) {
                    $missing["expiry.{$doc->id}"] = "The {$label} has expired. Upload a current one.";
                }
            }
        }

        if ($missing) {
            throw ValidationException::withMessages($missing);
        }

        $wasReturned = $gym->status === GymStatus::Returned;

        $gym->forceFill([
            'status' => GymStatus::Submitted,
            'submitted_at' => now(),
            'return_items' => null,
            'decision_reason' => null,
        ])->save();

        // (R-131) Every platform admin hears about a new or re-sent application.
        $gym->loadMissing('applicant');
        User::where('is_platform_admin', true)->get()->each(fn (User $admin) => $admin->notify(new GymNotice(
            $wasReturned ? 'Gym application sent again' : 'New gym application',
            "{$gym->name} from {$gym->applicant->first_name} {$gym->applicant->last_name} is waiting for review.",
            null, "/admin/applications/{$gym->id}",
        )));

        return response()->json(['application' => $gym->load('documents')]);
    }

    /** Drafts may be saved half filled in; submit() checks completeness. */
    private function rules(bool $updating = false): array
    {
        $days = 'mon,tue,wed,thu,fri,sat,sun';

        return [
            'name' => [...($updating ? ['sometimes'] : []), 'required', 'string', 'max:120'],
            'tier' => ['nullable', 'integer', Rule::in(array_keys(config('verification.tiers')))],
            'address_line' => ['nullable', 'string', 'max:255'],
            'barangay' => ['nullable', 'string', 'max:120', CaviteLocations::barangayRule()],
            'city' => ['nullable', 'string', Rule::in(CaviteLocations::municipalityNames())],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'operating_hours' => ['nullable', "array:{$days}"],
            'operating_hours.*' => ['array:open,close'],
            'operating_hours.*.open' => ['required', 'date_format:H:i'],
            'operating_hours.*.close' => ['required', 'date_format:H:i', new ClosingTime],
            'disciplines' => ['nullable', 'array'],
            'disciplines.*' => ['distinct', Rule::in(array_keys(config('verification.disciplines')))],
            'services' => ['nullable', 'array', 'max:30'],
            'services.*' => ['string', 'max:100'],
            'facilities' => ['nullable', 'array', 'max:30'],
            'facilities.*' => ['string', 'max:100'],
        ];
    }
}
