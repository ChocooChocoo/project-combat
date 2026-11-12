<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\PurchaseOrderProof;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** R-114: receipt and delivery proof files on a purchase order, kept on the private disk. */
class PurchaseOrderProofController extends Controller
{
    public function index(Gym $gym, int $order): JsonResponse
    {
        $row = $gym->purchaseOrders()->findOrFail($order);

        return response()->json(['proofs' => $this->proofs($row->id)]);
    }

    public function store(Request $request, Gym $gym, int $order): JsonResponse
    {
        $row = $gym->purchaseOrders()->findOrFail($order);
        $rules = config('procurement.proofs');

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:'.$rules['max_files']],
            'files.*' => ['file', 'mimes:'.implode(',', $rules['mimes']), 'max:'.$rules['max_kb']],
            'goods_receipt_id' => ['nullable', 'integer', Rule::exists('goods_receipts', 'id')->where('purchase_order_id', $row->id)],
        ], [
            'files.*.mimes' => 'Upload photos (JPG, PNG, WEBP) or PDF files.',
            'files.*.max' => 'Each file can be up to 5 MB.',
            'files.max' => 'Upload up to '.$rules['max_files'].' files at a time.',
        ]);

        $stored = [];

        try {
            DB::transaction(function () use ($request, $gym, $row, $data, &$stored) {
                foreach ($data['files'] as $file) {
                    $path = $file->store("purchase-proofs/{$gym->id}", 'local');
                    $stored[] = $path;
                    $proof = new PurchaseOrderProof;
                    $proof->forceFill([
                        'purchase_order_id' => $row->id,
                        'goods_receipt_id' => $data['goods_receipt_id'] ?? null,
                        'path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                    ])->save();
                }
            });
        } catch (\Throwable $error) {
            // Nothing half-saved: files written before the failure are removed again.
            Storage::disk('local')->delete($stored);
            throw $error;
        }

        return response()->json(['proofs' => $this->proofs($row->id)], 201);
    }

    public function show(Gym $gym, int $order, int $proof): StreamedResponse
    {
        $model = PurchaseOrderProof::where('purchase_order_id', $gym->purchaseOrders()->findOrFail($order)->id)->findOrFail($proof);

        return Storage::disk('local')->response($model->path, $model->original_name);
    }

    public function destroy(Request $request, Gym $gym, int $order, int $proof): JsonResponse
    {
        $model = PurchaseOrderProof::where('purchase_order_id', $gym->purchaseOrders()->findOrFail($order)->id)->findOrFail($proof);
        $user = $request->user();

        abort_unless(
            (int) $model->uploaded_by === (int) $user->id || $user->hasPermission($gym, 'procurement.receipts.manage'),
            403,
        );

        Storage::disk('local')->delete($model->path);
        $model->delete();

        return response()->json(['proofs' => $this->proofs($model->purchase_order_id)]);
    }

    private function proofs(int $orderId): array
    {
        return PurchaseOrderProof::with('uploadedBy')->where('purchase_order_id', $orderId)->orderBy('id')->get()
            ->map(fn (PurchaseOrderProof $proof) => [
                'id' => $proof->id,
                'goods_receipt_id' => $proof->goods_receipt_id,
                'original_name' => $proof->original_name,
                'mime' => $proof->mime,
                'size' => $proof->size,
                'is_image' => str_starts_with($proof->mime, 'image/'),
                'uploaded_by' => $proof->uploadedBy ? trim("{$proof->uploadedBy->first_name} {$proof->uploadedBy->last_name}") : null,
                'uploaded_at' => $proof->created_at?->toIso8601String(),
            ])->all();
    }
}
