<?php

use App\Http\Controllers\AmoCRMController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [AmoCRMController::class, 'index'])->name('home');
Route::get('/contacts', [AmoCRMController::class, 'contacts'])->name('success');
Route::post('/get-contacts', [AmoCRMController::class, 'getContact']);
