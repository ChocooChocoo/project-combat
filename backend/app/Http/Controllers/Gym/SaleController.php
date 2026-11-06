<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Concerns\PresentsFinance;
use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Support\Finance\SaleRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** R-63: a walk-in, merchandise or other desk sale, recorded as one payment. */
class SaleController extends Controller
{
    use PresentsFinance;

    public function store(Request $request, Gym $gym, SaleRecorder $recorder): JsonResponse
    {
        $payment = $recorder->record($gym, $request->user(), $request->all());

        return response()->json([
            'payment' => $this->salePayment($payment->loadMissing('user')),
        ], 201);
    }
}
