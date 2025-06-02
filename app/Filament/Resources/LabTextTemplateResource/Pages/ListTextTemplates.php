<?php

namespace App\Filament\Resources\TextTemplateResource\Pages;

use App\Filament\Resources\TextTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTextTemplates extends ListRecords
{
    protected static string $resource = TextTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    public function getTitle(): string
    {
        return __('Text templates');
    }
}
