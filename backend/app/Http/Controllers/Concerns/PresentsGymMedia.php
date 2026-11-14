<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Gym;
use App\Models\GymFile;
use Illuminate\Support\Facades\Storage;

/** R-134, R-135: the cover photo, gallery and policy files of a gym, also for public pages. */
trait PresentsGymMedia
{
    protected function gymMedia(Gym $gym): array
    {
        $files = GymFile::withoutGlobalScope('gym')->where('gym_id', $gym->id)->orderBy('sort')->orderBy('id')->get();
        $url = fn (GymFile $file) => Storage::disk('public')->url($file->path);

        return [
            'cover_photo_url' => $gym->cover_photo_path ? Storage::disk('public')->url($gym->cover_photo_path) : null,
            'photos' => $files->where('kind', 'photo')
                ->map(fn (GymFile $file) => ['id' => $file->id, 'url' => $url($file)])->values()->all(),
            'policy_files' => $files->where('kind', 'policy')
                ->map(fn (GymFile $file) => [
                    'id' => $file->id, 'name' => $file->original_name, 'size' => $file->size, 'url' => $url($file),
                ])->values()->all(),
        ];
    }
}
