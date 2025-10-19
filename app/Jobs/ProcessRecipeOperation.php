<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Book;
use App\Models\Recipe;
use App\Models\User;
use App\Services\CookButlerService;
use Illuminate\Support\Facades\Cache;

class ProcessRecipeOperation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $operation;
    public $recipeId;
    public $bookId;
    public $userId;
    public $timeout = 120;

    public function __construct($operation, $recipeId, $bookId = null, $userId = null)
    {
        $this->operation = $operation;
        $this->recipeId = $recipeId;
        $this->bookId = $bookId;
        $this->userId = $userId;
    }

    public function handle()
    {
        try {
            Log::info('Processing recipe operation', [
                'operation' => $this->operation,
                'recipeId' => $this->recipeId,
                'bookId' => $this->bookId,
                'userId' => $this->userId
            ]);

            switch ($this->operation) {
                case 'remove_from_book':
                    $this->handleRemoveFromBook();
                    break;
                case 'add_to_book':
                    $this->handleAddToBook();
                    break;
                case 'add_to_favorites':
                    $this->handleAddToFavorites();
                    break;
                case 'remove_from_favorites':
                    $this->handleRemoveFromFavorites();
                    break;
                case 'process_images':
                    $this->handleProcessImages();
                    break;
            }
        } catch (\Exception $e) {
            Log::error('Recipe operation failed', [
                'operation' => $this->operation,
                'recipeId' => $this->recipeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function handleRemoveFromBook()
    {
        $book = Book::find($this->bookId);
        if (!$book) return;

        $recipe = Recipe::where('id_recipe', $this->recipeId)
            ->orWhere('id_external', $this->recipeId)
            ->first();

        if (!$recipe) return;

        // Remove from book
        $book->recipes()->detach($recipe->id_recipe);

        // Update book status
        if ($book->status !== 'Warten auf Versand') {
            $book->status = 'Geändert nach Versand';
            $book->save();
            // Dispatch event to update UI
            \Filament\Notifications\Notification::make()
                ->title(__('Buch Status aktualisiert'))
                ->body('Der Buch Status wurde auf "Geändert nach Versand" gesetzt.')
                ->info()
                ->send();
        }

        // Clean up orphaned recipe
        if ($recipe->books()->count() === 0) {
            $recipe->delete();
        }

        Log::info('Recipe removed from book', [
            'bookId' => $this->bookId,
            'recipeId' => $this->recipeId
        ]);
    }

    private function handleAddToBook()
    {
        $book = Book::find($this->bookId);
        if (!$book) return;

        $recipe = Recipe::where('id_recipe', $this->recipeId)
            ->orWhere('id_external', $this->recipeId)
            ->first();

        if (!$recipe) {
            // Recipe doesn't exist, need to fetch from API and create it
            $this->createRecipeFromAPI();
            return;
        }

        // Add to book with limit checking
        if (!$book->addRecipeWithLimitCheck($recipe->id_recipe)) {
            Log::warning('Recipe limit reached for book', [
                'bookId' => $this->bookId,
                'recipeId' => $this->recipeId
            ]);
            return;
        }

        // Update book status
        if ($book->status !== 'Warten auf Versand') {
            $book->status = 'Geändert nach Versand';
            $book->save();
            // Dispatch event to update UI
            \Filament\Notifications\Notification::make()
                ->title(__('Buch Status aktualisiert'))
                ->body('Der Buch Status wurde auf "Geändert nach Versand" gesetzt.')
                ->info()
                ->send();
        }

        Log::info('Recipe added to book', [
            'bookId' => $this->bookId,
            'recipeId' => $this->recipeId
        ]);
    }

    private function createRecipeFromAPI()
    {
        try {
            $cookButlerService = app(CookButlerService::class);
            $book = Book::find($this->bookId);
            $patient = $book ? $book->patient : null;

            $recipeData = $cookButlerService->fetchRecipeDetails($this->recipeId);
            if (!$recipeData) return;

            $recipe = Recipe::create([
                'id_external' => $this->recipeId,
                'title' => $recipeData['title'] ?? '',
                'subtitle' => $recipeData['subtitle'] ?? null,
                'description' => $recipeData['description'] ?? null,
                'category' => is_string($recipeData['category'] ?? null) ? $recipeData['category'] : json_encode($recipeData['category'] ?? []),
                'substances' => is_string($recipeData['substances'] ?? null) ? $recipeData['substances'] : json_encode($recipeData['substances'] ?? []),
                'media' => is_string($recipeData['media'] ?? null) ? $recipeData['media'] : json_encode($recipeData['media'] ?? []),
                'images' => is_string($recipeData['images'] ?? null) ? $recipeData['images'] : json_encode($recipeData['images'] ?? []),
                'serving' => $recipeData['serving'] ?? null,
                'language' => 'de-de',
                'difficulty' => $recipeData['difficulty'] ?? null,
                'time' => is_string($recipeData['time'] ?? null) ? $recipeData['time'] : json_encode($recipeData['time'] ?? 'keine Angabe'),
                'steps' => is_string($recipeData['steps'] ?? null) ? $recipeData['steps'] : json_encode($recipeData['steps'] ?? []),
                'ingredients' => is_string($recipeData['ingredients'] ?? null) ? $recipeData['ingredients'] : json_encode($recipeData['ingredients'] ?? []),
                'diets' => is_string($recipeData['diets'] ?? null) ? $recipeData['diets'] : json_encode($recipeData['diets'] ?? []),
                'course' => !empty($recipeData['category']) ? \App\Filament\Resources\BookResource::mapCategoryToCourse(
                    \App\Filament\Resources\BookResource::getPrimaryCategory(
                        is_string($recipeData['category']) ? json_decode($recipeData['category'], true) : $recipeData['category']
                    )
                ) : 'main_course',
                'yield_quantity_1' => $recipeData['yield_quantity_1'] ?? null,
                'yield_quantity_2' => $recipeData['yield_quantity_2'] ?? null,
                'yield_info' => $recipeData['yield_info'] ?? null,
                'yield_info_short' => $recipeData['yield_info_short'] ?? null,
                'price' => $recipeData['price'] ?? null,
                'suitable_for_pregnancy' => $recipeData['suitable_for_pregnancy'] ?? null,
                'alttitle' => $recipeData['alttitle'] ?? null,
                'allergens' => is_string($recipeData['allergens'] ?? null) ? $recipeData['allergens'] : json_encode($recipeData['allergens'] ?? []),
                'create' => $recipeData['create'] ?? null,
                'last_update' => $recipeData['last_update'] ?? null,
            ]);

            // Now add to book
            $book = Book::find($this->bookId);
            if ($book && $book->addRecipeWithLimitCheck($recipe->id_recipe)) {
                // Update book status
                if ($book->status !== 'Warten auf Versand') {
                    $book->status = 'Geändert nach Versand';
                    $book->save();
                    // Dispatch event to update UI
                    \Filament\Notifications\Notification::make()
                        ->title(__('Buch Status aktualisiert'))
                        ->body('Der Buch Status wurde auf "Geändert nach Versand" gesetzt.')
                        ->info()
                        ->send();
                }
            }

            Log::info('Recipe created from API and added to book', [
                'bookId' => $this->bookId,
                'recipeId' => $this->recipeId
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create recipe from API', [
                'recipeId' => $this->recipeId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleAddToFavorites()
    {
        $user = User::find($this->userId);
        if (!$user) return;

        $settings = $user->settings ?? [];
        $favorites = $settings['favorites'] ?? [];

        if (!in_array($this->recipeId, $favorites)) {
            $favorites[] = $this->recipeId;
            $settings['favorites'] = $favorites;
            $user->settings = $settings;
            $user->save();
        }

        Log::info('Recipe added to favorites', [
            'userId' => $this->userId,
            'recipeId' => $this->recipeId
        ]);
    }

    private function handleRemoveFromFavorites()
    {
        $user = User::find($this->userId);
        if (!$user) return;

        $settings = $user->settings ?? [];
        $favorites = $settings['favorites'] ?? [];

        $favorites = array_filter($favorites, function($id) {
            return $id != $this->recipeId;
        });

        $settings['favorites'] = array_values($favorites);
        $user->settings = $settings;
        $user->save();

        Log::info('Recipe removed from favorites', [
            'userId' => $this->userId,
            'recipeId' => $this->recipeId
        ]);
    }

    private function handleProcessImages()
    {
        $recipe = Recipe::where('id_recipe', $this->recipeId)
            ->orWhere('id_external', $this->recipeId)
            ->first();

        if (!$recipe) return;

        // Process images and cache them
        $this->processRecipeImages($recipe);
    }

    private function processRecipeImages($recipe)
    {
        if (!$recipe->id_external) return;

        $cacheKey = 'recipe_images_' . $recipe->id_external;

        // Check if already cached
        if (Cache::has($cacheKey)) {
            return;
        }

        try {
            $cookButlerService = app(CookButlerService::class);
            $book = Book::find($this->bookId);
            $patient = $book ? $book->patient : null;

            $apiRecipe = $cookButlerService->fetchRecipeDetails($recipe->id_external);

            if (!empty($apiRecipe['images'])) {
                Cache::put($cacheKey, $apiRecipe['images'], now()->addDay());
                Log::info('Cached recipe images', ['recipeId' => $recipe->id_external]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to process recipe images', [
                'recipeId' => $recipe->id_external,
                'error' => $e->getMessage()
            ]);
        }
    }
}
