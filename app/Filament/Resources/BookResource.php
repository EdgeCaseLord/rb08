<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Models\Book;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Filament\Notifications\Notification;
use Livewire\Component;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\TextEntry;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';
    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string { return 'Rezeptbuch'; }
    public static function getPluralModelLabel(): string { return 'Bücher'; }
    protected static ?string $navigationLabel = 'Rezeptbücher';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    protected static function getCategoryMapping(): array
    {
        return [
            'Vorspeise' => 'starter',
            'Hauptgericht' => 'main_course',
            'Dessert' => 'dessert'
        ];
    }

    public static function mapCategoryToCourse(string $category): string
    {
        $mapping = self::getCategoryMapping();
        $course = $mapping[$category] ?? 'main_course';

        // Update the recipe's course field if it exists
        if ($recipe = \App\Models\Recipe::where('category', 'like', '%"' . $category . '"%')->first()) {
            $recipe->course = $course;
            $recipe->save();
        }

        return $course;
    }

    public static function getPrimaryCategory(array $categories): string
    {
        // Priority order: Vorspeise > Hauptgericht > Dessert
        $priority = [
            'Vorspeise' => 1,
            'Hauptgericht' => 2,
            'Dessert' => 3
        ];

        // Filter out any categories we don't recognize
        $validCategories = array_filter($categories, function($cat) use ($priority) {
            return isset($priority[$cat]);
        });

        if (empty($validCategories)) {
            return 'Hauptgericht'; // Default if no valid categories
        }

        // Sort by priority and return the highest priority category
        usort($validCategories, function($a, $b) use ($priority) {
            return $priority[$a] <=> $priority[$b];
        });

        return $validCategories[0];
    }

    public static function getAnalysisLink($analysisId)
    {
        if (!$analysisId) return 'Keine Analyse ausgewählt';
        $analysis = \App\Models\Analysis::find($analysisId);
        if (!$analysis) return 'Keine Analyse ausgewählt';
        return new \Illuminate\Support\HtmlString('<a href="' . route('filament.admin.resources.analyses.edit', ['record' => $analysisId]) . '" class="text-primary-600 hover:text-primary-500" target="_blank">' . $analysis->sample_code . '</a>');
    }

    public static function form(Form $form): Form
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $canSeePatientSelect = $user && ($user->isAdmin() || $user->isLab() || $user->isDoctor());

        // Prepare dynamic data for the view components
        $record = $form->getRecord();
        $bookRecipes = $record ? $record->recipes()->get() : collect([]);
        $bookId = $record ? $record->id : null;
        $favorites = collect([]);
        if ($record && $record->patient) {
            $favoriteIds = $record->patient->settings['favorites'] ?? [];
            $favorites = \App\Models\Recipe::whereIn('id_recipe', $favoriteIds)->get();
        }
        $availableRecipes = collect([]);
        if ($record && $record->patient) {
            // Only use $record->recipes() for book recipes
            $bookRecipeIds = $bookRecipes->pluck('id_recipe')->toArray();
            // Fetch all recipes in the system except those already in the book
            $availableRecipes = \App\Models\Recipe::whereNotIn('id_recipe', $bookRecipeIds)->get();
        }

        return $form
            ->schema([
                Forms\Components\Section::make('Buch Details')
                    ->extraAttributes(['class' => 'w-full'])
                    ->collapsible()
                    ->schema([
                        Forms\Components\Group::make()
                            ->extraAttributes(['class' => 'w-full'])
                            ->schema([
                                Forms\Components\Select::make('patient_id')
                                    ->relationship('patient', 'name', function ($query) use ($user) {
                                        $query->where('role', 'patient');
                                        if ($user->isLab()) {
                                            $query->where('lab_id', $user->id);
                                        } elseif ($user->isDoctor()) {
                                            $query->where('doctor_id', $user->id);
                                        }
                                    })
                                    ->label('Patient')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->inlineLabel()
                                    ->columnSpanFull()
                                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->patient_code})")
                                    ->afterStateUpdated(function ($state, Forms\Set $set) {
                                        if ($state) {
                                            $latestAnalysis = \App\Models\Analysis::where('patient_id', $state)
                                                ->latest()
                                                ->first();
                                            if ($latestAnalysis) {
                                                $set('analysis_id', $latestAnalysis->id);
                                            } else {
                                                $set('analysis_id', null);
                                            }
                                        } else {
                                            $set('analysis_id', null);
                                        }
                                    })
                                    ->visible(fn () => $form->getOperation() === 'create' && $user && ($user->isAdmin() || $user->isLab()))
                                    ->default(fn () => request()->get('patient_id')),
                                Forms\Components\Fieldset::make('book_details')
                                    ->label(__('Book Details'))
                                    ->schema([
                                        Forms\Components\TextInput::make('title')
                                            ->label(__('Title'))
                                            ->required()
                                            ->inlineLabel()
                                            ->extraAttributes(['class' => 'w-full'])
                                            ->columnSpanFull(),
                                        Forms\Components\Select::make('status')
                                            ->label(__('Status'))
                                            ->options([
                                                'Versendet' => 'Versendet',
                                                'Warten auf Versand' => 'Warten auf Versand',
                                                'Geändert nach Versand' => 'Geändert nach Versand',
                                            ])
                                            ->default('Warten auf Versand')
                                            ->disabled()
                                            ->dehydrated()
                                            ->required()
                                            ->inlineLabel()
                                            ->columnSpanFull(),
                                        Forms\Components\Placeholder::make('analysis')
                                            ->label('Analyse')
                                            ->content(function ($record, $get) {
                                                if ($record) {
                                                    return static::getAnalysisLink($record->analysis_id);
                                                }

                                                $patientId = $get('patient_id');
                                                if ($patientId) {
                                                    $latestAnalysis = \App\Models\Analysis::where('patient_id', $patientId)
                                                        ->latest()
                                                        ->first();
                                                    if ($latestAnalysis) {
                                                        return static::getAnalysisLink($latestAnalysis->id);
                                                    }
                                                }

                                                return 'Keine Analyse ausgewählt';
                                            })
                                            ->live()
                                            ->inlineLabel()
                                            ->columnSpanFull(),
                                        Forms\Components\Actions::make([
                                            Forms\Components\Actions\Action::make('save')
                                                ->label('Speichern')
                                                ->submit('save')
                                                ->color('primary'),
                                        ])->columnSpanFull(),
                                    ])
                                    ->columnSpanFull()
                                    ->extraAttributes(['class' => 'flex flex-col gap-4 w-full']),
                            ])
                            ->columns(2),
                    ]),
                Forms\Components\Section::make(__('Rezepte im Buch'))
                    ->collapsible()
                    ->extraAttributes(['class' => 'max-h-[60vh] overflow-y-auto'])
                    ->schema([
                        Forms\Components\View::make('livewire.book-recipes-table')->viewData(['bookId' => $bookId]),
                    ]),
                Forms\Components\Section::make(__('Favoriten'))
                    ->collapsible()
                    ->extraAttributes(['class' => 'max-h-[60vh] overflow-y-auto'])
                    ->schema([
                        Forms\Components\View::make('livewire.favorite-recipes-table')->viewData(['bookId' => $bookId]),
                    ]),
                Forms\Components\Section::make(__('Verfügbare Rezepte'))
                    ->collapsible()
                    ->extraAttributes(['class' => 'max-h-[60vh] overflow-y-auto'])
                    ->schema([
                        Forms\Components\View::make('livewire.available-recipes-table')->viewData(['bookId' => $bookId]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titel')
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('patient.name')
                    ->label('Patient')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('recipes')
                    ->label('Rezepte')
                    ->getStateUsing(function (Book $record) {
                        $recipeTitles = $record->recipes()->pluck('title')->toArray();
                        Log::info('Table recipes fetched', [
                            'book_id' => $record->id,
                            'recipe_titles' => $recipeTitles,
                        ]);
                        return implode(', ', $recipeTitles) ?: 'Keine Rezepte';
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('analysis.sample_code')
                    ->label('Analyse (Sample Code)')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'primary' => 'Warten auf Versand',
                        'success' => 'Versendet',
                        'warning' => 'Geändert nach Versand',
                    ])
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Erstellt am')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc') // Sort by created_at in descending order
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->before(function () {
                        Log::info('CreateAction: Before hook triggered');
                    })
                    ->after(function ($record, array $data) {
                        Log::info('CreateAction: After hook triggered', [
                            'book_id' => $record->id,
                            'form_data' => $data,
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('email_pdf')
                    ->label('PDF per E-Mail senden')
                    ->icon('heroicon-o-envelope')
                    ->color('primary')
                    ->disabled(fn ($record) => $record->recipes()->count() === 0)
                    ->form([
                        \Filament\Forms\Components\Select::make('recipient')
                            ->label('Empfänger')
                            ->options(function ($record) {
                                $options = [];
                                /** @var \App\Models\User|null $user */
                                $user = Auth::user();

                                // Add patient email if available
                                if ($record->patient && $record->patient->email) {
                                    $options['patient'] = "Patient: {$record->patient->email}";
                                }

                                // Add lab email if user is admin or lab
                                if ($user->isAdmin() || $user->isLab()) {
                                    if ($record->patient && $record->patient->lab && $record->patient->lab->email) {
                                        $options['lab'] = "Labor: {$record->patient->lab->email}";
                                    }
                                }

                                return $options;
                            })
                            ->required(),
                    ])
                    ->action(function ($record, array $data) {
                        $recipient = $data['recipient'];
                        $email = '';
                        $name = '';

                        if ($recipient === 'patient') {
                            $email = $record->patient->email;
                            $name = $record->patient->name;
                        } elseif ($recipient === 'lab' && $record->patient->lab) {
                            $email = $record->patient->lab->email;
                            $name = $record->patient->lab->name;
                        }

                        if (!$email) {
                            \Filament\Notifications\Notification::make()
                                ->title('Fehler')
                                ->body('Keine gültige E-Mail-Adresse gefunden.')
                                ->danger()
                                ->send();
                            return;
                        }

                        // Generate PDF
                        $pdfPath = "books/book-{$record->id}-rezepte.pdf";
                        \Spatie\LaravelPdf\Facades\Pdf::view('pdf.book', [
                            'book' => $record,
                            'recipes' => $record->recipes()->get()
                        ])
                        ->format('a4')
                        ->name("buch-{$record->id}-rezepte.pdf")
                        ->withBrowsershot(function (\Spatie\Browsershot\Browsershot $browsershot) {
                            $browsershot->noSandbox();
                        })
                        ->save(\Illuminate\Support\Facades\Storage::path($pdfPath));

                        // Send email
                        \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($email, $name, $record, $pdfPath) {
                            $message->to($email, $name)
                                ->subject("Ihr Rezeptbuch: {$record->title}")
                                ->html("Sehr geehrte(r) {$name},<br><br>anbei finden Sie Ihr Rezeptbuch als PDF-Datei.<br><br>Mit freundlichen Grüßen")
                                ->attach(\Illuminate\Support\Facades\Storage::path($pdfPath), [
                                    'as' => "buch-{$record->id}-rezepte.pdf",
                                    'mime' => 'application/pdf',
                                ]);
                        });

                        // Delete PDF after sending
                        \Illuminate\Support\Facades\Storage::delete($pdfPath);

                        // Update book status to 'Versendet' and save
                        $record->status = 'Versendet';
                        $record->save();

                        \Filament\Notifications\Notification::make()
                            ->title('E-Mail gesendet')
                            ->body("Das Rezeptbuch wurde an {$email} gesendet.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            Log::info('Bulk DeleteAction triggered', ['book_ids' => $records->pluck('id')->toArray()]);
                            $records->each->delete();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $query = parent::getEloquentQuery();

        if ($user->isLab()) {
            $query->whereHas('patient', function ($q) use ($user) {
                $q->where('lab_id', $user->id);
            });
        } elseif ($user->isDoctor()) {
            $query->whereHas('patient', function ($q) use ($user) {
                $q->where('doctor_id', $user->id);
            });
        } elseif ($user->isPatient()) {
            $query->where('patient_id', $user->id);
        }

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }

    public static function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('download_pdf')
                ->label('PDF herunterladen')
                ->url(fn ($record) => route('book.pdf', $record))
                ->icon('heroicon-o-document-download')
                ->color('success')
                ->disabled(fn ($record) => $record->recipes()->count() === 0),
            \Filament\Actions\Action::make('email_pdf')
                ->label('PDF per E-Mail senden')
                ->icon('heroicon-o-envelope')
                ->color('primary')
                ->disabled(fn ($record) => $record->recipes()->count() === 0)
                ->form([
                    \Filament\Forms\Components\Select::make('recipient')
                        ->label('Empfänger')
                        ->options(function ($record) {
                            $options = [];
                            /** @var \App\Models\User|null $user */
                            $user = Auth::user();

                            // Add patient email if available
                            if ($record->patient && $record->patient->email) {
                                $options['patient'] = "Patient: {$record->patient->email}";
                            }

                            // Add lab email if user is admin or lab
                            if ($user->isAdmin() || $user->isLab()) {
                                if ($record->patient && $record->patient->lab && $record->patient->lab->email) {
                                    $options['lab'] = "Labor: {$record->patient->lab->email}";
                                }
                            }

                            return $options;
                        })
                        ->required(),
                ])
                ->action(function ($record, array $data) {
                    $recipient = $data['recipient'];
                    $email = '';
                    $name = '';

                    if ($recipient === 'patient') {
                        $email = $record->patient->email;
                        $name = $record->patient->name;
                    } elseif ($recipient === 'lab' && $record->patient->lab) {
                        $email = $record->patient->lab->email;
                        $name = $record->patient->lab->name;
                    }

                    if (!$email) {
                        \Filament\Notifications\Notification::make()
                            ->title('Fehler')
                            ->body('Keine gültige E-Mail-Adresse gefunden.')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Generate PDF
                    $pdfPath = "books/book-{$record->id}-rezepte.pdf";
                    \Spatie\LaravelPdf\Facades\Pdf::view('pdf.book', [
                        'book' => $record,
                        'recipes' => $record->recipes()->get()
                    ])
                    ->format('a4')
                    ->name("buch-{$record->id}-rezepte.pdf")
                    ->withBrowsershot(function (\Spatie\Browsershot\Browsershot $browsershot) {
                        $browsershot->noSandbox();
                    })
                    ->save(\Illuminate\Support\Facades\Storage::path($pdfPath));

                    // Send email
                    \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($email, $name, $record, $pdfPath) {
                        $message->to($email, $name)
                            ->subject("Ihr Rezeptbuch: {$record->title}")
                            ->html("Sehr geehrte(r) {$name},<br><br>anbei finden Sie Ihr Rezeptbuch als PDF-Datei.<br><br>Mit freundlichen Grüßen")
                            ->attach(\Illuminate\Support\Facades\Storage::path($pdfPath), [
                                'as' => "buch-{$record->id}-rezepte.pdf",
                                'mime' => 'application/pdf',
                            ]);
                    });
                    // Log only the filename, not the file content or path
                    \Illuminate\Support\Facades\Log::info('Email sent with attachment', [
                        'filename' => "buch-{$record->id}-rezepte.pdf",
                    ]);

                    // Update book status to 'Versendet' and save
                    $record->status = 'Versendet';
                    $record->save();

                    \Filament\Notifications\Notification::make()
                        ->title('E-Mail gesendet')
                        ->body("Das Rezeptbuch wurde an {$email} gesendet.")
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function getAvailableRecipes($record)
    {
        $patientId = $record->patient_id;
        if (!$patientId) {
            Log::warning('No patient ID found for book', ['book_id' => $record->id]);
            return collect([]);
        }
        $patient = User::find($patientId);
        if (!$patient) {
            Log::warning('Patient not found for book', ['book_id' => $record->id, 'patient_id' => $patientId]);
            return collect([]);
        }

        Log::info('Available recipes before filtering', [
            'patient_id' => $patientId,
            'recipe_count' => $patient->recipes()->count(),
            'recipe_ids' => $patient->recipes()->pluck('id_recipe')->toArray(),
        ]);

        $bookRecipes = $record->recipes()->pluck('id_recipe')->toArray();
        Log::info('Book recipes for exclusion', [
            'book_id' => $record->id,
            'book_recipe_ids' => $bookRecipes,
        ]);

        $query = $patient->recipes();
        $recipes = $query->whereNotIn('id_recipe', $bookRecipes)->get();

        Log::info('Available recipes after filtering', [
            'patient_id' => $patientId,
            'available_recipe_count' => $recipes->count(),
            'available_recipe_ids' => $recipes->pluck('id_recipe')->toArray(),
        ]);

        // Validate recipes
        $validRecipes = $recipes->filter(function ($recipe) {
            $isValid = isset($recipe->id_recipe) && is_int($recipe->id_recipe);
            if (!$isValid) {
                Log::error('Invalid recipe in available recipes', [
                    'recipe' => $recipe ? (method_exists($recipe, 'toArray') ? $recipe->toArray() : (array) $recipe) : null,
                ]);
            }
            return $isValid;
        });

        return $validRecipes;
    }

    // Authorization
    public static function canViewAny(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return $user !== null;
    }

    public static function canCreate(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return $user !== null;
    }

    public static function canView($record): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user) return false;

        // Admin can view all books
        if ($user->isAdmin()) return true;

        // Get the patient associated with the book
        $patient = $record->patient;
        if (!$patient) return false;

        // Lab users can only view books of their patients
        if ($user->isLab()) {
            return $patient->lab_id === $user->id;
        }

        // Doctor users can only view books of their patients
        if ($user->isDoctor()) {
            return $patient->doctor_id === $user->id;
        }

        // Patient users can only view their own books
        if ($user->isPatient()) {
            return $record->patient_id === $user->id;
        }

        return false;
    }

    public static function canEdit($record): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user) return false;

        // Admin can edit all books
        if ($user->isAdmin()) return true;

        // Get the patient associated with the book
        $patient = $record->patient;
        if (!$patient) return false;

        // Lab users can only edit books of their patients
        if ($user->isLab()) {
            return $patient->lab_id === $user->id;
        }

        // Doctor users can only edit books of their patients
        if ($user->isDoctor()) {
            return $patient->doctor_id === $user->id;
        }

        // Patient users can only edit their own books
        if ($user->isPatient()) {
            return $record->patient_id === $user->id;
        }

        return false;
    }

    public static function canDelete($record): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        if (!$user) return false;

        // Admin can delete all books
        if ($user->isAdmin()) return true;

        // Get the patient associated with the book
        $patient = $record->patient;
        if (!$patient) return false;

        // Lab users can only delete books of their patients
        if ($user->isLab()) {
            return $patient->lab_id === $user->id;
        }

        // Doctor users can only delete books of their patients
        if ($user->isDoctor()) {
            return $patient->doctor_id === $user->id;
        }

        // Patient users can only delete their own books
        if ($user->isPatient()) {
            return $record->patient_id === $user->id;
        }

        return false;
    }
}
