<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\EditAction;
use Illuminate\Support\Facades\Log;

class ListBooks extends ListRecords
{
    protected static string $resource = BookResource::class;

    protected function getTableActions(): array
    {
        Log::info('ListBooks: Getting table actions');
        return [
            EditAction::make()
                ->before(function () {
                    Log::info('EditAction: Before hook triggered');
                }),
        ];
    }

    protected function getHeaderActions(): array
    {
        Log::info('ListBooks: Getting header actions');
        return parent::getHeaderActions();
    }
}
