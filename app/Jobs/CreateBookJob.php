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
use Illuminate\Support\Facades\Auth;

class CreateBookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $patient;
    public $uniqueId;
    public $tries = 3;
    public $recipeIds;

    public function __construct(User $patient, array $recipeIds = null)
    {
        $this->patient = $patient;
        $this->uniqueId = 'create_book_' . $patient->id . '_' . now()->timestamp;
        $this->recipeIds = $recipeIds;
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
            $latestAnalysis = \App\Models\Analysis::where('patient_id', $patient->id)->latest('created_at')->first();
            $book = Book::create([
                'patient_id' => $patient->id,
                'title' => "Persönliches Rezeptbuch für {$patient->name}",
                'analysis_id' => $latestAnalysis ? $latestAnalysis->id : null,
                'status' => 'Warten auf Versand',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            Log::channel('email')->info('Book created for patient', ['book_id' => $book->id, 'patient_id' => $patient->id]);

            // Attach provided recipes if available
            if ($this->recipeIds && is_array($this->recipeIds) && count($this->recipeIds) > 0) {
                foreach ($this->recipeIds as $recipeId) {
                    try {
                        $book->addRecipe($recipeId);
                    } catch (\Exception $e) {
                        Log::channel('email')->error('Failed to add recipe to book', [
                            'book_id' => $book->id,
                            'patient_id' => $patient->id,
                            'recipe_id' => $recipeId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                Log::channel('email')->info('Assigned provided recipes to book', [
                    'book_id' => $book->id,
                    'patient_id' => $patient->id,
                    'recipe_count' => count($this->recipeIds),
                    'recipe_ids' => $this->recipeIds,
                ]);
            } else {
                // Fallback to old logic
                $recipes = \App\Models\Recipe::whereHas('books', function($q) use ($patient) {
                    $q->where('patient_id', $patient->id);
                })->get();
                Log::channel('email')->info('Fetched recipes for patient', [
                    'patient_id' => $patient->id,
                    'recipe_count' => $recipes->count(),
                    'recipe_ids' => $recipes->pluck('id_recipe')->toArray(),
                ]);
                if ($recipes->isEmpty()) {
                    Log::channel('email')->warning('No recipes found for patient, skipping book recipe attachment', [
                        'patient_id' => $patient->id,
                        'book_id' => $book->id,
                    ]);
                    return;
                }
                // Get lab settings for recipe limits
                $lab = $patient->lab;
                $defaultRecipesPerCourse = [
                    'starter' => 5,
                    'main_course' => 5,
                    'dessert' => 5,
                ];
                $recipesPerCourse = $lab ? ($lab->settings['recipes_per_course'] ?? $defaultRecipesPerCourse) : $defaultRecipesPerCourse;
                $totalRecipeLimit = array_sum($recipesPerCourse);

                // Group recipes by course
                $recipesByCourse = [];
                foreach ($recipes as $recipe) {
                    if ($recipe && $recipe->course) {
                        $recipesByCourse[$recipe->course][] = $recipe;
                    }
                }

                // Select random recipes within limits for each course
                $selectedRecipes = [];
                foreach ($recipesPerCourse as $course => $limit) {
                    $courseRecipes = $recipesByCourse[$course] ?? [];
                    if (!empty($courseRecipes)) {
                        $selectedRecipes = array_merge(
                            $selectedRecipes,
                            collect($courseRecipes)->shuffle()->take($limit)->all()
                        );
                    }
                }

                // Attach selected recipes to the book
                foreach ($selectedRecipes as $recipe) {
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
                    'recipe_count' => count($selectedRecipes),
                    'recipe_ids' => collect($selectedRecipes)->pluck('id_recipe')->toArray(),
                    'recipes_per_course' => $recipesPerCourse,
                ]);
            }

            // Log current recipes
            $currentRecipes = $book->recipes()->pluck('recipes.id_recipe')->toArray();
            Log::channel('email')->info('Current recipes in book', [
                'book_id' => $book->id,
                'recipe_ids' => $currentRecipes,
            ]);

            // Send email to lab
            $this->sendEmailToLab($book, $patient);

            Notification::make()
                ->title('Rezeptbuch erstellt')
                ->body("Ein personalisiertes Rezeptbuch wurde für {$patient->name} erstellt.")
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

    protected function sendEmailToLab(Book $book, User $patient): void
    {
        // Use test email for now
        $labEmail = 'daniel@pixelhoch.de';
        // Uncomment to use actual lab email
        // $lab = $patient->lab ?? null;
        // $labEmail = $lab && $lab->email ? $lab->email : null;

        $editLink = url("https://rezept-butler.com/books/{$book->id}/edit");
        $subject = 'Rezeptbuch für Ihre Patient:innen – Jetzt einsehen und bearbeiten';
        $userName = Auth::check() ? Auth::user()->name : 'Guest';

        // Email template data
        $emailData = [
            'subject' => $subject,
            'body' => $this->getEmailBody($editLink, $userName),
        ];

        // Log email content
        Log::channel('email')->debug('Email content prepared', [
            'book_id' => $book->id,
            'to' => $labEmail,
            'subject' => $emailData['subject'],
            'body' => $emailData['body'],
        ]);

        if (!$labEmail) {
            Log::channel('email')->warning('Lab email not found, skipping email', [
                'book_id' => $book->id,
                'patient_id' => $patient->id,
            ]);
            return;
        }

        try {
            Mail::send([], [], function ($message) use ($emailData, $labEmail) {
                $message->to($labEmail)
                    ->subject(mb_encode_mimeheader($emailData['subject'], 'UTF-8'))
                    ->html($emailData['body']);
            });

            Log::channel('email')->info('Email sent to lab', [
                'book_id' => $book->id,
                'lab_email' => $labEmail,
                'patient_id' => $patient->id,
            ]);
        } catch (\Exception $e) {
            Log::channel('email')->error('Failed to send email to lab', [
                'book_id' => $book->id,
                'lab_email' => $labEmail,
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function getEmailBody(string $editLink, string $patientName): string
    {
        return <<<EOT
<p>Sehr geehrte/r {$patientName},</p>

<p>vielen Dank für Ihren Auftrag zur Typ-III-Allergiediagnostik.</p>

<p>Sie können das individuell zusammengestellte Rezeptbuch für Ihre Patientin bzw. Ihren Patienten, abgestimmt auf die festgestellten Nahrungsmittelunverträglichkeiten, über den folgenden Link einsehen und bearbeiten:</p>

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
            ->title('Rezeptbuch-Erstellung fehlgeschlagen')
            ->body('Das Rezeptbuch konnte nicht erstellt werden. Bitte überprüfen Sie die Protokolle für Details.')
            ->danger()
            ->persistent()
            ->send();
    }
}
