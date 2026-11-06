<?php

namespace App\Http\Controllers\Gym;

use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsPayroll;
use App\Models\Gym;
use App\Models\PayrollRun;
use App\Support\Payroll\Workflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** R-59, R-61: payroll runs, computation and the approval chain. */
class PayrollRunController extends Controller
{
    use PresentsPayroll;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::enum(PayrollStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $page = $gym->payrollRuns()
            ->with(['preparedBy', 'releasedBy'])
            ->withCount('lines')
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'runs' => collect($page->items())
                ->map(fn (PayrollRun $run) => $this->runSummary($gym, $run))
                ->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
        ]);

        $from = Carbon::parse($data['starts_on'])->startOfDay();
        $to = Carbon::parse($data['ends_on'])->startOfDay();

        $this->assertMatchesFrequency($gym, $from, $to);

        $overlap = PayrollRun::withoutGlobalScope('gym')
            ->where('gym_id', $gym->id)
            ->whereDate('starts_on', '<=', $to)
            ->whereDate('ends_on', '>=', $from)
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'starts_on' => 'This period overlaps another payroll run for this gym.',
            ]);
        }

        $run = $gym->payrollRuns()->create([
            'starts_on' => $from->toDateString(),
            'ends_on' => $to->toDateString(),
            'status' => PayrollStatus::Draft->value,
            'round' => 1,
            'current_step' => 0,
            'prepared_by' => $request->user()->id,
        ]);

        $run->load(['preparedBy', 'releasedBy']);

        return response()->json(['run' => $this->runSummary($gym, $run)], 201);
    }

    public function show(Gym $gym, int $run): JsonResponse
    {
        $run = $gym->payrollRuns()
            ->with(['lines.items', 'lines.user', 'approvals.user', 'preparedBy', 'releasedBy'])
            ->findOrFail($run);

        $run->lines->each(fn ($line) => $line->setRelation('run', $run));

        $payslips = $run->isReleased()
            ? $run->lines->map(fn ($line) => $this->payslipRow($line))->values()->all()
            : [];

        return response()->json([
            'run' => $this->runSummary($gym, $run),
            'lines' => $run->lines->map(fn ($line) => $this->linePayload($line))->values()->all(),
            'approvals' => $run->approvals->map(fn ($approval) => $this->approvalPayload($approval))->values()->all(),
            'warnings' => $this->runWarnings($run),
            'payslips' => $payslips,
        ]);
    }

    public function compute(Request $request, Gym $gym, int $run, Workflow $workflow): JsonResponse
    {
        $run = $this->findRun($gym, $run);
        $run = $workflow->compute($gym, $run, $request->user());

        return response()->json(['run' => $this->runSummary($gym, $run)]);
    }

    public function submit(Request $request, Gym $gym, int $run, Workflow $workflow): JsonResponse
    {
        $run = $this->findRun($gym, $run);
        $run = $workflow->submit($gym, $run, $request->user());

        return response()->json(['run' => $this->runSummary($gym, $run)]);
    }

    public function decide(Request $request, Gym $gym, int $run, Workflow $workflow): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $run = $this->findRun($gym, $run);
        $run = $workflow->decide($gym, $run, $request->user(), $data);

        return response()->json(['run' => $this->runSummary($gym, $run)]);
    }

    public function destroy(Gym $gym, int $run): Response
    {
        $run = $gym->payrollRuns()->findOrFail($run);

        abort_unless($run->isDraft(), 409, 'Only a draft payroll run can be deleted.');
        $run->delete();

        return response()->noContent();
    }

    private function findRun(Gym $gym, int $id): PayrollRun
    {
        return $gym->payrollRuns()->with(['preparedBy', 'releasedBy'])->findOrFail($id);
    }

    private function assertMatchesFrequency(Gym $gym, Carbon $from, Carbon $to): void
    {
        $frequency = $gym->payroll_frequency ?: 'semi_monthly';

        $monthly = $from->day === 1 && $to->isSameDay($from->copy()->endOfMonth());
        $firstHalf = $from->day === 1 && $to->day === 15 && $from->isSameMonth($to);
        $secondHalf = $from->day === 16 && $to->isSameDay($from->copy()->endOfMonth());

        $valid = $frequency === 'monthly' ? $monthly : ($firstHalf || $secondHalf);

        if (! $valid) {
            throw ValidationException::withMessages([
                'starts_on' => $frequency === 'monthly'
                    ? 'A monthly payroll run must cover the first to the last day of one month.'
                    : 'A semi-monthly payroll run must cover the 1st to the 15th, or the 16th to the end of the month.',
            ]);
        }
    }
}
