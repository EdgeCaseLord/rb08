<?php

namespace App\Filament\Widgets;

use App\Models\Analysis;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;

class LatestAnalysesTable extends TableWidget
{
    protected static ?string $heading = 'Neueste Analysen';

    protected int | string | array $columnSpan = [
        'default' => 4,
        'sm' => 4,
        'md' => 3,
        'lg' => 3,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Analysis::query()
                    ->with('analysisAllergens.allergen')
                    ->latest()
                    ->limit(5)
            )
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
                    ->toggleable(isToggledHiddenByDefault: true),
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
                                            ->inlineLabel(),
                                        TextEntry::make('allergen.code')
                                            ->label(__('Code'))
                                            ->inlineLabel(),
                                        TextEntry::make('calibrated_value')
                                            ->label(__('Calibrated Value'))
                                            ->inlineLabel()
                                            ->formatStateUsing(fn ($state) => number_format($state, 2)),
                                        TextEntry::make('signal_noise')
                                            ->label(__('Signal Noise'))
                                            ->inlineLabel()
                                            ->formatStateUsing(fn ($state) => number_format($state, 2)),
                                    ])
                                    ->columns(1),
                            ])
                            ->collapsible(),
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalWidth('5xl'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
?>
