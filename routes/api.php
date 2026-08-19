<?php

use App\Http\Controllers\PublicDocumentApiController;
use App\Http\Controllers\TifaAssistantController;
use Illuminate\Support\Facades\Route;

Route::post('/tifa/ask', [TifaAssistantController::class, 'ask']);
Route::get('/ruang-informasi', [PublicDocumentApiController::class, 'index'])->name('api.ruang-informasi.index');
