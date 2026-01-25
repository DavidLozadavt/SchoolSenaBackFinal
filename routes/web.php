<?php

use App\Http\Controllers\gestion_transporte\ReportController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('onfigure_apli', function () {
    Artisan::call('storage:link');
});

Route::get('optimize', function () {
    Artisan::call('optimize');
});

Route::get('/ticket-viaje', [ReportController::class, 'generatePDFTicket']);
Route::get('/planilla-viaje', [ReportController::class, 'generatePDFPlanilla']);


// SPA
Route::get('/{any}', [App\Http\Controllers\WebController::class, 'index'])
    ->where('any', '.*');
    