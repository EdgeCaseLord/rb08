<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;

class CreateBook extends CreateRecord
{
    protected static string $resource = BookResource::class;

    protected function afterCreate(): void
    {
        Log::info('CreateBook: After create triggered', ['book_id' => $this->record->id]);
        // No recipe syncing here; handled by user actions
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Ensure status is set to "Warten auf Versand"
        $data['status'] = 'Warten auf Versand';

        // Get the latest analysis for the patient
        if (isset($data['patient_id'])) {
            $latestAnalysis = \App\Models\Analysis::where('patient_id', $data['patient_id'])
                ->latest()
                ->first();
            if ($latestAnalysis) {
                $data['analysis_id'] = $latestAnalysis->id;
            }
        }

        return $data;
    }
}
