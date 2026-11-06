<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Concerns\PresentsFinance;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsProcurement;
use App\Models\Expense;
use App\Models\Gym;
use App\Models\SupplierPayment;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** R-64: manual expenses and their corrections. */
class ExpenseController extends Controller
{
    use PresentsFinance;
    use PresentsProcurement;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'category' => ['nullable', Rule::in([...array_keys(config('finance.expense_categories')), 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $category = ($data['category'] ?? null) === 'all' ? null : ($data['category'] ?? null);

        $page = $gym->expenses()
            ->with('recordedBy')
            ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('incurred_on', '>=', $from))
            ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('incurred_on', '<=', $to))
            ->when($category, fn ($query, $value) => $query->where('category', $value))
            ->orderByDesc('incurred_on')
            ->orderByDesc('id')
            ->paginate(25);

        $totals = $this->filteredTotals($gym, $data['from'] ?? null, $data['to'] ?? null, $category);

        // R-66: supplier payments are cash out, shown beside expenses but never
        // added to the expense totals or treated as a second cost.
        $supplierPayments = $category === null
            ? $gym->supplierPayments()
                ->with(['recordedBy', 'purchaseOrder', 'supplier'])
                ->when($data['from'] ?? null, fn ($query, $from) => $query->whereDate('paid_on', '>=', $from))
                ->when($data['to'] ?? null, fn ($query, $to) => $query->whereDate('paid_on', '<=', $to))
                ->orderByDesc('paid_on')
                ->orderByDesc('id')
                ->limit(100)
                ->get()
            : collect();

        return response()->json([
            'expenses' => collect($page->items())->map(fn (Expense $expense) => $this->expensePayload($expense))->values(),
            'supplier_payments' => $supplierPayments->map(fn (SupplierPayment $payment) => $this->supplierPaymentPayload($payment))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'totals' => $totals,
        ]);
    }

    public function store(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(config('finance.expense_categories')))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'incurred_on' => ['nullable', 'date_format:Y-m-d'],
            'method' => ['nullable', Rule::in(['cash', 'card', 'ewallet', 'bank'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $expense = $gym->expenses()->create([
            'category' => $data['category'],
            'amount' => number_format((float) $data['amount'], 2, '.', ''),
            'incurred_on' => $data['incurred_on'] ?? today()->toDateString(),
            'method' => $data['method'] ?? null,
            'note' => $data['note'] ?? null,
            'recorded_by' => $request->user()->id,
        ]);
        $expense->load('recordedBy');

        return response()->json(['expense' => $this->expensePayload($expense)], 201);
    }

    public function correct(Request $request, Gym $gym, int $expense): JsonResponse
    {
        $original = $gym->expenses()->findOrFail($expense);

        abort_if($original->corrects_id !== null || (float) $original->amount <= 0, 409, 'Only an expense can be corrected, not a correction.');
        abort_if($gym->expenses()->where('corrects_id', $original->id)->exists(), 409, 'This expense was already corrected.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
        ], ['reason.required' => 'Say why this expense is being corrected.']);

        $correction = $gym->expenses()->create([
            'category' => $original->category,
            'amount' => number_format(-1 * (float) $original->amount, 2, '.', ''),
            'incurred_on' => today()->toDateString(),
            'method' => $original->method,
            'note' => "Corrects #{$original->id}: {$data['reason']}",
            'corrects_id' => $original->id,
            'recorded_by' => $request->user()->id,
        ]);
        $correction->load('recordedBy');

        return response()->json(['expense' => $this->expensePayload($correction)], 201);
    }

    private function filteredTotals(Gym $gym, ?string $from, ?string $to, ?string $category): array
    {
        return DB::table('expenses')
            ->where('gym_id', $gym->id)
            ->when($from, fn (Builder $query, $value) => $query->whereDate('incurred_on', '>=', $value))
            ->when($to, fn (Builder $query, $value) => $query->whereDate('incurred_on', '<=', $value))
            ->when($category, fn (Builder $query, $value) => $query->where('category', $value))
            ->selectRaw('category, SUM(amount) as amount')
            ->groupBy('category')
            ->orderBy('category')
            ->get()
            ->map(fn (object $row) => [
                'category' => $row->category,
                'amount' => number_format((float) $row->amount, 2, '.', ''),
            ])->all();
    }
}
