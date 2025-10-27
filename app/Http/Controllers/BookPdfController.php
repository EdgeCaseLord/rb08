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

        Log::info('Generating PDF for book', [
            'book_id' => $book->id,
            'memory_limit' => ini_get('memory_limit'),
            'time_limit' => ini_get('max_execution_time')
        ]);

        $maxRetries = 2;
        $retryCount = 0;

        while ($retryCount <= $maxRetries) {
            try {
                $recipes = $book->recipes()->get();

                Log::info('Recipes fetched', [
                    'count' => $recipes->count(),
                    'retry_attempt' => $retryCount
                ]);

                if ($recipes->isEmpty()) {
                    Log::warning('No recipes found for book', ['book_id' => $book->id]);
                    return redirect()->back()->with('error', 'Keine Rezepte in diesem Buch.');
                }

                // Fetch book text templates
                $impressumTemplate = \App\Models\TextTemplate::where('type', 'book_text_impressum')->first();
                $erlaeuterungTemplate = \App\Models\TextTemplate::where('type', 'book_text_erlaeuterung')->first();

                Log::info('Starting PDF generation attempt', [
                    'book_id' => $book->id,
                    'retry_attempt' => $retryCount
                ]);

                // PDF mit --no-sandbox generieren
                return Pdf::view('pdf.book', [
                    'book' => $book,
                    'recipes' => $recipes,
                    'impressumTemplate' => $impressumTemplate,
                    'erlaeuterungTemplate' => $erlaeuterungTemplate,
                ])
                    ->format('a4')
                    ->name('buch-' . $book->id . '-rezepte.pdf')
                    ->withBrowsershot(function (Browsershot $browsershot) use ($retryCount) {
                        $browsershot->noSandbox()
                            ->addChromiumArguments([
                                '--outline',
                                '--disable-dev-shm-usage',
                                '--disable-gpu',
                                '--disable-web-security',
                                '--disable-features=VizDisplayCompositor',
                                '--run-all-compositor-stages-before-draw',
                                '--disable-background-timer-throttling',
                                '--disable-backgrounding-occluded-windows',
                                '--disable-renderer-backgrounding'
                            ])
                            ->timeout(240); // 4 minutes timeout
                    })
                    ->download();

            } catch (\Throwable $e) {
                $retryCount++;
                Log::error('PDF generation attempt failed', [
                    'book_id' => $book->id,
                    'retry_attempt' => $retryCount,
                    'max_retries' => $maxRetries,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                if ($retryCount > $maxRetries) {
                    Log::error('PDF generation failed after all retries', [
                        'book_id' => $book->id,
                        'total_attempts' => $retryCount
                    ]);

                    return response()->view('errors.pdf-generation', [
                        'message' => 'PDF-Generierung fehlgeschlagen nach ' . $retryCount . ' Versuchen: ' . $e->getMessage(),
                        'book' => $book,
                    ], 500);
                }

                // Wait before retry
                sleep(2);
            }
        }
    }
}
