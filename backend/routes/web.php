<?php

use Illuminate\Support\Facades\Route;

// The screens live in frontend/. This backend only answers under /api.
Route::get('/', fn () => response()->json(['app' => config('app.name'), 'api' => url('/api')]));
