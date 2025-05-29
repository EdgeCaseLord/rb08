<?php

namespace App\Filament\Resources\AnalysisResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class AllergensRelationManager extends RelationManager
{
    protected static string $relationship = 'analysisAllergens';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('allergen.name')
                    ->label(__('Allergen Name'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('allergen.code')
                    ->label(__('Code'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('calibrated_value')
                    ->label(__('Calibrated Value'))
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 2)),
                TextColumn::make('signal_noise')
                    ->label(__('Signal Noise'))
                    ->sortable()
                    ->formatStateUsing(fn ($state) => number_format($state, 2)),
            ]);
    }

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Allergens');
    }
}
