<?php

namespace App\Http\Controllers;

use App\Support\Finance\Receivables;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** R-65: a person's own balance across every gym they belong to. */
class ReceivableController extends Controller
{
    public function mine(Request $request, Receivables $receivables): JsonResponse
    {
        return response()->json($receivables->forUser($request->user()));
    }
}
