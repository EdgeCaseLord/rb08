<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Browsershot\Browsershot;
use App\Models\Analysis;
use App\Models\TextTemplate;

class PdfController extends Controller
{
    public function downloadBook(Book $book)
    {
        try {
            if ($book->recipes()->count() === 0) {
                \Log::warning('PDF generierung abgebrochen: Das Buch enthält keine Rezepte', ['book_id' => $book->id]);
                abort(404, 'Kann kein Buch ohne Rezepte generieren.');
            }

            $analysis = $book->analysis;
            if (!$analysis && $book->patient) {
                $analysis = Analysis::where('patient_id', $book->patient->id)->latest()->first();
            }
            $sampleCode = $analysis?->sample_code;
            $pdfFileName = $sampleCode ? ($sampleCode . '_RB.pdf') : "book-{$book->id}-rezepte.pdf";

            // Increase limits for complex PDFs
            @ini_set('memory_limit', '1024M');
            @set_time_limit(600);

            // Fetch optional text templates so they appear in the PDF
            $impressumTemplate = TextTemplate::where('type', 'book_text_impressum')->first();
            $erlaeuterungTemplate = TextTemplate::where('type', 'book_text_erlaeuterung')->first();

            return Pdf::view('pdf.book', [
                    'book' => $book,
                    'recipes' => $book->recipes()->get(),
                    'impressumTemplate' => $impressumTemplate,
                    'erlaeuterungTemplate' => $erlaeuterungTemplate,
                ])
                ->format('a4')
                ->withBrowsershot(function (Browsershot $browsershot) {
                    $browsershot
                        ->noSandbox()
                        ->addChromiumArguments(['--disable-dev-shm-usage', '--disable-gpu'])
                        ->timeout(120);
                })
                ->download($pdfFileName);
        } catch (\Throwable $e) {
            \Log::error('PDF generation failed', [
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
