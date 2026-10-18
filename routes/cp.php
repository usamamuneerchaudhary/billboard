<?php

use Billboard\Http\Controllers\CP\AdActionController;
use Billboard\Http\Controllers\CP\AdsController;
use Billboard\Http\Controllers\CP\PlacementActionController;
use Billboard\Http\Controllers\CP\PlacementsController;
use Billboard\Http\Controllers\CP\ReportsController;
use Illuminate\Support\Facades\Route;

Route::prefix('billboard')->name('billboard.')->group(function () {
    Route::post('ads/actions', [AdActionController::class, 'run'])->name('ads.actions.run');
    Route::post('ads/actions/list', [AdActionController::class, 'bulkActions'])->name('ads.actions.bulk');
    Route::get('ads', [AdsController::class, 'index'])->name('ads.index');
    Route::get('ads/create', [AdsController::class, 'create'])->name('ads.create');
    Route::post('ads', [AdsController::class, 'store'])->name('ads.store');
    Route::get('ads/{ad}/edit', [AdsController::class, 'edit'])->name('ads.edit');
    Route::patch('ads/{ad}', [AdsController::class, 'update'])->name('ads.update');

    Route::post('placements/actions', [PlacementActionController::class, 'run'])->name('placements.actions.run');
    Route::post('placements/actions/list', [PlacementActionController::class, 'bulkActions'])->name('placements.actions.bulk');
    Route::get('placements', [PlacementsController::class, 'index'])->name('placements.index');
    Route::get('placements/create', [PlacementsController::class, 'create'])->name('placements.create');
    Route::post('placements', [PlacementsController::class, 'store'])->name('placements.store');
    Route::get('placements/{placement}/edit', [PlacementsController::class, 'edit'])->name('placements.edit');
    Route::patch('placements/{placement}', [PlacementsController::class, 'update'])->name('placements.update');

    Route::get('reports', [ReportsController::class, 'index'])->name('reports.index');
    Route::get('reports/export', [ReportsController::class, 'export'])->name('reports.export');
});
