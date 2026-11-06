<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Concerns\PresentsFinance;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Support\IncomeQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** R-63: recorded income by type and date range. Payments remains the ledger. */
class IncomeController extends Controller
{
    use PresentsFinance;

    public function index(Request $request, Gym $gym, IncomeQuery $income): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'type' => ['nullable', Rule::in([...array_keys(config('finance.income_types')), 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;
        $type = ($data['type'] ?? null) === 'all' ? null : ($data['type'] ?? null);

        $page = $income->forGym($gym, $from, $to, $type)->paginate(25);

        return response()->json([
            'income' => collect($page->items())->map(fn (object $row) => $this->incomeRow($row))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'totals' => $income->totals($gym, $from, $to, $type),
        ]);
    }
}
