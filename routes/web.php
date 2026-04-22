<?php

use App\Http\Controllers\GoToAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard.alt');

Route::get('/conversations', function () {
    return view('conversations');
})->name('conversations.ui');

// OAuth routes (without /api prefix to match redirect URI)
Route::get('/goto/callback', [GoToAuthController::class, 'callback'])->name('goto.callback.web');
Route::get('/goto/auth', [GoToAuthController::class, 'redirect'])->name('goto.auth.web');
