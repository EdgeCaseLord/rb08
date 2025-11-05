<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Models\Book;
use App\Models\Recipe;
use Spatie\LaravelPdf\Facades\Pdf;
use App\Http\Controllers\BookPdfController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BookController; // Add this
use App\Http\Controllers\PdfController;
use App\Http\Controllers\PatientFilterController;
use Illuminate\Support\Facades\Log;

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::post('/books/{book}/recipes/{recipe}', [BookController::class, 'addRecipe'])->name('book.addRecipe');
    Route::delete('/books/{book}/recipes/{recipe}', [BookController::class, 'removeRecipe'])->name('book.removeRecipe');
    Route::post('/books/{book}/bulk-add-recipes', [BookController::class, 'bulkAddRecipes'])->name('book.bulkAddRecipes');
    Route::delete('/books/{book}/bulk-remove-recipes', [BookController::class, 'bulkRemoveRecipes'])->name('book.bulkRemoveRecipes');
    Route::post('/books', [BookController::class, 'create'])->name('book.create');
    Route::put('/books/{book}', [BookController::class, 'update'])->name('book.update');

//    Route::get('/recipe/{recipe}/view', function (Recipe $recipe) {
//        return view('filament.resources.recipe-resource.view-recipe', ['recipe' => $recipe]);
//    })->name('recipe.view');
    Route::get('/recipes/{recipe}', function (Recipe $recipe) {
        return view('filament.resources.recipe-resource.view-recipe', ['recipe' => $recipe]);
    })->name('recipe.view');

    Route::get('/recipes/{recipe}/pdf', function (Recipe $recipe) {
        return Pdf::view('filament.resources.recipe-resource.view-recipe-pdf', [
            'recipe' => $recipe,
        ])
            ->format('a4')
            ->name('recipe-' . $recipe->id . '.pdf')
            ->withBrowsershot(function (\Spatie\Browsershot\Browsershot $browsershot) {
                $browsershot->noSandbox();
            })
            ->download();
    })->name('recipe.pdf');

    Route::get('/test-pdf/{book}', function (Book $book) {
        // Simple test endpoint to diagnose PDF generation issues
        ini_set('memory_limit', '1G');
        set_time_limit(600);

        Log::info('PDF test endpoint called', [
            'book_id' => $book->id,
            'recipe_count' => $book->recipes()->count(),
            'memory_limit' => ini_get('memory_limit'),
            'time_limit' => ini_get('max_execution_time')
        ]);

        try {
            $recipes = $book->recipes()->take(1)->get(); // Only test with 1 recipe

            return Pdf::view('pdf.book', [
                'book' => $book,
                'recipes' => $recipes,
                'impressumTemplate' => null,
                'erlaeuterungTemplate' => null,
            ])
                ->format('a4')
                ->name('test-buch-' . $book->id . '.pdf')
                ->withBrowsershot(function (\Spatie\Browsershot\Browsershot $browsershot) {
                    $browsershot->noSandbox()
                        ->addChromiumArguments(['--disable-dev-shm-usage', '--disable-gpu'])
                        ->timeout(120);
                })
                ->download();
        } catch (\Throwable $e) {
            Log::error('PDF test failed', [
                'book_id' => $book->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => $e->getMessage(),
                'book_id' => $book->id,
                'memory_limit' => ini_get('memory_limit'),
                'time_limit' => ini_get('max_execution_time')
            ], 500);
        }
    })->name('test.pdf');

    Route::get('/language/switch/{locale}', [App\Http\Controllers\LanguageController::class, 'switch'])->name('language.switch');

    Route::get('/test-translation', function () {
        Log::info('Testing translation', ['patients' => __('Patients'), 'locale' => \Illuminate\Support\Facades\App::getLocale()]);
        return __('Patients');
    });
    Route::get('/test-translation-details', function () {
        $locale = \Illuminate\Support\Facades\App::getLocale();
        $translation = __('Patients');
        $file_path = resource_path('lang/de.json');
        $file_exists = file_exists($file_path);
        $file_content = $file_exists ? file_get_contents($file_path) : 'File not found';
        Log::info('Testing translation details', [
            'locale' => $locale,
            'translation' => $translation,
            'file_path' => $file_path,
            'file_exists' => $file_exists,
            'file_content' => $file_content
        ]);
        return $translation;
    });
    Route::get('/test-path', function () {
        $path = resource_path('lang/de.json');
        $real_path = realpath($path);
        Log::info('Testing file path', [
            'resource_path' => $path,
            'real_path' => $real_path ?: 'Not resolved',
            'file_exists' => file_exists($path),
            'is_readable' => is_readable($path),
            'file_content' => file_exists($path) ? file_get_contents($path) : 'File not found'
        ]);
        return $real_path ?: 'Path not resolved';
    });

    Route::post('/admin/patients/{patient}/filters', [PatientFilterController::class, 'save'])->name('patients.filters.save');

    // Minimal JSON endpoints for Edit Book page
    Route::get('/books/{book}/recipes.json', [\App\Http\Controllers\RecipeListController::class, 'bookRecipes'])->name('books.recipes.json');
    Route::get('/books/{book}/status', [\App\Http\Controllers\BookController::class, 'status'])->name('books.status');
    Route::get('/favorites.json', [\App\Http\Controllers\RecipeListController::class, 'favorites'])->name('favorites.json');
    Route::get('/available.json', [\App\Http\Controllers\RecipeListController::class, 'available'])->name('available.json');
    Route::post('/favorites/{id}', [\App\Http\Controllers\RecipeListController::class, 'addFavorite'])->name('favorites.add');
    Route::delete('/favorites/{id}', [\App\Http\Controllers\RecipeListController::class, 'removeFavorite'])->name('favorites.remove');
});

Route::get('/books/{book}/pdf', [PdfController::class, 'downloadBook'])->name('book.pdf');
