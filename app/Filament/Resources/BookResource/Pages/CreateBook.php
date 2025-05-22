<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateBook extends CreateRecord
{
    protected static string $resource = BookResource::class;

    protected function afterCreate(): void
    {
        Log::info('CreateBook: After create triggered', ['book_id' => $this->record->id]);
        // No recipe syncing here; handled by user actions
    }
}
