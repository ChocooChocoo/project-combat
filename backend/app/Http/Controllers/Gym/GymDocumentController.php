<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\GymDocument;
use App\Support\Verification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * R-13 and R-15: verification files. They live on the private "local" disk and
 * are only ever streamed through show() after the policy check, so a copied
 * link opens nothing for anyone else. Later private files (member IDs,
 * medical files, leave attachments) follow the same pattern.
 */
class GymDocumentController extends Controller
{
    public function store(Request $request, Gym $gym): JsonResponse
    {
        Gate::authorize('update', $gym);

        if (! $gym->tier) {
            throw ValidationException::withMessages(['type' => 'Choose a tier before uploading documents.']);
        }

        $request->validate(['type' => ['required', Rule::in(Verification::typesFor($gym->tier))]], [
            'type.in' => 'This document is not part of the chosen tier.',
        ]);
        $type = $request->input('type');
        $request->validate(['file' => Verification::fileRules($type)], [
            'file.mimes' => 'This file type is not accepted. Use: :values.',
            'file.max' => 'This file is too big. The largest accepted size is '.round(Verification::maxKb($type) / 1024).' MB.',
            // PHP drops files above its own upload limit before Laravel sees them.
            'file.uploaded' => 'The server could not accept this file, most likely because it is bigger than '.ini_get('upload_max_filesize').'B. Try a smaller file.',
        ]);

        $max = config("verification.documents.{$type}.max", 1);
        if ($gym->documents()->where('type', $type)->count() >= $max) {
            throw ValidationException::withMessages(['file' => $max === 1
                ? 'This document is already uploaded. Delete it first to replace it.'
                : "You can upload at most {$max} of these."]);
        }

        // (R-133) ponytail: flags come from the browser and can be faked; they only guide the admin.
        $request->validate([
            'expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'ocr_flags' => ['nullable', 'array', 'max:3'],
            'ocr_flags.*' => [Rule::in(['owner_name', 'gym_name', 'no_text'])],
        ], ['expires_on.after_or_equal' => 'This document has expired. Upload a current one.']);

        $file = $request->file('file');
        $document = $gym->documents()->create([
            'type' => $type,
            'path' => $file->store("gym-applications/{$gym->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'expires_on' => config("verification.documents.{$type}.expiry") ? $request->input('expires_on') : null,
            'ocr_flags' => $request->input('ocr_flags') ?: null,
        ]);

        return response()->json(['document' => $document], 201);
    }

    /** (R-133) The owner corrects the expiry date, before sending only (policy). */
    public function update(Request $request, Gym $gym, GymDocument $document): JsonResponse
    {
        Gate::authorize('update', $gym);

        if (! config("verification.documents.{$document->type}.expiry")) {
            throw ValidationException::withMessages(['expires_on' => 'This document has no expiry date.']);
        }

        $data = $request->validate(['expires_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today']], [
            'expires_on.after_or_equal' => 'This document has expired. Upload a current one.',
        ]);
        $document->update(['expires_on' => $data['expires_on'] ?? null]);

        return response()->json(['document' => $document]);
    }

    public function show(Request $request, Gym $gym, GymDocument $document): StreamedResponse
    {
        Gate::authorize('view', $gym);

        // (R-132) Thumbnail previews send ?preview=1 and do not count as opened.
        if ($request->user()->is_platform_admin && ! $request->boolean('preview') && $document->admin_viewed_at === null) {
            $document->forceFill(['admin_viewed_at' => now()])->save();
        }

        return Storage::disk('local')->response($document->path, $document->original_name);
    }

    /** Only the applicant's own files, and only before sending (policy). */
    public function destroy(Gym $gym, GymDocument $document): JsonResponse
    {
        Gate::authorize('update', $gym);

        Storage::disk('local')->delete($document->path);
        $document->delete();

        return response()->json(['message' => 'File deleted.']);
    }
}
