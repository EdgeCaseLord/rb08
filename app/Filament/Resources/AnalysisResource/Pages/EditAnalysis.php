<?php

namespace App\Filament\Resources\AnalysisResource\Pages;

use App\Filament\Resources\AnalysisResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAnalysis extends EditRecord
{
    protected static string $resource = AnalysisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
