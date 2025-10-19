<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessRecipeOperation;
use Illuminate\Http\Request;

class BookRecipeOperationController extends Controller
{
    public function store(Request $request, $bookId)
    {
        $validated = $request->validate([
            'operation' => 'required|string|in:add_to_book,remove_from_book,add_to_favorites,remove_from_favorites',
            'recipeId' => 'required|string'
        ]);

        // Dispatch background job for persistence
        ProcessRecipeOperation::dispatch(
            $validated['operation'],
            $validated['recipeId'],
            $bookId
        );

        return response()->json(['success' => true]);
    }
}
