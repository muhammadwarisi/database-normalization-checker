<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;
 
Route::get('/',                       [WebController::class, 'index'])->name('home');
Route::get('/upload',                 [WebController::class, 'upload'])->name('upload');
Route::post('/parse',                 [WebController::class, 'parseTables'])->name('parse');
Route::post('/analyze',               [WebController::class, 'analyze'])->name('analyze');
Route::get('/results',                [WebController::class, 'results'])->name('results');
Route::get('/visualize',              [WebController::class, 'visualize'])->name('visualize');
