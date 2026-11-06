<?php

namespace App\Http\Controllers\Gym;

use App\Enums\PayrollStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsPayroll;
use App\Models\PayrollLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** R-62: an employee sees their own released payslips, outside the gym check. */
class PayslipController extends Controller
{
    use PresentsPayroll;

    public function index(Request $request): JsonResponse
    {
        $lines = PayrollLine::withoutGlobalScope('gym')
            ->with('run')
            ->where('user_id', $request->user()->id)
            ->whereHas('run', fn ($query) => $query->where('status', PayrollStatus::Released->value))
            ->get()
            ->sortByDesc(fn (PayrollLine $line) => $line->run?->released_at?->getTimestamp() ?? 0)
            ->values();

        return response()->json([
            'payslips' => $lines->map(fn (PayrollLine $line) => $this->payslipRow($line))->values()->all(),
        ]);
    }

    public function show(Request $request, int $line): JsonResponse
    {
        $line = PayrollLine::withoutGlobalScope('gym')
            ->with(['items', 'run'])
            ->where('user_id', $request->user()->id)
            ->findOrFail($line);

        abort_unless($line->run?->status === PayrollStatus::Released, 404, 'Payslip not found.');

        return response()->json([
            'payslip' => $this->payslipRow($line),
            'run' => [
                'id' => $line->run->id,
                'gym_id' => $line->run->gym_id,
                'starts_on' => $line->run->starts_on->toDateString(),
                'ends_on' => $line->run->ends_on->toDateString(),
                'status' => $line->run->status->value,
                'released_at' => $line->run->released_at?->toIso8601String(),
                'gross_total' => $line->run->gross_total,
                'net_total' => $line->run->net_total,
            ],
            'line' => $this->linePayload($line),
            'items' => $line->items->map(fn ($item) => $this->itemPayload($item))->values()->all(),
        ]);
    }
}
