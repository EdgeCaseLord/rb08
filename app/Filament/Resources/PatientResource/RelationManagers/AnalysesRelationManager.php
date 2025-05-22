<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use App\Filament\Imports\AnalysisImporter;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class AnalysesRelationManager extends RelationManager
{
    protected static string $relationship = 'analyses';

    protected static ?string $title = 'Analysen';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sample_code')
            ->columns([
                Tables\Columns\TextColumn::make('sample_code')
                    ->label(__('Sample Code'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('patient_code')
                    ->label(__('Patient Code'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('patient_name')
                    ->label(__('Patient Name'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('sample_date')
                    ->label(__('Sample Date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('assay_date')
                    ->label(__('Assay Date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('approval_date')
                    ->label(__('Approval Date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('approval_by')
                    ->label(__('Approval By'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('analysis_allergens_count')
                    ->label(__('Allergen Count'))
                    ->counts('analysisAllergens')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([])
            ->headerActions([
                // Tables\Actions\ImportAction::make()
                //     ->importer(AnalysisImporter::class)
                //     ->label(__('Import Analyses (CSV)')),
            ])
            ->actions([
                Tables\Actions\Action::make('view_allergens')
                    ->label(__('View Allergens'))
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn ($record) => __('Allergens for Analysis: :sample_code', ['sample_code' => $record->sample_code]))
                    ->infolist([
                        Section::make(__('Allergen Details'))
                            ->schema([
                                RepeatableEntry::make('analysisAllergens')
                                    ->label(__('Allergens'))
                                    ->schema([
                                        TextEntry::make('allergen.name')
                                            ->label(__('Allergen Name'))
                                            ->inlineLabel()
                                            ->weight('bold'),
                                        TextEntry::make('allergen.code')
                                            ->label(__('Code'))
                                            ->inlineLabel()
                                            ->color('gray'),
                                        TextEntry::make('calibrated_value')
                                            ->label(__('Calibrated Value'))
                                            ->inlineLabel()
                                            ->formatStateUsing(fn ($state) => number_format($state, 2))
                                            ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                                        TextEntry::make('signal_noise')
                                            ->label(__('Signal Noise'))
                                            ->inlineLabel()
                                            ->formatStateUsing(fn ($state) => number_format($state, 2))
                                            ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),
                                    ])
                                    ->columns(1),
                            ])
                            ->collapsible(),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalWidth('5xl'),
                Tables\Actions\DeleteAction::make()
                    ->label(__('Delete'))
                    ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isLab()),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->label(__('Delete Analyses'))
                    ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isLab()),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('analysisAllergens.allergen'));
    }
}
?>
