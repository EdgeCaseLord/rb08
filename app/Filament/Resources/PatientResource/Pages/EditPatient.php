<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\PatientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Jobs\CreateBookJob;
use App\Models\Book;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EditPatient extends EditRecord
{
    protected static string $resource = PatientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\Action::make('updateBookWithFilters')
                ->label('Buch mit aktuellem Filter-Set aktualisieren')
                ->action(function () {
                    $record = $this->getRecord();
                    $filterSet = $record->settings['recipe_filter_set'] ?? [];
                    $latestBook = \App\Models\Book::where('patient_id', $record->id)->latest()->first();
                    if ($latestBook) {
                        \App\Jobs\CreateBookJob::dispatch($record, null, $latestBook->id, $filterSet);
                        \Filament\Notifications\Notification::make()->title(__('Buch wird aktualisiert'))->success()->send();
                    } else {
                        \Filament\Notifications\Notification::make()->title(__('Kein Buch gefunden'))->warning()->send();
                    }
                }),
        ];
    }

    public function mount($record): void
    {
        parent::mount($record);
        // After parent::mount(), $this->record is the model instance
        if (is_string($this->record->settings)) {
            $this->record->settings = json_decode($this->record->settings, true);
        }
    }

    public function onFilterSave(Request $request): void
    {
        Log::info('onFilterSave: request input', $request->all());
        $record = $this->getRecord();
        $settings = $record->settings ?? [];
        $filterSet = [
            'filterTitle' => $request->input('filterTitle', ''),
            'filterIngredients' => $request->input('filterIngredients', ''),
            'filterAllergen' => $request->has('filterAllergen') ? $request->input('filterAllergen', []) : [],
            'filterCategory' => $request->input('filterCategory', []),
            'filterCountry' => $request->input('filterCountry', []),
            'filterCourse' => $request->input('filterCourse', []),
            'filterDiets' => $request->has('filterDiets') ? $request->input('filterDiets', []) : [],
            'filterDifficulty' => $request->input('filterDifficulty', []),
            'filterMaxTime' => $request->input('filterMaxTime', []),
            'filterOffset' => $request->input('filterOffset', 0),
            'filterRandomizeOffset' => $request->has('filterRandomizeOffset'),
            'filterSubstances' => $request->input('filterSubstances', []),
            'updateBookWithFilters' => $request->has('updateBookWithFilters'),
        ];
        Log::info('onFilterSave: filterSet', $filterSet);
        $settings['recipe_filter_set'] = $filterSet;
        Log::info('onFilterSave: settings before save', $settings);
        $record->settings = $settings;
        $record->save();
        if ($filterSet['updateBookWithFilters']) {
            $latestBook = Book::where('patient_id', $record->id)->latest()->first();
            if ($latestBook) {
                CreateBookJob::dispatch($record, null, $latestBook->id, $filterSet);
            }
        }
        Notification::make()->title(__('Filter gespeichert'))->success()->send();
        redirect()->to(request()->url());
    }
}
