<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\WalkIn;
use App\Support\Finance\SaleRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** R-137: the front desk sees today's walk-ins and records their payment. */
class WalkInDeskController extends Controller
{
    public function today(Request $request, Gym $gym): JsonResponse
    {
        $rows = WalkIn::with('payment')->whereDate('checked_in_at', today())->orderByDesc('checked_in_at')->orderByDesc('id')->get();
        $health = $request->user()->hasPermission($gym, 'health_records.records.view');

        return response()->json(['walk_ins' => $rows->map(fn (WalkIn $row) => $this->payload($row, $health))->values()]);
    }

    public function pay(Request $request, Gym $gym, int $walkIn, SaleRecorder $recorder): JsonResponse
    {
        $row = DB::transaction(function () use ($request, $gym, $walkIn, $recorder) {
            $row = WalkIn::lockForUpdate()->findOrFail($walkIn);
            abort_if($row->payment_id, 409, 'This visit is already paid.');

            $payment = $recorder->record($gym, $request->user(), [
                'income_type' => 'walk_in',
                'method' => $request->input('method'),
                'amount' => $request->input('amount'),
                'discount' => $request->input('discount'),
                'payer_name' => $row->name,
                'note' => "Walk-in visit #{$row->id}",
            ]);
            $row->forceFill(['payment_id' => $payment->id])->save();

            return $row->load('payment');
        });

        return response()->json(['walk_in' => $this->payload($row, $request->user()->hasPermission($gym, 'health_records.records.view'))]);
    }

    private function payload(WalkIn $row, bool $health): array
    {
        return [
            'id' => $row->id,
            'name' => $row->name,
            'phone' => $row->phone,
            'email' => $row->email,
            'checked_in_at' => $row->checked_in_at->toIso8601String(),
            'customer_id' => $row->customer_id,
            'paid' => $row->payment_id !== null,
            'amount' => $row->payment?->amount,
            'health_flag' => collect($row->health_answers)->contains(true),
        ] + ($health ? ['health_answers' => $row->health_answers, 'health_notes' => $row->health_notes] : []);
    }
}
