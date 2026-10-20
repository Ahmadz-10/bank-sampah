<?php

use App\Http\Controllers\HouseholdSurveyController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:survey-households')->group(function () {
    // Validation workflow: Admin / Super Admin only.
    Route::middleware('can:validate-surveys')->group(function () {
        Route::post('household-surveys/{household_survey}/approve', [HouseholdSurveyController::class, 'approve'])
            ->name('household-surveys.approve');
        Route::post('household-surveys/{household_survey}/reject', [HouseholdSurveyController::class, 'reject'])
            ->name('household-surveys.reject');
        Route::post('household-surveys/{household_survey}/reopen', [HouseholdSurveyController::class, 'reopen'])
            ->name('household-surveys.reopen');
    });

    // Photos are private: streamed only to logged-in users who may see surveys.
    Route::get('household-surveys/{household_survey}/photo', [HouseholdSurveyController::class, 'photo'])
        ->name('household-surveys.photo');

    Route::resource('household-surveys', HouseholdSurveyController::class);
});
