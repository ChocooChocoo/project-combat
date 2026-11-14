<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Concerns\PresentsGymMedia;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-134, R-135: cover photo, gallery photos and policy PDFs of the gym. */
class GymMediaController extends Controller
{
    use PresentsGymMedia;

    public function cover(Request $request, Gym $gym): JsonResponse
    {
        $kb = config('offer.gym_photos.max_kb');

        $request->validate(['photo' => ['required', 'image', "max:{$kb}"]], [
            'photo.*' => 'Choose an image (JPG or PNG) under '.($kb / 1024).' MB.',
        ]);

        $old = $gym->cover_photo_path;
        $gym->forceFill(['cover_photo_path' => $request->file('photo')->store("gyms/{$gym->id}", 'public')])->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return response()->json(['media' => $this->gymMedia($gym)]);
    }

    public function storeFile(Request $request, Gym $gym): JsonResponse
    {
        $kind = $request->validate(['kind' => ['required', Rule::in(['photo', 'policy'])]])['kind'];
        $config = config($kind === 'photo' ? 'offer.gym_photos' : 'offer.policy_files');

        $request->validate([
            'file' => $kind === 'photo'
                ? ['required', 'image', "max:{$config['max_kb']}"]
                : ['required', 'file', 'mimes:pdf', "max:{$config['max_kb']}"],
        ], ['file.*' => $kind === 'photo'
            ? 'Choose an image (JPG or PNG) under '.($config['max_kb'] / 1024).' MB.'
            : 'Choose a PDF file under '.($config['max_kb'] / 1024).' MB.']);

        $existing = GymFile::where('kind', $kind);

        if ($existing->count() >= $config['max']) {
            $label = $kind === 'photo' ? 'photos' : 'policy files';
            throw ValidationException::withMessages([
                'file' => "You can add at most {$config['max']} {$label}. Delete one first to add another.",
            ]);
        }

        $file = $request->file('file');

        GymFile::create([
            'kind' => $kind,
            'path' => $file->store("gyms/{$gym->id}", 'public'),
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'sort' => ((int) $existing->max('sort')) + 1,
        ]);

        return response()->json(['media' => $this->gymMedia($gym)]);
    }

    public function destroyFile(Gym $gym, int $file): JsonResponse
    {
        $row = GymFile::findOrFail($file);

        Storage::disk('public')->delete($row->path);
        $row->delete();

        return response()->json(['media' => $this->gymMedia($gym)]);
    }
}
