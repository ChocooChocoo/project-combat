<?php

namespace App\Http\Controllers\Gym;

use App\Enums\AccessLevel;
use App\Enums\AthleteEntryType;
use App\Enums\Module;
use App\Http\Controllers\Concerns\PresentsMemberships;
use App\Http\Controllers\Controller;
use App\Models\AthleteEntry;
use App\Models\AthleteProfile;
use App\Models\Gym;
use App\Models\Membership;
use App\Models\SessionNote;
use App\Models\User;
use App\Support\AttendanceLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R-37 to R-41: the athlete record. Staff see it by permission; health details only with
 * health permission (R-40); an athlete sees only their own (R-41).
 */
class AthleteRecordController extends Controller
{
    use PresentsMemberships;

    public function show(Request $request, Gym $gym, User $user): JsonResponse
    {
        $viewer = $request->user();
        abort_unless(
            $viewer->canInGym($gym, Module::Members, AccessLevel::View) || $viewer->canInGym($gym, Module::Training, AccessLevel::View),
            403,
            'You do not have access to athlete records.',
        );

        return response()->json(['record' => $this->record($gym, $user, $viewer->canInGym($gym, Module::HealthRecords, AccessLevel::View))]);
    }

    /** R-37 to R-39: a permitted coach adds a promotion, assessment, competition or achievement. */
    public function storeEntry(Request $request, Gym $gym, User $user): JsonResponse
    {
        $this->profile($gym, $user);
        abort_if($request->user()->id === $user->id, 403, 'You cannot add entries to your own record.');

        $data = $request->validate([
            'type' => ['required', Rule::enum(AthleteEntryType::class)],
            'discipline' => ['nullable', 'required_if:type,promotion', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:150'],
            'division' => ['nullable', 'string', 'max:100'],
            'result' => ['nullable', 'required_if:type,competition,assessment', 'string', 'max:150'],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'discipline.required_if' => 'Choose the discipline for this rank.',
            'result.required_if' => 'Add the result.',
            'occurred_on.before_or_equal' => 'The date cannot be in the future.',
        ]);

        $entry = $gym->athleteEntries()->create([...$data, 'user_id' => $user->id, 'recorded_by' => $request->user()->id]);

        return response()->json(['entry' => $this->entryPayload($entry->load('recordedBy'))], 201);
    }

    /** R-41: the athlete's own record, health included. */
    public function mine(Request $request, Gym $gym): JsonResponse
    {
        return response()->json(['record' => $this->record($gym, $request->user(), true)]);
    }

    /** R-40: the athlete keeps their own emergency contacts and health declaration up to date. */
    public function updateMyHealth(Request $request, Gym $gym): JsonResponse
    {
        $profile = $this->profile($gym, $request->user());

        $rules = [
            'emergency_contacts' => ['required', 'array', 'min:1', 'max:'.config('membership.emergency_contacts_max')],
            'emergency_contacts.*' => ['array:name,relationship,phone'],
            'emergency_contacts.*.name' => ['required', 'string', 'max:150'],
            'emergency_contacts.*.relationship' => ['required', 'string', 'max:60'],
            'emergency_contacts.*.phone' => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s()-]{7,30}$/'],
            'health_answers' => ['required', 'array:'.implode(',', array_keys(config('membership.health_questions')))],
            'health_notes' => ['nullable', 'string', 'max:2000'],
            'medical_file' => [
                'nullable', 'file',
                'mimes:'.implode(',', config('membership.medical_file.mimes')),
                'max:'.config('membership.medical_file.max_kb'),
            ],
        ];
        foreach (array_keys(config('membership.health_questions')) as $key) {
            $rules["health_answers.{$key}"] = ['required', 'boolean'];
        }

        $data = $request->validate($rules, [
            'emergency_contacts.min' => 'Add at least one emergency contact.',
            'emergency_contacts.*.phone.regex' => 'Enter a valid phone number, for example 0917 123 4567.',
        ]);

        $profile->fill([
            'emergency_contacts' => array_values($data['emergency_contacts']),
            'health_answers' => array_map(fn ($answer) => filter_var($answer, FILTER_VALIDATE_BOOLEAN), $data['health_answers']),
            'health_notes' => $data['health_notes'] ?? null,
        ]);

        if ($request->hasFile('medical_file')) {
            $file = $request->file('medical_file');
            $profile->forceFill([
                'medical_file_path' => $file->store("medical-files/{$gym->id}", 'local'),
                'medical_file_name' => $file->getClientOriginalName(),
                'medical_file_mime' => $file->getMimeType(),
                'medical_file_size' => $file->getSize(),
            ]);
        }

        $profile->save();

        return response()->json(['record' => $this->record($gym, $request->user(), true)]);
    }

    /** R-40: medical files stay private: the athlete, or staff with health permission. */
    public function medicalFile(Request $request, Gym $gym, User $user): StreamedResponse
    {
        $viewer = $request->user();
        abort_unless($viewer->id === $user->id || $viewer->canInGym($gym, Module::HealthRecords, AccessLevel::View), 403);

        $profile = $this->profile($gym, $user);
        abort_unless($profile->medical_file_path, 404);

        return Storage::disk('local')->response($profile->medical_file_path, $profile->medical_file_name);
    }

    private function record(Gym $gym, User $user, bool $withHealth): array
    {
        $profile = $this->profile($gym, $user);
        $entries = $gym->athleteEntries()->where('user_id', $user->id)->with('recordedBy')
            ->orderByDesc('occurred_on')->orderByDesc('id')->get();

        // Current rank per discipline: the latest promotion in each (R-37).
        $ranks = $entries->where('type', AthleteEntryType::Promotion)
            ->groupBy('discipline')
            ->map(fn ($promotions, $discipline) => [
                'discipline' => $discipline,
                'rank' => $promotions->first()->title,
                'since' => $promotions->first()->occurred_on?->toDateString(),
            ])->values();

        $attendance = app(AttendanceLedger::class)->rows(['user_id' => $user->id]);
        $record = [
            'athlete' => [...$this->personPayload($user), 'phone' => $user->phone],
            'background' => $profile->background,
            'starting_rank' => $profile->current_rank,
            'training_history' => $profile->training_history,
            'ranks' => $ranks,
            'entries' => $entries->map(fn (AthleteEntry $entry) => $this->entryPayload($entry))->values(),
            'memberships' => $gym->memberships()->where('user_id', $user->id)->with(['gym', 'user', 'decidedBy'])
                ->orderByDesc('created_at')->get()
                ->map(fn (Membership $membership) => $this->membershipPayload($membership, false))->values(),
            // R-54, R-55: only this athlete's attendance and participant notes.
            'attendance' => $attendance,
            'attendance_counts' => collect(['present', 'late', 'absent', 'no_show'])->mapWithKeys(fn ($status) => [$status => $attendance->where('attendance', $status)->count()]),
            'progress_notes' => SessionNote::with(['session', 'author'])->where('user_id', $user->id)
                ->get()->sortByDesc(fn ($note) => $note->session->starts_at->timestamp)->values()->map(fn ($note) => $note->payload()),
        ];

        if ($withHealth) {
            $record['health'] = $this->healthPayload($profile);
        }

        return $record;
    }

    private function profile(Gym $gym, User $user): AthleteProfile
    {
        return $gym->athleteProfiles()->where('user_id', $user->id)->firstOrFail();
    }
}
