<?php

namespace App\Http\Controllers\Gym;

use App\Enums\AccessLevel;
use App\Enums\Module;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymService;
use App\Models\GymServicePhoto;
use App\Models\MembershipPlanAddon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-28: services, their photos, and publishing. */
class ServiceController extends Controller
{
    private const MESSAGES = [
        'photo.required' => 'Choose a photo.',
        'photo.image' => 'Choose an image file.',
        'photo.max' => 'This photo is too big. The largest accepted size is 4 MB.',
    ];

    public function index(Request $request, Gym $gym): JsonResponse
    {
        // R-29: a plan manager needs the service list to choose add-ons, even
        // without access to the gym setup screens.
        abort_unless(
            $request->user()->canInGym($gym, Module::GymSetup, AccessLevel::View)
            || $request->user()->canInGym($gym, Module::Members, AccessLevel::View),
            403,
            'You do not have access to this part of the gym.',
        );

        return response()->json([
            'services' => $gym->gymServices()
                ->orderBy('name')
                ->get()
                ->map(fn (GymService $service) => $this->payload($service))
                ->all(),
        ]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate($this->rules(), self::MESSAGES);

        if ($data['price_included'] && ($data['price'] ?? null) !== null) {
            throw ValidationException::withMessages(['price' => 'Leave the price empty when it is included in the membership.']);
        }

        if (! $data['price_included'] && ($data['price'] ?? null) === null) {
            throw ValidationException::withMessages(['price' => 'Add a price, or mark the service as included in the membership.']);
        }

        $service = $gym->gymServices()->create($data);

        return response()->json(['service' => $this->payload($service)], 201);
    }

    public function update(Request $request, Gym $gym, int $service): JsonResponse
    {
        $model = $gym->gymServices()->findOrFail($service);
        $data = $request->validate($this->rules(updating: true), self::MESSAGES);

        $model->fill($data);

        if ($model->price_included) {
            if (array_key_exists('price', $data) && $data['price'] !== null) {
                throw ValidationException::withMessages(['price' => 'Leave the price empty when it is included in the membership.']);
            }

            $model->price = null;
        } elseif ($model->price === null) {
            throw ValidationException::withMessages(['price' => 'Add a price, or mark the service as included in the membership.']);
        }

        // A published service stays publishable: the edited result must still pass the same rules.
        if ($model->published_at) {
            $errors = $this->publishErrors($model);

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        }

        $model->save();

        return response()->json(['service' => $this->payload($model)]);
    }

    public function destroy(Gym $gym, int $service): JsonResponse
    {
        $model = $gym->gymServices()->findOrFail($service);

        if (MembershipPlanAddon::where('gym_service_id', $model->id)->exists()) {
            throw ValidationException::withMessages([
                'service' => 'This service is used by a membership plan. Remove it from the plan first.',
            ]);
        }

        if ($model->cover_photo_path) {
            Storage::disk('public')->delete($model->cover_photo_path);
        }

        foreach ($model->photos()->get() as $photo) {
            Storage::disk('public')->delete($photo->path);
        }

        $model->photos()->delete();
        $model->delete();

        return response()->json(['message' => 'Service deleted.']);
    }

    public function cover(Request $request, Gym $gym, int $service): JsonResponse
    {
        $model = $gym->gymServices()->findOrFail($service);

        $request->validate(['photo' => ['required', 'image', 'max:4096']], self::MESSAGES);

        $old = $model->cover_photo_path;
        $model->cover_photo_path = $request->file('photo')->store("gym-services/{$gym->id}", 'public');
        $model->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return response()->json(['service' => $this->payload($model)]);
    }

    public function storePhoto(Request $request, Gym $gym, int $service): JsonResponse
    {
        $model = $gym->gymServices()->findOrFail($service);

        $request->validate(['photo' => ['required', 'image', 'max:4096']], self::MESSAGES);

        $max = config('offer.service_photos.max', 5);

        if ($model->photos()->count() >= $max) {
            throw ValidationException::withMessages([
                'photo' => "You can add at most {$max} gallery photos. Delete one first to add another.",
            ]);
        }

        $model->photos()->create([
            'path' => $request->file('photo')->store("gym-services/{$gym->id}", 'public'),
            'sort' => ((int) $model->photos()->max('sort')) + 1,
        ]);

        return response()->json(['service' => $this->payload($model)]);
    }

    public function destroyPhoto(Gym $gym, int $service, int $photo): JsonResponse
    {
        $model = $gym->gymServices()->findOrFail($service);
        $row = $model->photos()->findOrFail($photo);

        Storage::disk('public')->delete($row->path);
        $row->delete();

        return response()->json(['service' => $this->payload($model)]);
    }

    public function publish(Gym $gym, int $service): JsonResponse
    {
        $model = $gym->gymServices()->findOrFail($service);
        $errors = $this->publishErrors($model);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $model->forceFill(['published_at' => now()])->save();

        return response()->json(['service' => $this->payload($model)]);
    }

    public function unpublish(Gym $gym, int $service): JsonResponse
    {
        $model = $gym->gymServices()->findOrFail($service);
        $model->forceFill(['published_at' => null])->save();

        return response()->json(['service' => $this->payload($model)]);
    }

    private function payload(GymService $service): array
    {
        $photos = $service->photos()->orderBy('sort')->get();

        return [
            'id' => $service->id,
            'name' => $service->name,
            'discipline' => $service->discipline,
            'activity_type' => $service->activity_type,
            'overview' => $service->overview,
            'level' => $service->level,
            'duration_minutes' => $service->duration_minutes,
            'frequency' => $service->frequency,
            'included' => $service->included ?? [],
            'bring' => $service->bring ?? [],
            'price' => $service->price,
            'price_included' => $service->price_included,
            'cover_photo_path' => $service->cover_photo_path,
            'cover_photo_url' => $service->cover_photo_path
                ? Storage::disk('public')->url($service->cover_photo_path)
                : null,
            'photos' => $photos->map(fn (GymServicePhoto $photo) => [
                'id' => $photo->id,
                'sort' => $photo->sort,
                'url' => Storage::disk('public')->url($photo->path),
            ])->all(),
            'photo_urls' => $photos->map(
                fn (GymServicePhoto $photo) => Storage::disk('public')->url($photo->path),
            )->all(),
            'published_at' => $service->published_at?->toIso8601String(),
            'created_at' => $service->created_at?->toIso8601String(),
            'updated_at' => $service->updated_at?->toIso8601String(),
        ];
    }

    private function publishErrors(GymService $service): array
    {
        $errors = [];

        if (blank($service->name)) {
            $errors['name'] = 'Add the service name.';
        }

        if (! array_key_exists((string) $service->discipline, config('verification.disciplines'))) {
            $errors['discipline'] = 'Choose a combat sport from the list.';
        }

        if (! array_key_exists((string) $service->activity_type, config('offer.activity_types'))) {
            $errors['activity_type'] = 'Choose an activity type from the list.';
        }

        $overview = trim((string) $service->overview);

        if (mb_strlen($overview) < 40) {
            $errors['overview'] = 'Write an overview of at least 40 characters, in 2 to 4 sentences.';
        } else {
            $sentences = preg_match_all('/[.!?]/', $overview);

            if ($sentences < 2 || $sentences > 4) {
                $errors['overview'] = 'Write the overview in 2 to 4 sentences.';
            }
        }

        if (! array_key_exists((string) $service->level, config('offer.levels'))) {
            $errors['level'] = 'Choose a level from the list.';
        }

        if (! $service->duration_minutes || $service->duration_minutes < 1 || $service->duration_minutes > 600) {
            $errors['duration_minutes'] = 'Set a duration between 1 and 600 minutes.';
        }

        if (blank($service->frequency)) {
            $errors['frequency'] = 'Say how often the service runs.';
        }

        if (blank($service->included)) {
            $errors['included'] = 'List at least one thing the service includes.';
        }

        if (blank($service->bring)) {
            $errors['bring'] = 'List at least one thing the athlete should bring.';
        }

        if (blank($service->cover_photo_path)) {
            $errors['cover_photo_path'] = 'Upload a cover photo.';
        }

        if ($service->price_included && $service->price !== null) {
            $errors['price'] = 'Leave the price empty when it is included in the membership.';
        }

        if (! $service->price_included && $service->price === null) {
            $errors['price'] = 'Add a price, or mark the service as included in the membership.';
        }

        return $errors;
    }

    private function rules(bool $updating = false): array
    {
        $sometimes = $updating ? ['sometimes'] : [];

        return [
            'name' => [...$sometimes, 'required', 'string', 'max:120'],
            'discipline' => [...$sometimes, 'required', Rule::in(array_keys(config('verification.disciplines')))],
            'activity_type' => [...$sometimes, 'required', Rule::in(array_keys(config('offer.activity_types')))],
            'overview' => [...$sometimes, 'required', 'string', 'max:5000'],
            'level' => [...$sometimes, 'required', Rule::in(array_keys(config('offer.levels')))],
            'duration_minutes' => [...$sometimes, 'required', 'integer', 'min:1', 'max:600'],
            'frequency' => [...$sometimes, 'required', 'string', 'max:100'],
            'included' => [...$sometimes, 'required', 'array'],
            'included.*' => ['string', 'max:255'],
            'bring' => [...$sometimes, 'required', 'array'],
            'bring.*' => ['string', 'max:255'],
            'price_included' => [...$sometimes, 'required', 'boolean'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
