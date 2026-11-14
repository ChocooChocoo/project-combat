<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\JobPosting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** R-138: the owner's list of job openings. */
class JobPostingController extends Controller
{
    public function index(): JsonResponse
    {
        $postings = JobPosting::with('role:id,name')
            ->withCount(['applications as pending_count' => fn ($q) => $q->where('status', 'pending')])
            ->orderByRaw("case when status = 'open' then 0 else 1 end")->orderByDesc('id')->get();

        return response()->json(['postings' => $postings->map(fn (JobPosting $row) => $this->payload($row))->values()]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $posting = new JobPosting($this->validated($request, $gym));
        $posting->created_by = $request->user()->id;
        $posting->save();

        return response()->json(['posting' => $this->payload($posting->load('role:id,name'))], 201);
    }

    public function update(Request $request, Gym $gym, int $posting): JsonResponse
    {
        $row = JobPosting::findOrFail($posting);
        $row->update($this->validated($request, $gym));

        return response()->json(['posting' => $this->payload($row->load('role:id,name'))]);
    }

    private function validated(Request $request, Gym $gym): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'gym_role_id' => ['nullable', 'integer', Rule::exists('gym_roles', 'id')->where('gym_id', $gym->id)->where('status', 'active')],
            'description' => ['required', 'string', 'max:5000'],
            'requirements' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['open', 'closed'])],
        ]);
    }

    private function payload(JobPosting $row): array
    {
        return [
            'id' => $row->id,
            'title' => $row->title,
            'gym_role_id' => $row->gym_role_id,
            'role_name' => $row->role?->name,
            'description' => $row->description,
            'requirements' => $row->requirements,
            'status' => $row->status,
            'pending_count' => (int) ($row->pending_count ?? 0),
            'created_at' => $row->created_at?->toIso8601String(),
        ];
    }
}
