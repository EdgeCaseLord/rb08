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

        // Check if book already exists for this patient with these recipes (idempotency check)
        if ($this->recipeIds && !$this->bookId) {
            $existingBook = Book::where('patient_id', $patient->id)
                ->whereHas('recipes', function($query) {
                    $query->whereIn('recipes.id_recipe', $this->recipeIds);
                })
                ->first();

            if ($existingBook) {
                Log::info('CreateBookJob: Book already exists, skipping creation', [
                    'patient_id' => $patient->id,
                    'existing_book_id' => $existingBook->id,
                    'recipe_ids' => $this->recipeIds
                ]);
                return;
            }
        }

        // Additional check: prevent duplicate book creation for same patient
        // This handles race conditions where multiple CreateBookJob instances
        // are dispatched for the same patient before the first one completes
        $lockKey = "create_book_patient_{$patient->id}";
        $lockAcquired = \Illuminate\Support\Facades\Cache::lock($lockKey, 120)->get();

        if (!$lockAcquired) {
            Log::info('CreateBookJob: Another book creation is in progress for this patient, skipping', [
                'patient_id' => $patient->id,
                'lock_key' => $lockKey
            ]);
            return;
        }

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
                    // Check if user has a course filter active
                    $userCourseFilter = $this->filters['filterCourse'] ?? [];

                    // If user has a course filter and this course is not in it, skip
                    if (!empty($userCourseFilter) && !in_array($course, $userCourseFilter)) {
                        Log::debug('CreateBookJob: Skipping course due to user filter', [
                            'course' => $course,
                            'user_course_filter' => $userCourseFilter,
                        ]);
                        continue;
                    }

                    $courseFilter = ['filterCourse' => [$course], 'randomize_offset' => true];
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

            // Check if any recipes were added
            // Only fail if no recipes found AND no course filter is set (user expected all courses)
            $hasCourseFilter = !empty($this->filters['filterCourse'] ?? []);
            if (empty($currentRecipes) && !$hasCourseFilter) {
                \Filament\Notifications\Notification::make()
                    ->title(__('No Recipes Found'))
                    ->body(__('No recipes were found for :name with the current filter settings. Please adjust your filters and try again.', ['name' => $patient->name]))
                    ->warning()
                    ->persistent()
                    ->send();

                Log::warning('CreateBookJob: No recipes found for patient with current filters', [
                    'patient_id' => $patient->id,
                    'patient_name' => $patient->name,
                    'filters' => $this->filters,
                ]);
                return;
            }

            // If course filter is set and no recipes found, allow book creation with warning
            if (empty($currentRecipes) && $hasCourseFilter) {
                Log::info('CreateBookJob: No recipes found but course filter is active, allowing book creation', [
                    'patient_id' => $patient->id,
                    'course_filter' => $this->filters['filterCourse'],
                ]);
            }

            // Send email to lab
            Log::debug('CreateBookJob: Sending email to lab', [
                'book_id' => $book->id,
                'patient_id' => $patient->id,
            ]);
            $this->sendEmailToLab($book, $patient);

            if ($createdNewBook) {
                \Filament\Notifications\Notification::make()
                    ->title(__('Recipe Book Created'))
                    ->body(__('A personalized recipe book has been created for :name', ['name' => $patient->name]))
                    ->success()
                    ->send();
            } elseif ($this->bookId) {
                $request = request();
                $onBookEditPage = false;
                if ($request) {
                    $route = $request->route();
                    if ($route) {
                        $routeName = $route->getName();
                        $uri = $route->uri();
                        if ((is_string($routeName) && str_contains($routeName, 'book')) || (is_string($uri) && str_contains($uri, 'book'))) {
                            $onBookEditPage = true;
                        }
                    } elseif (str_contains($request->path(), 'book')) {
                        $onBookEditPage = true;
                    }
                }
                $body = __('The recipe book has been updated with new recipes based on the current filter set.');
                if ($onBookEditPage) {
                    $body .= "\n\n<br>BITTE DIE SEITE NEU LADEN";
                }
                \Filament\Notifications\Notification::make()
                    ->title(__('Recipe Book Updated'))
                    ->body($body)
                    ->success()
                    ->persistent()
                    ->send();
                // Emit Livewire event for UI refresh
                if (method_exists(\Livewire\Livewire::class, 'emit')) {
                    \Livewire\Livewire::emit('bookUpdated', $book->id);
                }
            }

            // Emit custom event if triggered by recreate book (filters set)
            if ($this->filters !== null) {
                if (method_exists(\Livewire\Livewire::class, 'emit')) {
                    \Livewire\Livewire::emit('bookRecreatedAndSent', $book->id);
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
        } finally {
            // Release the lock
            \Illuminate\Support\Facades\Cache::lock($lockKey)->release();
            Log::debug('CreateBookJob: Lock released', [
                'patient_id' => $patient->id,
                'lock_key' => $lockKey
            ]);
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

        // Fetch the text template for analysis_import_email for this lab
        $template = TextTemplate::where('type', 'analysis_import_email')
            ->where('user_id', $lab ? $lab->id : null)
            ->first();
        // Fallback to global template if not found
        if (!$template) {
            $template = TextTemplate::where('type', 'analysis_import_email')->first();
        }

        $editLink = url("https://myintest-rezepte.de/books/{$book->id}/edit");
        $userName = $lab ? $lab->name : ($patient->name ?? 'Lab');
        $patientName = $patient->name;

        // Prepare variables for template
        $vars = [
            'book' => $book,
            'patient' => $patient,
            'lab' => $lab,
            'edit_link' => $editLink,
            'record' => $book,
            'name' => $patientName,
            'lab_name' => $userName,
        ];

        // Get subject and body from template with variable replacement
        $subject = $template ? $template->getSubjectForLocaleWithVars($labLanguage, $vars) : 'Rezeptbuch für Ihre Patient:innen – Jetzt einsehen und bearbeiten';
        $body = $template ? $template->getBodyForLocale($labLanguage, $vars) : $this->getEmailBody($editLink, $userName);

        // Log email content to email channel
        Log::channel('email')->debug('Email content prepared', [
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
            // Log mail configuration before sending to email channel
            Log::channel('email')->debug('Mail configuration', [
                'default_mailer' => config('mail.default'),
                'log_channel' => config('mail.mailers.log.channel'),
                'from_address' => config('mail.from.address'),
                'from_name' => config('mail.from.name'),
                'smtp_host' => config('mail.mailers.smtp.host'),
                'smtp_port' => config('mail.mailers.smtp.port'),
                'smtp_encryption' => config('mail.mailers.smtp.encryption'),
            ]);

            Log::channel('email')->info('Attempting to send email', [
                'book_id' => $book->id,
                'lab_email' => $labEmail,
                'patient_id' => $patient->id,
                'subject' => $subject,
            ]);

            Mail::send([], [], function ($message) use ($subject, $body, $labEmail) {
                $message->to($labEmail)
                    ->from(config('mail.from.address'), config('mail.from.name'))
                    ->subject($subject)
                    ->html($body);
            });

            Log::channel('email')->info('✅ EMAIL SENT SUCCESSFULLY', [
                'book_id' => $book->id,
                'lab_email' => $labEmail,
                'patient_id' => $patient->id,
                'subject' => $subject,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            Log::channel('email')->error('❌ EMAIL SENDING FAILED', [
                'book_id' => $book->id,
                'lab_email' => $labEmail,
                'patient_id' => $patient->id,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now()->toISOString(),
            ]);
            throw $e; // Re-throw to ensure job fails and can be retried
        }
    }

    protected function getEmailBody(string $editLink, string $patientName): string
    {
        return <<<EOT
<p>Sehr geehrte/r {$patientName},</p>

<p>vielen Dank für Ihren Auftrag zur Typ-III-Allergiediagnostik.</p>

<p>Sie können das individuell zusammengestellte Rezeptbuch für Ihre Patientin bzw. Ihren Patienten, abgestimmt auf die festgestellten Nahrungsmittelunverträglichkeiten, über den folgenden Link einsehen, bearbeiten und herunterladen:</p>

<p><a href="{$editLink}">Rezeptbuch bearbeiten</a></p>

<p>Bitte prüfen Sie die Angaben und Rezepte sorgfältig. Bei Fragen oder Änderungswünschen stehen wir Ihnen selbstverständlich jederzeit gerne zur Verfügung.</p>

<p>Mit freundlichen Grüßen</p>
<p><strong>Wichtiger Hinweis:</strong> Aus Sicherheitsgründen müssen Sie bei Ihrer ersten Anmeldung ein eigenes Passwort festlegen. Bitte folgen Sie den Anweisungen auf der Login-Seite. Sie erhalten dann eine Email mit einem Link, um das Passwort zu setzen und können danach erneut den oben genannten Link nutzen.</p>
EOT;
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('CreateBookJob failed permanently', [
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
        Notification::make()
            ->title(__('Book Creation Failed'))
            ->body(__('Failed to create recipe book. Check logs for details.'))
            ->danger()
            ->persistent()
            ->send();
    }
}
