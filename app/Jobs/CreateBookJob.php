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
use App\Models\TextTemplate;

class CreateBookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $patient;
    public $uniqueId;
    public $tries = 3;
    public $recipeIds;
    protected $bookId;
    protected $filters;

    public function __construct(User $patient, array $recipeIds = null, $bookId = null, array $filters = null)
    {
        $this->patient = $patient;
        $this->uniqueId = 'create_book_' . $patient->id . '_' . now()->timestamp;
        $this->recipeIds = $recipeIds;
        $this->bookId = $bookId;
        $this->filters = $filters;
    }

    public function uniqueId()
    {
        return $this->uniqueId;
    }

    public function handle(): void
    {
        $patient = $this->patient;
        Log::debug('CreateBookJob: Starting handle()', [
            'patient_id' => $patient ? $patient->id : null,
            'recipeIds_provided' => $this->recipeIds,
            'bookId' => $this->bookId,
        ]);

        // Validate patient
        if (!$patient) {
            Log::warning('No patient provided, skipping book creation', ['patient_id' => null]);
            return;
        }
        if ($patient->id === 1) {
            Log::warning('Unexpected dispatch for admin user ID 1, skipping book creation', ['patient_id' => 1]);
            return;
        }
        if ($patient->role !== 'patient') {
            Log::warning('User is not a patient, skipping book creation', [
                'user_id' => $patient->id,
                'role' => $patient->role,
            ]);
            return;
        }
        Log::info('CreateBookJob: Patient validated', ['patient_id' => $patient->id]);

        try {
            $latestAnalysis = \App\Models\Analysis::where('patient_id', $patient->id)->latest('created_at')->first();
            Log::debug('CreateBookJob: Latest analysis fetched', [
                'patient_id' => $patient->id,
                'latest_analysis_id' => $latestAnalysis ? $latestAnalysis->id : null,
            ]);

            $createdNewBook = false;
            // If bookId is given, use existing book, else create new
            if ($this->bookId) {
                $book = \App\Models\Book::find($this->bookId);
                if (!$book) {
                    Log::error('CreateBookJob: Book not found for update', ['book_id' => $this->bookId]);
                    return;
                }
                Log::info('CreateBookJob: Using existing book for update', ['book_id' => $book->id, 'patient_id' => $patient->id]);
                // Clear recipes
                $book->recipes()->detach();
            } else {
                $book = Book::create([
                    'patient_id' => $patient->id,
                    'title' => "Persönliches Rezeptbuch für {$patient->name}",
                    'analysis_id' => $latestAnalysis ? $latestAnalysis->id : null,
                    'status' => 'Warten auf Versand',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                Log::info('CreateBookJob: Book created', ['book_id' => $book->id, 'patient_id' => $patient->id]);
                $createdNewBook = true;
            }

            // Attach provided recipes if available
            if ($this->recipeIds && is_array($this->recipeIds) && count($this->recipeIds) > 0) {
                Log::debug('CreateBookJob: Attaching provided recipes', [
                    'book_id' => $book->id,
                    'recipe_ids' => $this->recipeIds,
                ]);
                foreach ($this->recipeIds as $recipeId) {
                    try {
                        Log::debug('CreateBookJob: Adding recipe to book', [
                            'book_id' => $book->id,
                            'recipe_id' => $recipeId,
                        ]);
                        $book->addRecipe($recipeId);
                    } catch (\Exception $e) {
                        Log::error('Failed to add recipe to book', [
                            'book_id' => $book->id,
                            'patient_id' => $patient->id,
                            'recipe_id' => $recipeId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                Log::info('CreateBookJob: Assigned provided recipes to book', [
                    'book_id' => $book->id,
                    'patient_id' => $patient->id,
                    'recipe_count' => count($this->recipeIds),
                    'recipe_ids' => $this->recipeIds,
                ]);
            } else {
                Log::debug('CreateBookJob: No provided recipes, fetching from CookButlerService using patient filters', [
                    'patient_id' => $patient->id,
                ]);
                $authUser = Auth::user();
                if ($authUser instanceof \App\Models\User && method_exists($authUser, 'isLab') && $authUser->isLab()) {
                    $lab = $authUser;
                } else {
                    $lab = $patient->lab;
                }
                $defaultRecipesPerCourse = [
                    'starter' => 5,
                    'main_course' => 5,
                    'dessert' => 5,
                ];
                $recipesPerCourse = $lab ? ($lab->settings['recipes_per_course'] ?? $defaultRecipesPerCourse) : $defaultRecipesPerCourse;
                $service = new \App\Services\CookButlerService();
                $selectedRecipes = [];
                foreach ($recipesPerCourse as $course => $limit) {
                    $courseFilter = ['filterCourse' => [$course]];
                    $requestFilters = array_merge($this->filters ?? [], $courseFilter);
                    $result = $service->fetchAvailableRecipesForPatient($patient, $requestFilters, $limit);
                    $recipeIds = $result['recipe_ids'] ?? [];
                    Log::debug('CreateBookJob: CookButlerService fetched recipes', [
                        'course' => $course,
                        'recipe_ids' => $recipeIds,
                        'limit' => $limit,
                    ]);
                    // Fetch all recipe details in one batch call
                    if (!empty($recipeIds)) {
                        $service->fetchRecipeDetailsBatch($recipeIds, $patient);
                        foreach ($recipeIds as $recipeId) {
                            try {
                                $book->addRecipe($recipeId);
                            } catch (\Exception $e) {
                                Log::error('Failed to add recipe to book', [
                                    'book_id' => $book->id,
                                    'patient_id' => $patient->id,
                                    'recipe_id' => $recipeId,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                    $selectedRecipes = array_merge($selectedRecipes, $recipeIds);
                }
                Log::info('CreateBookJob: Assigned CookButlerService recipes to book', [
                    'book_id' => $book->id,
                    'patient_id' => $patient->id,
                    'recipe_count' => count($selectedRecipes),
                    'recipe_ids' => $selectedRecipes,
                    'recipes_per_course' => $recipesPerCourse,
                ]);
            }

            // Log current recipes
            $currentRecipes = $book->recipes()->pluck('recipes.id_recipe')->toArray();
            Log::info('CreateBookJob: Current recipes in book', [
                'book_id' => $book->id,
                'recipe_ids' => $currentRecipes,
            ]);

            // Send email to lab
            Log::debug('CreateBookJob: Sending email to lab', [
                'book_id' => $book->id,
                'patient_id' => $patient->id,
            ]);
            $this->sendEmailToLab($book, $patient);

            if ($createdNewBook) {
                \Filament\Notifications\Notification::make()
                    ->title('Rezeptbuch erstellt')
                    ->body("Ein personalisiertes Rezeptbuch wurde für {$patient->name} erstellt.")
                    ->success()
                    ->send();
            } elseif ($this->bookId) {
                \Filament\Notifications\Notification::make()
                    ->title('Rezeptbuch aktualisiert')
                    ->body("Das Rezeptbuch wurde mit neuen Rezepten basierend auf dem aktuellen Filter-Set aktualisiert.")
                    ->success()
                    ->send();
                // Emit Livewire event for UI refresh
                if (method_exists(\Livewire\Livewire::class, 'emit')) {
                    \Livewire\Livewire::emit('bookUpdated', $book->id);
                }
            }

            Log::debug('CreateBookJob: handle() completed successfully', [
                'book_id' => $book->id,
                'patient_id' => $patient->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create book for patient', [
                'patient_id' => $patient->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    protected function sendEmailToLab(Book $book, User $patient): void
    {
        // Get lab user and email
        $authUser = Auth::user();
        if ($authUser instanceof \App\Models\User && method_exists($authUser, 'isLab') && $authUser->isLab()) {
            $lab = $authUser;
        } else {
            $lab = $patient->lab;
        }
        $labEmail = $lab && $lab->email ? $lab->email : 'daniel@pixelhoch.de';
        $labLanguage = $lab && $lab->language ? $lab->language : 'de';

        // Fetch the text template for book_send_email for this lab
        $template = TextTemplate::where('type', 'book_send_email')
            ->where('user_id', $lab ? $lab->id : null)
            ->first();
        // Fallback to global template if not found
        if (!$template) {
            $template = TextTemplate::where('type', 'book_send_email')->first();
        }

        $editLink = url("https://rezept-butler.com/books/{$book->id}/edit");
        $userName = $lab ? $lab->name : ($patient->name ?? 'Lab');
        $patientName = $patient->name;

        // Prepare replacements for template variables
        $replacements = [
            '{edit_link}' => $editLink,
            '{lab_name}' => $userName,
            '{patient_name}' => $patientName,
        ];

        // Get subject and body from template, fallback to default if needed
        $subject = $template ? $template->getSubjectForLocale($labLanguage) : 'Rezeptbuch für Ihre Patient:innen – Jetzt einsehen und bearbeiten';
        $body = $template ? $template->getBodyForLocale($labLanguage) : $this->getEmailBody($editLink, $userName);

        // Replace variables in subject and body
        $subject = strtr($subject, $replacements);
        $body = strtr($body, $replacements);

        // Log email content
        Log::debug('Email content prepared', [
            'book_id' => $book->id,
            'to' => $labEmail,
            'subject' => $subject,
            'body' => $body,
        ]);

        if (!$labEmail) {
            Log::warning('Lab email not found, skipping email', [
                'book_id' => $book->id,
                'patient_id' => $patient->id,
            ]);
            return;
        }

        try {
            Mail::send([], [], function ($message) use ($subject, $body, $labEmail) {
                $message->to($labEmail)
                    ->subject(mb_encode_mimeheader($subject, 'UTF-8'))
                    ->html($body);
            });

            Log::info('Email sent to lab', [
                'book_id' => $book->id,
                'lab_email' => $labEmail,
                'patient_id' => $patient->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send email to lab', [
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
        Log::error('CreateBookJob failed permanently', [
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
