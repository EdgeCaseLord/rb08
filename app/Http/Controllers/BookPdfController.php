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
        // Increase memory and time limits for PDF generation
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        Log::info('Generating PDF for book', ['book_id' => $book->id]);

        try {
            $recipes = $book->recipes()->get();

            Log::info('Recipes fetched', [
                'count' => $recipes->count(),
                'titles' => $recipes->pluck('title')->toArray(),
            ]);

            if ($recipes->isEmpty()) {
                Log::warning('No recipes found for book', ['book_id' => $book->id]);
                return redirect()->back()->with('error', 'Keine Rezepte in diesem Buch.');
            }

            // Limit debug logging to avoid memory issues
            Log::info('Recipe count', ['count' => $recipes->count()]);

            // Fetch book text templates
            $impressumTemplate = \App\Models\TextTemplate::where('type', 'book_text_impressum')->first();
            $erlaeuterungTemplate = \App\Models\TextTemplate::where('type', 'book_text_erlaeuterung')->first();

            Log::info('Starting PDF generation', ['book_id' => $book->id]);

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
                    $browsershot->noSandbox()
                        ->addChromiumArguments(['--outline'])
                        ->timeout(240); // 4 minutes timeout
                })
                ->download();

        } catch (\Throwable $e) {
            Log::error('PDF generation failed', [
                'book_id' => $book->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->view('errors.pdf-generation', [
                'message' => 'PDF-Generierung fehlgeschlagen: ' . $e->getMessage(),
                'book' => $book,
            ], 500);
        }
    }
}
