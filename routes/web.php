<?php

use Illuminate\Support\Facades\Route;
use App\Services\TifaDataService;

Route::get('/', function (TifaDataService $dataService) {
    return view('welcome', [
        'educationSummary' => $dataService->homepageSummary(),
        'districtSummary' => $dataService->homepageDistrictSummary(),
    ]);
})->name('home');
