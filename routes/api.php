<?php

use App\Http\Controllers\Api\BookRecipeOperationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/books/{bookId}/recipe-operations', [BookRecipeOperationController::class, 'store']);
});
