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

        $file = $request->file('file');
        $document = $gym->documents()->create([
            'type' => $type,
            'path' => $file->store("gym-applications/{$gym->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json(['document' => $document], 201);
    }

    public function show(Gym $gym, GymDocument $document): StreamedResponse
    {
        Gate::authorize('view', $gym);

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
