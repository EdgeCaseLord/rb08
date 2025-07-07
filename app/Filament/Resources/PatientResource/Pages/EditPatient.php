<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\PatientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Jobs\CreateBookJob;
use App\Models\Book;
use Filament\Notifications\Notification;

class EditPatient extends EditRecord
{
    protected static string $resource = PatientResource::class;

    public $updateBookWithFilters = false;
    public $filterTitle = '';
    public $filterIngredients = '';
    public $filterAllergen = [];
    public $filterCategory = [];
    public $filterCountry = [];
    public $filterCourse = [];
    public $filterDiets = [];
    public $filterDifficulty = [];
    public $filterMaxTime = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getFilterPreferences(): array
    {
        $record = $this->getRecord();
        if ($record && isset($record->settings['recipe_filter_set'])) {
            return $record->settings['recipe_filter_set'];
        }
        return [];
    }

    public function updated($property, $value)
    {
        if ($property === 'updateBookWithFilters') {
            $this->updateBookWithFilters = $value;
        }
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Remove the checkbox from the saved settings
        unset($data['settings']['recipe_filter_set']['updateBookWithFilters']);
        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->updateBookWithFilters) {
            $patient = $this->getRecord();
            $latestBook = Book::where('patient_id', $patient->id)->latest()->first();
            if ($latestBook) {
                $filters = $this->getFilterPreferences();
                CreateBookJob::dispatch($patient, null, $latestBook->id, $filters);
                Notification::make()->title('Das Buch wird mit den aktuellen Filtern aktualisiert.')->success()->send();
            }
            $this->updateBookWithFilters = false;
        }
    }

    public function mount($record): void
    {
        parent::mount($record);
        $prefs = $this->getFilterPreferences();
        foreach ([
            'filterTitle', 'filterIngredients', 'filterAllergen', 'filterCategory', 'filterCountry', 'filterCourse', 'filterDiets', 'filterDifficulty', 'filterMaxTime'
        ] as $key) {
            if (array_key_exists($key, $prefs)) {
                if (in_array($key, ['filterAllergen','filterCategory','filterCountry','filterCourse','filterDiets','filterDifficulty','filterMaxTime']) && is_array($prefs[$key]) && array_values($prefs[$key]) === $prefs[$key]) {
                    $this->$key = array_fill_keys($prefs[$key], true);
                } else {
                    $this->$key = $prefs[$key];
                }
            }
        }
    }

    public function save(bool $shouldRedirect = false, bool $shouldSendSavedNotification = true): void
    {
        $record = $this->getRecord();
        $settings = $record->settings ?? [];
        $settings['recipe_filter_set'] = [
            'filterTitle' => $this->filterTitle,
            'filterIngredients' => $this->filterIngredients,
            'filterAllergen' => array_keys(array_filter($this->filterAllergen)),
            'filterCategory' => array_keys(array_filter($this->filterCategory)),
            'filterCountry' => array_keys(array_filter($this->filterCountry)),
            'filterCourse' => array_keys(array_filter($this->filterCourse)),
            'filterDiets' => array_keys(array_filter($this->filterDiets)),
            'filterDifficulty' => array_keys(array_filter($this->filterDifficulty)),
            'filterMaxTime' => array_keys(array_filter($this->filterMaxTime)),
        ];
        $record->settings = $settings;
        $record->save();
        Notification::make()->title('Filter gespeichert')->success()->send();
        if ($this->updateBookWithFilters) {
            $latestBook = Book::where('patient_id', $record->id)->latest()->first();
            if ($latestBook) {
                CreateBookJob::dispatch($record, null, $latestBook->id, $settings['recipe_filter_set']);
                Notification::make()->title('Das Buch wird mit den aktuellen Filtern aktualisiert.')->success()->send();
            }
            $this->updateBookWithFilters = false;
        }
        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }
}
