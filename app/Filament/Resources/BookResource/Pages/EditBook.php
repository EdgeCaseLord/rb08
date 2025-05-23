<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use App\Models\UserSettings;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Filament\Actions;

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

            // Notify success
            \Filament\Notifications\Notification::make()
                ->title('Rezept entfernt')
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
                ->title('Fehler')
                ->body('Das Rezept konnte nicht entfernt werden: ' . $e->getMessage())
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

            // Check if limit is reached BEFORE trying to add
            if ($currentCount >= ($recipeLimits[$course] ?? PHP_INT_MAX)) {
                \Filament\Notifications\Notification::make()
                    ->title('Rezeptlimit erreicht')
                    ->body("Maximale Rezepteanzahl für Gang {$course} erreicht!")
                    ->warning()
                    ->send();
                return;
            }

            // Only add the recipe if we haven't hit the limit
            $book->addRecipe($recipeId);

            // Notify success
            \Filament\Notifications\Notification::make()
                ->title('Rezept hinzugefügt')
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
                ->title('Fehler')
                ->body('Das Rezept konnte nicht hinzugefügt werden: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addToFavorites($recipeId)
    {
        try {
            $user = Auth::user();
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
                ->title('Zu Favoriten hinzugefügt')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Error adding recipe to favorites', [
                'recipe_id' => $recipeId,
                'error' => $e->getMessage(),
            ]);

            \Filament\Notifications\Notification::make()
                ->title('Fehler')
                ->body('Das Rezept konnte nicht zu den Favoriten hinzugefügt werden: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function removeFromFavorites($recipeId)
    {
        try {
            $user = Auth::user();
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
                ->title('Aus Favoriten entfernt')
                ->success()
                ->send();
        } catch (\Exception $e) {
            Log::error('Error removing recipe from favorites', [
                'recipe_id' => $recipeId,
                'error' => $e->getMessage(),
            ]);

            \Filament\Notifications\Notification::make()
                ->title('Fehler')
                ->body('Das Rezept konnte nicht aus den Favoriten entfernt werden: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        Log::info('EditBook: Rendering header actions', ['book_id' => $this->record->id]);
        return [
            Actions\Action::make('download_pdf')
                ->label('Rezepte als PDF herunterladen')
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
                            $user = auth()->user();

                            if ($this->record->patient && $this->record->patient->email) {
                                $options['patient'] = "Patient: {$this->record->patient->email}";
                            }

                            if ($user->isAdmin() || $user->isLab()) {
                                if ($this->record->patient && $this->record->patient->lab && $this->record->patient->lab->email) {
                                    $options['lab'] = "Labor: {$this->record->patient->lab->email}";
                                }
                            }

                            return $options;
                        })
                        ->required(),
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
                            ->title('Fehler')
                            ->body('Keine gültige E-Mail-Adresse gefunden.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $pdfPath = "books/book-{$this->record->id}-rezepte.pdf";
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
                    ->name("buch-{$this->record->id}-rezepte.pdf")
                    ->withBrowsershot(function (\Spatie\Browsershot\Browsershot $browsershot) {
                        $browsershot->noSandbox();
                    })
                    ->save(\Illuminate\Support\Facades\Storage::path($pdfPath));

                    \Illuminate\Support\Facades\Mail::send([], [], function ($message) use ($email, $name) {
                        $message->to($email, $name)
                            ->subject("Ihr Rezeptbuch: {$this->record->title}")
                            ->html("Sehr geehrte(r) {$name},<br><br>anbei finden Sie Ihr Rezeptbuch als PDF-Datei.<br><br>Mit freundlichen Grüßen")
                            ->attach(\Illuminate\Support\Facades\Storage::path("books/book-{$this->record->id}-rezepte.pdf"), [
                                'as' => "buch-{$this->record->id}-rezepte.pdf",
                                'mime' => 'application/pdf',
                            ]);
                    });

                    \Illuminate\Support\Facades\Storage::delete($pdfPath);

                    \Filament\Notifications\Notification::make()
                        ->title('E-Mail gesendet')
                        ->body("Das Rezeptbuch wurde an {$email} gesendet.")
                        ->success()
                        ->send();
                }),
            Actions\DeleteAction::make(),
        ];
    }

    public function getView(): string
    {
        return 'filament.resources.book-resource.pages.edit-book';
    }
}
