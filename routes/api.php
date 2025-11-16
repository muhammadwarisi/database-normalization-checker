<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\NormalizationController;

Route::post('/upload-dbml', [NormalizationController::class, 'uploadDbml']);
Route::get('/results/{project_id}', [NormalizationController::class, 'getResults']);
Route::get('/projects', [NormalizationController::class, 'listProjects']);
Route::post('/analyze-only', [NormalizationController::class, 'analyzeOnly']);
Route::get('/visualize/{project_id}', [NormalizationController::class, 'visualize']);