<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Support\Finance\Receivables;
use Illuminate\Http\JsonResponse;

/** R-65: every member's outstanding balance, with its sources. */
class ReceivableController extends Controller
{
    public function index(Gym $gym, Receivables $receivables): JsonResponse
    {
        return response()->json(['receivables' => $receivables->forGym($gym)]);
    }
}
