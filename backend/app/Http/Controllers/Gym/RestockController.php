<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Support\Procurement\Restock;
use Illuminate\Http\JsonResponse;

/** R-75: items at or below minimum plus items needed by upcoming activity. */
class RestockController extends Controller
{
    public function index(Gym $gym, Restock $restock): JsonResponse
    {
        return response()->json(['restock' => $restock->forGym($gym)->values()]);
    }
}
