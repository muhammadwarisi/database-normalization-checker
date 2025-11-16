<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebController;

Route::get('/', [WebController::class, 'index'])->name('home');
Route::get('/upload', [WebController::class, 'upload'])->name('upload');
Route::get('/results/{project_id}', [WebController::class, 'results'])->name('results');
Route::get('/visualize/{project_id}', [WebController::class, 'visualize'])->name('visualize');
