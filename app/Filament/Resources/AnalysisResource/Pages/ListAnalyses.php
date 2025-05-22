<?php

namespace App\Filament\Resources\AnalysisResource\Pages;

use App\Filament\Resources\AnalysisResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListAnalyses extends ListRecords
{
    protected static string $resource = AnalysisResource::class;

    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()->with('analysisAllergens.allergen');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
