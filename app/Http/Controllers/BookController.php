<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BookController extends Controller
{
    public function addRecipe(Book $book, $recipeId, Request $request)
    {
        Log::info('Add recipe request received', [
            'book_id' => $book->id,
            'recipe_id' => $recipeId,
            'request' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        try {
            $book->addRecipe($recipeId);
            Log::info('Recipe added to book', [
                'book_id' => $book->id,
                'recipe_id' => $recipeId,
            ]);
            return redirect()->back()->with('success', 'Rezept hinzugefügt.');
        } catch (\Exception $e) {
            Log::error('Failed to add recipe to book', [
                'book_id' => $book->id,
                'recipe_id' => $recipeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function removeRecipe(Book $book, $recipeId, Request $request)
    {
        Log::info('Remove recipe request received', [
            'book_id' => $book->id,
            'recipe_id' => $recipeId,
            'request' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        try {
            $book->removeRecipe($recipeId);
            Log::info('Recipe removed from book', [
                'book_id' => $book->id,
                'recipe_id' => $recipeId,
            ]);
            return redirect()->back()->with('success', 'Rezept entfernt.');
        } catch (\Exception $e) {
            Log::error('Failed to remove recipe from book', [
                'book_id' => $book->id,
                'recipe_id' => $recipeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->back()->with('error', 'Failed to remove recipe: ' . $e->getMessage());
        }
    }

    public function bulkAddRecipes(Book $book, Request $request): \Illuminate\Http\RedirectResponse
    {
        $recipeIds = $request->input('recipe_ids', []);
        Log::info('Bulk adding recipes', ['book_id' => $book->id, 'recipe_ids' => $recipeIds]);

        foreach ($recipeIds as $recipeId) {
            try {
                $book->addRecipe($recipeId);
            } catch (\Exception $e) {
                Log::error('Failed to bulk add recipe to book', [
                    'book_id' => $book->id,
                    'recipe_id' => $recipeId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        Log::info('Bulk add completed', ['book_id' => $book->id, 'recipe_count' => $book->recipes()->count()]);
        return redirect()->back()->with('success', 'Ausgewählte Rezepte hinzugefügt.');
    }

    public function bulkRemoveRecipes(Book $book, Request $request): \Illuminate\Http\RedirectResponse
    {
        $recipeIds = $request->input('recipe_ids', []);
        \Log::info('Bulk removing recipes', ['book_id' => $book->id, 'recipe_ids' => $recipeIds]);
        foreach ($recipeIds as $recipeId) {
            try {
                $book->removeRecipe($recipeId);
            } catch (\Exception $e) {
                Log::error('Failed to bulk remove recipe from book', [
                    'book_id' => $book->id,
                    'recipe_id' => $recipeId,
                    'error' => $e->getMessage(),
                ]);
                // Continue with other recipes
            }
        }
        return redirect()->back()->with('success', 'Ausgewählte Rezepte entfernt.');
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'patient_id' => 'required|exists:users,id',
        ]);

        $book = Book::create($data);

        return response()->json(['book_id' => $book->id], 201);
    }
}
