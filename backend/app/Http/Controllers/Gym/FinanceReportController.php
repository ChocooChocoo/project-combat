<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Support\Finance\Reports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** R-68: cash flow, accrual income against expenses, payroll and pay-outs. */
class FinanceReportController extends Controller
{
    public function cashFlow(Request $request, Gym $gym, Reports $reports): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json($reports->cashFlow($gym, $from, $to));
    }

    public function incomeExpense(Request $request, Gym $gym, Reports $reports): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json($reports->incomeAgainstExpenses($gym, $from, $to));
    }

    public function payroll(Request $request, Gym $gym, Reports $reports): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json($reports->payrollSummary($gym, $from, $to));
    }

    public function payouts(Request $request, Gym $gym, Reports $reports): JsonResponse
    {
        [$from, $to] = $this->range($request);

        return response()->json($reports->payouts($gym, $from, $to));
    }

    /** @return array{0: string, 1: string} */
    private function range(Request $request): array
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        return [$data['from'], $data['to']];
    }
}
