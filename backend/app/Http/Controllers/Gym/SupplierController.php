<?php

namespace App\Http\Controllers\Gym;

use App\Enums\SupplierStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Gym\Concerns\PresentsProcurement;
use App\Models\Gym;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** R-77: gym suppliers, add and edit. Archiving is a status change. */
class SupplierController extends Controller
{
    use PresentsProcurement;

    public function index(Request $request, Gym $gym): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([...array_column(SupplierStatus::cases(), 'value'), 'all'])],
            'search' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $status = ($data['status'] ?? null) === 'all' ? null : ($data['status'] ?? null);

        $page = $gym->suppliers()
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->when($data['search'] ?? null, function ($query, string $search) {
                $term = '%'.$search.'%';
                $query->where(fn ($query) => $query->where('name', 'like', $term)
                    ->orWhere('contact_person', 'like', $term)
                    ->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(50);

        return response()->json([
            'suppliers' => collect($page->items())->map(fn (Supplier $supplier) => $this->supplierPayload($supplier))->values(),
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
        $data = $request->validate($this->rules());

        $supplier = $gym->suppliers()->create([
            ...$data,
            'status' => $data['status'] ?? SupplierStatus::Active->value,
        ]);

        return response()->json(['supplier' => $this->supplierPayload($supplier)], 201);
    }

    public function update(Request $request, Gym $gym, int $supplier): JsonResponse
    {
        $row = $gym->suppliers()->findOrFail($supplier);
        $row->fill($request->validate($this->rules(true)))->save();

        return response()->json(['supplier' => $this->supplierPayload($row->refresh())]);
    }

    private function rules(bool $updating = false): array
    {
        $presence = $updating ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'required', 'string', 'max:120'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:120'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'email' => ['sometimes', 'nullable', 'email', 'max:150'],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:120'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', Rule::enum(SupplierStatus::class)],
        ];
    }
}
