<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Models\Book;
use App\Models\Recipe;
use Spatie\LaravelPdf\Facades\Pdf;
use App\Http\Controllers\BookPdfController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\BookController; // Add this

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

    Route::get('/book/{book}/pdf', [BookPdfController::class, 'generate'])->name('book.pdf');

    Route::get('/language/switch/{locale}', [App\Http\Controllers\LanguageController::class, 'switch'])->name('language.switch');

    Route::get('/test-translation', function () {
        \Log::info('Testing translation', ['patients' => __('Patients'), 'locale' => \Illuminate\Support\Facades\App::getLocale()]);
        return __('Patients');
    });
    Route::get('/test-translation-details', function () {
        $locale = \Illuminate\Support\Facades\App::getLocale();
        $translation = __('Patients');
        $file_path = resource_path('lang/de.json');
        $file_exists = file_exists($file_path);
        $file_content = $file_exists ? file_get_contents($file_path) : 'File not found';
        \Log::info('Testing translation details', [
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
        \Log::info('Testing file path', [
            'resource_path' => $path,
            'real_path' => $real_path ?: 'Not resolved',
            'file_exists' => file_exists($path),
            'is_readable' => is_readable($path),
            'file_content' => file_exists($path) ? file_get_contents($path) : 'File not found'
        ]);
        return $real_path ?: 'Path not resolved';
    });
});
