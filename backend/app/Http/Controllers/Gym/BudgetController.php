<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Support\Finance\BudgetActual;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** R-67: one month of budgets with its actual income and expenses. */
class BudgetController extends Controller
{
    public function index(Request $request, Gym $gym, BudgetActual $budgetActual): JsonResponse
    {
        $data = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        return response()->json(
            $budgetActual->forMonth($gym, $data['month'] ?? now()->format('Y-m')),
        );
    }

    public function update(Request $request, Gym $gym, BudgetActual $budgetActual): JsonResponse
    {
        $data = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
            'budgets' => ['required', 'array'],
            'budgets.*.kind' => ['required', Rule::in(['income', 'expense'])],
            'budgets.*.category' => ['required', 'string', 'max:40'],
            'budgets.*.amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $result = $budgetActual->upsert($gym, $data['month'], $data['budgets'], $request->user());

        return response()->json([
            'month' => $result['month'],
            'budgets' => $result['budgets'],
        ]);
    }
}
