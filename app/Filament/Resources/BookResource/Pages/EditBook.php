<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use App\Models\UserSettings;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Filament\Actions;
use App\Models\TextTemplate;

/**
 * @property \App\Models\Book $record
 */
class EditBook extends EditRecord
{
    protected static string $resource = BookResource::class;

    public function mount($record): void
    {
        Log::info('EditBook: Page mounted', ['record_id' => $record]);
        parent::mount($record);
    }

    protected function afterMount(): void
    {
        Log::info('EditBook: Page mounted', ['record_id' => $this->record->id]);
    }

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        Log::info('EditBook: Save method called');
        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }

    public function removeRecipe($recipeId)
    {
        try {
            $book = $this->record;
            $recipe = \App\Models\Recipe::findOrFail($recipeId);

            // Remove the recipe from the book using the Book model's method
            $book->removeRecipe($recipeId);

            // Update status if not 'Warten auf Versand'
            if ($book->status !== 'Warten auf Versand') {
                $book->status = 'Geändert nach Versand';
                $book->save();
                $book->refresh();
                $this->dispatch('bookStatusUpdated', id: $book->id, status: $book->status);
            }

            // Notify success
            \Filament\Notifications\Notification::make()
                ->title(__('Rezept entfernt'))
                ->body("Das Rezept '{$recipe->title}' wurde aus dem Buch entfernt.")
                ->success()
                ->send();

            // Optionally emit an event or update state here for Livewire UI update
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error removing recipe from book', [
                'book_id' => $this->record->id,
                'recipe_id' => $recipeId,
                'error' => $e->getMessage(),
            ]);

            // Notify error
            \Filament\Notifications\Notification::make()
                ->title(__('Fehler'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addRecipe($recipeId)
    {
        try {
            $book = $this->record;
            $recipe = \App\Models\Recipe::findOrFail($recipeId);

            // Get the recipe's course
            $categories = is_string($recipe->category ?? null) ? json_decode($recipe->category, true) : (is_array($recipe->category ?? null) ? $recipe->category : []);
            $primaryCategory = \App\Filament\Resources\BookResource::getPrimaryCategory($categories);
            $course = \App\Filament\Resources\BookResource::mapCategoryToCourse($primaryCategory);

            // Get recipe limits
            $recipeLimits = $book->getRecipesPerCourse();

            // Count current recipes in this course
            $currentCount = $book->recipes()
                ->where(function($query) use ($categories) {
                    foreach ($categories as $category) {
                        $query->orWhere('category', 'like', '%"' . $category . '"%');
                    }
                })
                ->count();

            // Add recipe with limit checking
            if (!$book->addRecipeWithLimitCheck($recipeId)) {
                return;
            }

            // Notify success
            \Filament\Notifications\Notification::make()
                ->title(__('Rezept hinzugefügt'))
                ->body("Das Rezept '{$recipe->title}' wurde zum Buch hinzugefügt.")
                ->success()
                ->send();

            // Optionally emit an event or update state here for Livewire UI update
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error adding recipe to book', [
                'book_id' => $this->record->id,
                'recipe_id' => $recipeId,
                'error' => $e->getMessage(),
            ]);

            // Notify error
            \Filament\Notifications\Notification::make()
                ->title(__('Fehler'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addToFavorites($recipeId)
    {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            $settings = $user->settings ?? [];
            $favorites = $settings['favorites'] ?? [];

            if (!in_array($recipeId, $favorites)) {
                $favorites[] = $recipeId;
                $settings['favorites'] = $favorites;
                $user->settings = $settings;
                $user->save();
            }

            \Filament\Notifications\Notification::make()
                ->title(__('Zu Favoriten hinzugefügt'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Error adding recipe to favorites', [
                'recipe_id' => $recipeId,
                'error' => $e->getMessage(),
            ]);

            \Filament\Notifications\Notification::make()
                ->title(__('Fehler'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function removeFromFavorites($recipeId)
    {
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            $settings = $user->settings ?? [];
            $favorites = $settings['favorites'] ?? [];

            $favorites = array_filter($favorites, fn($id) => $id !== $recipeId);
            $settings['favorites'] = array_values($favorites);
            $user->settings = $settings;
            $user->save();

            \Filament\Notifications\Notification::make()
                ->title(__('Aus Favoriten entfernt'))
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Error removing recipe from favorites', [
                'recipe_id' => $recipeId,
                'error' => $e->getMessage(),
            ]);

            \Filament\Notifications\Notification::make()
                ->title(__('Fehler'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        Log::info('EditBook: Rendering header actions', ['book_id' => $this->record->id]);
        return [
            Actions\Action::make('download_pdf')
                ->label('Rezeptbuch als PDF herunterladen')
                ->url(fn () => route('book.pdf', $this->record))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->disabled(fn () => $this->record->recipes()->count() === 0),
            Actions\Action::make('email_pdf')
                ->label('PDF per E-Mail senden')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->disabled(fn () => $this->record->recipes()->count() === 0)
                ->form([
                    \Filament\Forms\Components\Select::make('recipient')
                        ->label('Empfänger')
                        ->options(function () {
                            $options = [];
                            $user = \Illuminate\Support\Facades\Auth::user();
                            if ($this->record->patient && $this->record->patient->email) {
                                $options['patient'] = "Patient: {$this->record->patient->email}";
                            }
                            if ($user instanceof \App\Models\User && ((method_exists($user, 'isAdmin') && $user->isAdmin()) || (method_exists($user, 'isLab') && $user->isLab()))) {
                                if ($this->record->patient && $this->record->patient->lab && $this->record->patient->lab->email) {
                                    $options['lab'] = "Labor: {$this->record->patient->lab->email}";
                                }
                            }
                            return $options;
                        })
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function ($set, $get) {
                            $template = \App\Models\TextTemplate::find($get('template_id'));
                            $lang = $get('language') ?? 'de';
                            $book = $this->record;
                            $patient = $book->patient ?? null;
                            $lab = $patient && $patient->lab ? $patient->lab : null;
                            $editLink = url("/filament/resources/books/{$book->id}/edit");
                            $patientName = $patient ? $patient->name : '';
                            $labName = $lab ? $lab->name : '';
                            $vars = [
                                'book' => $book,
                                'patient' => $patient,
                                'lab' => $lab,
                                'edit_link' => $editLink,
                                'record' => $book,
                                'name' => $patientName,
                                'lab_name' => $labName,
                            ];
                            $subject = $template ? $template->getSubjectForLocaleWithVars($lang, $vars) : '';
                            $body = $template ? $template->getBodyForLocale($lang, $vars) : '';
                            $set('subject', $subject);
                            $set('body', $body);
                        }),
                    \Filament\Forms\Components\Select::make('template_id')
                        ->label('Textvorlage')
                        ->options(function () {
                            $user = \Illuminate\Support\Facades\Auth::user();
                            $labId = $this->record->patient && $this->record->patient->lab ? $this->record->patient->lab->id : null;
                            $templates = \App\Models\TextTemplate::where('type', 'book_send_email')
                                ->where(function($q) use ($labId) {
                                    $q->where('user_id', $labId)->orWhereNull('user_id');
                                })
                                ->get();
                            $options = [];
                            foreach ($templates as $template) {
                                $label = $template->getSubjectForLocale('de') ?: 'Vorlage #' . $template->id;

                                // Apply variable replacement to template label
                                if ($label && $this->record) {
                                    $book = $this->record;
                                    $patient = $book->patient ?? null;
                                    $lab = $patient && $patient->lab ? $patient->lab : null;
                                    $editLink = url("/filament/resources/books/{$book->id}/edit");
                                    $patientName = $patient ? $patient->name : '';
                                    $labName = $lab ? $lab->name : '';
                                    $vars = [
                                        'book' => $book,
                                        'patient' => $patient,
                                        'lab' => $lab,
                                        'edit_link' => $editLink,
                                        'record' => $book,
                                        'name' => $patientName,
                                        'lab_name' => $labName,
                                    ];

                                    $label = $template->getSubjectForLocaleWithVars('de', $vars);
                                }

                                $options[$template->id] = $label;
                            }
                            return $options;
                        })
                        ->reactive()
                        ->required()
                        ->visible(fn ($get) => filled($get('recipient')))
                        ->default(function () {
                            $user = \Illuminate\Support\Facades\Auth::user();
                            $labId = $this->record->patient && $this->record->patient->lab ? $this->record->patient->lab->id : null;
                            $template = \App\Models\TextTemplate::where('type', 'book_send_email')
                                ->where(function($q) use ($labId) {
                                    $q->where('user_id', $labId)->orWhereNull('user_id');
                                })
                                ->first();
                            return $template ? $template->id : null;
                        })
                        ->afterStateUpdated(function ($set, $get) {
                            $template = \App\Models\TextTemplate::find($get('template_id'));
                            $lang = $get('language') ?? 'de';
                            $book = $this->record;
                            $patient = $book->patient ?? null;
                            $lab = $patient && $patient->lab ? $patient->lab : null;
                            $editLink = url("/filament/resources/books/{$book->id}/edit");
                            $patientName = $patient ? $patient->name : '';
                            $labName = $lab ? $lab->name : '';
                            $vars = [
                                'book' => $book,
                                'patient' => $patient,
                                'lab' => $lab,
                                'edit_link' => $editLink,
                                'record' => $book,
                                'name' => $patientName,
                                'lab_name' => $labName,
                            ];
                            $subject = $template ? $template->getSubjectForLocaleWithVars($lang, $vars) : '';
                            $body = $template ? $template->getBodyForLocale($lang, $vars) : '';
                            $set('subject', $subject);
                            $set('body', $body);
                        }),
                    \Filament\Forms\Components\Select::make('language')
                        ->label('Sprache')
                        ->options(function ($get) {
                            $template = \App\Models\TextTemplate::find($get('template_id'));
                            if (!$template) return ['de' => 'Deutsch', 'en' => 'English'];
                            $langs = array_unique(array_merge(
                                array_keys($template->subject ?? []),
                                array_keys($template->body ?? [])
                            ));
                            $labels = ['de' => 'Deutsch', 'en' => 'English'];
                            $out = [];
                            foreach ($langs as $lang) {
                                $out[$lang] = $labels[$lang] ?? $lang;
                            }
                            return $out;
                        })
                        ->default(function () {
                            $bookLocale = $this->record->patient && isset($this->record->patient->settings['language'])
                                ? $this->record->patient->settings['language']
                                : (\Illuminate\Support\Facades\Auth::user() && isset(\Illuminate\Support\Facades\Auth::user()->settings['language'])
                                    ? \Illuminate\Support\Facades\Auth::user()->settings['language']
                                    : 'de');
                            return $bookLocale;
                        })
                        ->reactive()
                        ->required()
                        ->visible(fn ($get) => filled($get('recipient')))
                        ->afterStateUpdated(function ($set, $get) {
                            $template = \App\Models\TextTemplate::find($get('template_id'));
                            $lang = $get('language') ?? 'de';
                            $book = $this->record;
                            $patient = $book->patient ?? null;
                            $lab = $patient && $patient->lab ? $patient->lab : null;
                            $editLink = url("/filament/resources/books/{$book->id}/edit");
                            $patientName = $patient ? $patient->name : '';
                            $labName = $lab ? $lab->name : '';
                            $vars = [
                                'book' => $book,
                                'patient' => $patient,
                                'lab' => $lab,
                                'edit_link' => $editLink,
                                'record' => $book,
                                'name' => $patientName,
                                'lab_name' => $labName,
                            ];
                            $subject = $template ? $template->getSubjectForLocaleWithVars($lang, $vars) : '';
                            $body = $template ? $template->getBodyForLocale($lang, $vars) : '';
                            $set('subject', $subject);
                            $set('body', $body);
                        }),
                    \Filament\Forms\Components\TextInput::make('subject')
                        ->label('Betreff')
                        ->reactive()
                        ->afterStateUpdated(function ($set, $get) {
                            $set('preview_refresh', now());
                        })
                        ->afterStateHydrated(function ($component, $state, $record, $set, $get) {
                            $template = \App\Models\TextTemplate::find($get('template_id'));
                            $lang = $get('language') ?? 'de';
                            $subject = $template ? ($template->subject[$lang] ?? '') : '';
                            // Interpolate placeholders before setting
                            $book = $this->record;
                            $patient = $book->patient ?? null;
                            $lab = $patient && $patient->lab ? $patient->lab : null;
                            $editLink = url("/filament/resources/books/{$book->id}/edit");
                            $patientName = $patient ? $patient->name : '';
                            $labName = $lab ? $lab->name : '';
                            $vars = [
                                'book' => $book,
                                'patient' => $patient,
                                'lab' => $lab,
                                'edit_link' => $editLink,
                                'record' => $book,
                                'name' => $patientName,
                                'lab_name' => $labName,
                            ];
                            $set('subject', $subject);
                        })
                        ->visible(fn ($get) => filled($get('recipient'))),
                    \Filament\Forms\Components\RichEditor::make('body')
                        ->label('Text')
                        ->reactive()
                        ->afterStateUpdated(function ($set, $get) {
                            $set('preview_refresh', now());
                        })
                        ->afterStateHydrated(function ($component, $state, $record, $set, $get) {
                            $template = \App\Models\TextTemplate::find($get('template_id'));
                            $lang = $get('language') ?? 'de';
                            $body = $template ? ($template->body[$lang] ?? '') : '';
                            // Interpolate placeholders before setting
                            $book = $this->record;
                            $patient = $book->patient ?? null;
                            $lab = $patient && $patient->lab ? $patient->lab : null;
                            $editLink = url("/filament/resources/books/{$book->id}/edit");
                            $patientName = $patient ? $patient->name : '';
                            $labName = $lab ? $lab->name : '';
                            $vars = [
                                'book' => $book,
                                'patient' => $patient,
                                'lab' => $lab,
                                'edit_link' => $editLink,
                                'record' => $book,
                                'name' => $patientName,
                                'lab_name' => $labName,
                            ];
                            $set('body', $body);
                        })
                        ->visible(fn ($get) => filled($get('recipient'))),
                    \Filament\Forms\Components\Hidden::make('preview_refresh'),
                    \Filament\Forms\Components\Placeholder::make('preview')
                        ->label('Vorschau')
                        ->content(function ($get) {
                            $book = $this->record;
                            $patient = $book->patient ?? null;
                            $lab = $patient && $patient->lab ? $patient->lab : null;
                            $editLink = url("/filament/resources/books/{$book->id}/edit");
                            $patientName = $patient ? $patient->name : '';
                            $labName = $lab ? $lab->name : '';
                            $vars = [
                                'book' => $book,
                                'patient' => $patient,
                                'lab' => $lab,
                                'edit_link' => $editLink,
                                'record' => $book,
                                'name' => $patientName,
                                'lab_name' => $labName,
                            ];

                            // Get template and language from form data
                            $template = \App\Models\TextTemplate::find($get('template_id'));
                            $lang = $get('language') ?? 'de';
                            $subject = $template ? $template->getSubjectForLocaleWithVars($lang, $vars) : '';
                            $body = $template ? $template->getBodyForLocale($lang, $vars) : '';
                            return new \Illuminate\Support\HtmlString('<div><div><b>Betreff:</b> ' . e($subject) . '</div><div style="margin-top:10px;"><b>Text:</b><br>' . $body . '</div></div>');
                        })
                        ->columnSpanFull()
                        ->visible(fn ($get) => filled($get('recipient')))
                        ->extraAttributes(['style' => 'min-height: 120px;', 'class' => 'filament-html-preview']),
                    \Filament\Forms\Components\Actions::make([
                        \Filament\Forms\Components\Actions\Action::make('save_template')
                            ->label('Textvorlage speichern')
                            ->color('secondary')
                            ->disabled(),
                    ])->columnSpanFull()
                        ->visible(fn ($get) => filled($get('recipient'))),
                ])
                ->action(function (array $data) {
                    $recipient = $data['recipient'];
                    $email = '';
                    $name = '';
                    if ($recipient === 'patient') {
                        $email = $this->record->patient->email;
                        $name = $this->record->patient->name;
                    } elseif ($recipient === 'lab' && $this->record->patient->lab) {
                        $email = $this->record->patient->lab->email;
                        $name = $this->record->patient->lab->name;
                    }
                    if (!$email) {
                        \Filament\Notifications\Notification::make()
                            ->title(__('Fehler'))
                            ->body(__('Keine gültige E-Mail-Adresse gefunden.'))
                            ->danger()
                            ->send();
                        return;
                    }

                    // Find the sample code more robustly
                    $analysis = $this->record->analysis;
                    if (!$analysis && $this->record->patient) {
                        $analysis = \App\Models\Analysis::where('patient_id', $this->record->patient->id)->latest()->first();
                    }
                    $sampleCode = $analysis?->sample_code;
                    $pdfFileName = $sampleCode ? ($sampleCode . '_RB.pdf') : "book-{$this->record->id}-rezepte.pdf";
                    $pdfPath = "books/" . $pdfFileName;

                    $pdfDir = dirname($pdfPath);
                    $storagePath = \Illuminate\Support\Facades\Storage::path($pdfDir);
                    if (!file_exists($storagePath)) {
                        mkdir($storagePath, 0775, true);
                    }
                    \Spatie\LaravelPdf\Facades\Pdf::view('pdf.book', [
                        'book' => $this->record,
                        'recipes' => $this->record->recipes()->get()
                    ])
                    ->format('a4')
                    ->name($pdfFileName)
                    ->withBrowsershot(function (\Spatie\Browsershot\Browsershot $browsershot) {
                        $browsershot->noSandbox();
                    })
                    ->save(\Illuminate\Support\Facades\Storage::path($pdfPath));
                    $editLink = url("/filament/resources/books/{$this->record->id}/edit");
                    $patientName = $this->record->patient ? $this->record->patient->name : '';
                    $labName = $this->record->patient && $this->record->patient->lab ? $this->record->patient->lab->name : '';
                    $book = $this->record;
                    $vars = [
                        'book' => $book,
                        'patient' => $book->patient ?? null,
                        'lab' => $book->patient && $book->patient->lab ? $book->patient->lab : null,
                        'edit_link' => $editLink,
                        'record' => $book,
                        'name' => $patientName,
                        'lab_name' => $labName,
                    ];

                    // Get template and language from form data
                    $template = \App\Models\TextTemplate::find($data['template_id'] ?? null);
                    $lang = $data['language'] ?? 'de';
                    $subject = $template ? $template->getSubjectForLocaleWithVars($lang, $vars) : '';
                    $body = $template ? $template->getBodyForLocale($lang, $vars) : '';
                    // Remove all line breaks and extra spaces from subject, force string
                    $subject = preg_replace('/[\r\n]+/', ' ', (string)$subject);
                    $subject = trim(preg_replace('/\s+/', ' ', $subject));
                    // Note: Subject encoding is handled by the mailer and is required for non-ASCII chars (RFC compliance)
                    // Ensure body is not HTML-escaped
                    if ($body instanceof \Illuminate\Support\HtmlString) {
                        $body = $body->toHtml();
                    }
                    \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($email, $name, $subject, $body, $pdfPath, $pdfFileName) {
                        $message->to($email, $name)
                            ->from(config('mail.from.address'), config('mail.from.name'))
                            ->subject($subject)
                            ->html($body)
                            ->attach(\Illuminate\Support\Facades\Storage::path($pdfPath), [
                                'as' => $pdfFileName,
                                'mime' => 'application/pdf',
                            ]);
                    });
                    \Illuminate\Support\Facades\Storage::delete($pdfPath);
                    if ($this->record instanceof \App\Models\Book) {
                        $this->record->status = 'Versendet';
                        $this->record->save();
                        $this->record->refresh();
                        $this->dispatch('bookStatusUpdated', id: $this->record->id, status: $this->record->status);
                    }
                    \Filament\Notifications\Notification::make()
                        ->title(__('E-Mail gesendet'))
                        ->body(__('Das Rezeptbuch wurde an :email gesendet.', ['email' => $email]))
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return __('Rezeptbuch bearbeiten');
    }

    public function getView(): string
    {
        return 'filament.resources.book-resource.pages.edit-book';
    }
}
