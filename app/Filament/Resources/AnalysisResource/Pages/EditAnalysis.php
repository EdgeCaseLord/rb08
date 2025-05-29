<?php

namespace App\Filament\Resources\AnalysisResource\Pages;

use App\Filament\Resources\AnalysisResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use App\Models\AnalysisAllergen;

class EditAnalysis extends EditRecord
{
    protected static string $resource = AnalysisResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function getInfolist(string $name = 'infolist'): ?\Filament\Infolists\Infolist
    {
        if ($name !== 'infolist') {
            return null;
        }
        return \Filament\Infolists\Infolist::make($this)
            ->record($this->record)
            ->schema([
                Section::make(__('Allergen Details'))
                    ->schema([
                        RepeatableEntry::make('analysisAllergens')
                            ->label(__('Allergens'))
                            ->schema([
                                TextEntry::make('allergen.name')
                                    ->label(__('Allergen Name')),
                                TextEntry::make('allergen.code')
                                    ->label(__('Code')),
                                TextEntry::make('calibrated_value')
                                    ->label(__('Calibrated Value'))
                                    ->formatStateUsing(fn ($state) => number_format($state, 2)),
                                TextEntry::make('signal_noise')
                                    ->label(__('Signal Noise'))
                                    ->formatStateUsing(fn ($state) => number_format($state, 2)),
                            ])
                            ->columns(1),
                    ])
                    ->collapsible(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AnalysisAllergen::query()->where('analysis_id', $this->record->id)->with('allergen'))
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
            ])
            ->defaultSort('allergen.name');
    }
}
