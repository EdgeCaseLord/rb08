<?php

namespace App\Jobs;

use App\Filament\Resources\BookResource;
use App\Models\Book;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Filament\Notifications\Notification;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Storage;

class CreateBookJobWithPdf implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $patient;
    public $uniqueId;
    public $tries = 3;

    public function __construct(User $patient)
    {
        $this->patient = $patient;
        $this->uniqueId = 'create_book_' . $patient->id . '_' . now()->timestamp;
    }

    public function uniqueId()
    {
        return $this->uniqueId;
    }

    public function handle(): void
    {
        $patient = $this->patient;

        // Validate patient
        if (!$patient) {
            Log::channel('email')->warning('No patient provided, skipping book creation', ['patient_id' => null]);
            return;
        }
        if ($patient->id === 1) {
            Log::channel('email')->warning('Unexpected dispatch for admin user ID 1, skipping book creation', ['patient_id' => 1]);
            return;
        }
        if ($patient->role !== 'patient') {
            Log::channel('email')->warning('User is not a patient, skipping book creation', [
                'user_id' => $patient->id,
                'role' => $patient->role,
            ]);
            return;
        }

        Log::channel('email')->info('CreateBookJob started for patient', ['patient_id' => $patient->id]);

        try {
            $book = Book::create([
                'patient_id' => $patient->id,
                'title' => "Persönliches Rezeptbuch für {$patient->name}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Log::channel('email')->info('Book created for patient', ['book_id' => $book->id, 'patient_id' => $patient->id]);

            // Fetch recipes for the patient directly
            $recipes = \App\Models\Recipe::whereHas('books', function($q) use ($patient) {
                $q->where('patient_id', $patient->id);
            })->get();
            if ($recipes->isEmpty()) {
                Log::channel('email')->warning('No recipes found for patient, skipping book recipe attachment', [
                    'patient_id' => $patient->id,
                    'book_id' => $book->id,
                ]);
                return;
            }
            Log::channel('email')->info('Fetched recipes for patient', [
                'patient_id' => $patient->id,
                'recipe_count' => $recipes->count(),
                'recipe_ids' => $recipes->pluck('id_recipe')->toArray(),
            ]);
            // Attach each recipe using addRecipe
            foreach ($recipes as $recipe) {
                try {
                    $book->addRecipe($recipe->id_recipe);
                } catch (\Exception $e) {
                    Log::channel('email')->error('Failed to add recipe to book', [
                        'book_id' => $book->id,
                        'patient_id' => $patient->id,
                        'recipe_id' => $recipe->id_recipe,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            Log::channel('email')->info('Assigned recipes to book', [
                'book_id' => $book->id,
                'patient_id' => $patient->id,
                'recipe_count' => $recipes->count(),
                'recipe_ids' => $recipes->pluck('id_recipe')->toArray(),
            ]);

            // Log current recipes
            $currentRecipes = $book->recipes()->pluck('recipes.id_recipe')->toArray();
            Log::channel('email')->info('Current recipes in book', [
                'book_id' => $book->id,
                'recipe_ids' => $currentRecipes,
            ]);

            // Generate PDF
            $pdfPath = $this->generateBookPdf($book);
            if (!$pdfPath) {
                Log::channel('email')->warning('PDF generation failed, skipping email sending', [
                    'book_id' => $book->id,
                    'patient_id' => $patient->id,
                ]);
                return;
            }

            // Send email to lab
            $emailSent = $this->sendEmailToLab($book, $pdfPath, $patient);

            // Delete PDF if email was sent successfully
            if ($emailSent) {
                try {
                    Storage::delete($pdfPath);
                    Log::channel('email')->info('PDF deleted after email sending', ['book_id' => $book->id, 'pdf_path' => $pdfPath]);
                } catch (\Exception $e) {
                    Log::channel('email')->error('Failed to delete PDF', [
                        'book_id' => $book->id,
                        'pdf_path' => $pdfPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Notification::make()
                ->title('Recipe Book Created')
                ->body("A personalized recipe book has been created for {$patient->name}.")
                ->success()
                ->send();

        } catch (\Exception $e) {
            Log::channel('email')->error('Failed to create book for patient', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function generateBookPdf(Book $book)
    {
        try {
            $recipes = $book->recipes()->get();

            Log::channel('email')->info('Generating PDF for book', [
                'book_id' => $book->id,
                'recipe_count' => $recipes->count(),
                'recipe_titles' => $recipes->pluck('title')->toArray(),
            ]);

            if ($recipes->isEmpty()) {
                Log::channel('email')->warning('No recipes found for book, skipping PDF generation', ['book_id' => $book->id]);
                return null;
            }

            $pdfPath = "books/book-{$book->id}-rezepte.pdf";
            Pdf::view('pdf.book', ['book' => $book, 'recipes' => $recipes])
                ->format('a4')
                ->name("buch-{$book->id}-rezepte.pdf")
                ->withBrowsershot(function (Browsershot $browsershot) {
                    $browsershot->noSandbox();
                })
                ->save(Storage::path($pdfPath));

            Log::channel('email')->info('PDF generated and saved', ['book_id' => $book->id, 'pdf_path' => $pdfPath]);

            return $pdfPath;
        } catch (\Exception $e) {
            Log::channel('email')->error('Failed to generate PDF for book', [
                'book_id' => $book->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    protected function sendEmailToLab(Book $book, string $pdfPath, User $patient): bool
    {
        // Use test email for now
        $labEmail = 'daniel@pixelhoch.de';
        // Uncomment to use actual lab email
        // $lab = $patient->lab ?? null;
        // $labEmail = $lab && $lab->email ? $lab->email : null;

        $editLink = url("/filament/resources/books/{$book->id}/edit");
        $subject = 'Rezeptbuch für Ihre Patient:innen – Jetzt einsehen und bearbeiten';

        // Email template data
        $emailData = [
            'subject' => $subject,
            'body' => $this->getEmailBody($editLink, $patient->name),
            'pdfPath' => Storage::path($pdfPath),
            'pdfName' => "buch-{$book->id}-rezepte.pdf",
        ];

        // Log email content
        Log::channel('email')->debug('Email content prepared', [
            'book_id' => $book->id,
            'to' => $labEmail,
            'subject' => $emailData['subject'],
            'body' => $emailData['body'],
            'pdf_path' => $emailData['pdfPath'],
            'pdf_name' => $emailData['pdfName'],
        ]);

        if (!$labEmail) {
            Log::channel('email')->warning('Lab email not found, skipping email', [
                'book_id' => $book->id,
                'patient_id' => $patient->id,
            ]);
            return false;
        }

        try {
            Mail::send([], [], function ($message) use ($emailData, $labEmail) {
                $message->to($labEmail)
                    ->subject(mb_encode_mimeheader($emailData['subject'], 'UTF-8'))
                    ->html($emailData['body'])
                    ->attach($emailData['pdfPath'], [
                        'as' => $emailData['pdfName'],
                        'mime' => 'application/pdf',
                    ]);
            });

            Log::channel('email')->info('Email sent to lab', [
                'book_id' => $book->id,
                'lab_email' => $labEmail,
                'patient_id' => $patient->id,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::channel('email')->error('Failed to send email to lab', [
                'book_id' => $book->id,
                'lab_email' => $labEmail,
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    protected function getEmailBody(string $editLink, string $patientName): string
    {
        return <<<EOT
<p>Sehr geehrte/r {$patientName},</p>

<p>vielen Dank für Ihren Auftrag zur Typ-III-Allergiediagnostik.</p>

<p>Im Anhang finden Sie das individuell zusammengestellte Rezeptbuch für Ihre Patientin bzw. Ihren Patienten – abgestimmt auf die festgestellten Nahrungsmittelunverträglichkeiten.</p>

<p>Über den folgenden Link kann das Rezeptbuch online eingesehen und bei Bedarf direkt angepasst werden:</p>

<p><a href="{$editLink}">Rezeptbuch bearbeiten</a></p>

<p>Bitte prüfen Sie die Angaben und Rezepte sorgfältig. Bei Fragen oder Änderungswünschen stehen wir Ihnen selbstverständlich jederzeit gerne zur Verfügung.</p>

<p>Mit freundlichen Grüßen</p>
EOT;
    }

    public function failed(\Throwable $exception): void
    {
        Log::channel('email')->error('CreateBookJob failed permanently', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
        Notification::make()
            ->title('Book Creation Failed')
            ->body('Failed to create recipe book. Check logs for details.')
            ->danger()
            ->persistent()
            ->send();
    }
}
