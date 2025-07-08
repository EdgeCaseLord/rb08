<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Browsershot\Browsershot;
use App\Models\Analysis;

class PdfController extends Controller
{
    public function downloadBook(Book $book)
    {
        if ($book->recipes()->count() === 0) {
            abort(404, 'Cannot generate a PDF for a book with no recipes.');
        }

        // Find the sample code more robustly
        $analysis = $book->analysis;
        if (!$analysis && $book->patient) {
            $analysis = Analysis::where('patient_id', $book->patient->id)->latest()->first();
        }
        $sampleCode = $analysis?->sample_code;
        $pdfFileName = $sampleCode ? ($sampleCode . '_RB.pdf') : "book-{$book->id}-rezepte.pdf";

        return Pdf::view('pdf.book', [
                'book' => $book,
                'recipes' => $book->recipes()->get()
            ])
            ->format('a4')
            ->withBrowsershot(function (Browsershot $browsershot) {
                $browsershot->noSandbox();
            })
            ->download($pdfFileName);
    }
}
