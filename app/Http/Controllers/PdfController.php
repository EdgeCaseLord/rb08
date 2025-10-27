<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Browsershot\Browsershot;
use App\Models\Analysis;
use Illuminate\Support\Facades\Log;

class PdfController extends Controller
{
    public function downloadBook(Book $book)
    {
        // Increase memory and time limits for PDF generation
        ini_set('memory_limit', '512M');
        set_time_limit(300); // 5 minutes

        try {
            if ($book->recipes()->count() === 0) {
                Log::warning('PDF generierung abgebrochen: Das Buch enthält keine Rezepte', ['book_id' => $book->id]);
                abort(404, 'Kann kein Buch ohne Rezepte generieren.');
            }

            $analysis = $book->analysis;
            if (!$analysis && $book->patient) {
                $analysis = Analysis::where('patient_id', $book->patient->id)->latest()->first();
            }
            $sampleCode = $analysis?->sample_code;
            $pdfFileName = $sampleCode ? ($sampleCode . '_RB.pdf') : "book-{$book->id}-rezepte.pdf";

            Log::info('Starting PDF generation', ['book_id' => $book->id, 'filename' => $pdfFileName]);

            return Pdf::view('pdf.book', [
                    'book' => $book,
                    'recipes' => $book->recipes()->get()
                ])
                ->format('a4')
                ->withBrowsershot(function (Browsershot $browsershot) {
                    $browsershot->noSandbox()->timeout(240); // 4 minutes timeout
                })
                ->download($pdfFileName);
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
