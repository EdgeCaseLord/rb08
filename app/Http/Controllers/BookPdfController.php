<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Spatie\LaravelPdf\Facades\Pdf;
use Illuminate\Support\Facades\Log;
use Spatie\Browsershot\Browsershot;

class BookPdfController extends Controller
{
    public function generate(Book $book)
    {
        Log::info('Generating PDF for book', ['book_id' => $book->id]);
        $recipes = $book->recipes()->get();

        Log::info('Recipes fetched', [
            'count' => $recipes->count(),
            'titles' => $recipes->pluck('title')->toArray(),
            'media' => $recipes->pluck('media')->toArray(),
        ]);

        if ($recipes->isEmpty()) {
            Log::warning('No recipes found for book', ['book_id' => $book->id]);
            return redirect()->back()->with('error', 'Keine Rezepte in diesem Buch.');
        }

        // foreach ($recipes as $index => $recipe) {
        //     Log::debug("Recipe $index details", [
        //         'title' => $recipe->title,
        //         'media' => $recipe->media,
        //         'course' => $recipe->course,
        //         'ingredients' => $recipe->ingredients,
        //         'steps' => $recipe->steps,
        //     ]);
        // }

        // Fetch book text templates for the patient's lab user
        $labUserId = $book->patient->lab_id ?? null;
        $impressumTemplate = \App\Models\TextTemplate::where('type', 'book_text_impressum')
            ->where('user_id', $labUserId)
            ->first();
        $erlaeuterungTemplate = \App\Models\TextTemplate::where('type', 'book_text_erlaeuterung')
            ->where('user_id', $labUserId)
            ->first();
        
        // Fallback to any template if lab-specific not found
        if (!$impressumTemplate) {
            $impressumTemplate = \App\Models\TextTemplate::where('type', 'book_text_impressum')->first();
        }
        if (!$erlaeuterungTemplate) {
            $erlaeuterungTemplate = \App\Models\TextTemplate::where('type', 'book_text_erlaeuterung')->first();
        }

        // PDF mit --no-sandbox generieren
        return Pdf::view('pdf.book', [
            'book' => $book,
            'recipes' => $recipes,
            'impressumTemplate' => $impressumTemplate,
            'erlaeuterungTemplate' => $erlaeuterungTemplate,
        ])
            ->format('a4')
            ->name('buch-' . $book->id . '-rezepte.pdf')
            ->withBrowsershot(function (Browsershot $browsershot) {
                $browsershot->noSandbox()->addChromiumArguments(['--outline']);
            })
            ->download();
    }
}
