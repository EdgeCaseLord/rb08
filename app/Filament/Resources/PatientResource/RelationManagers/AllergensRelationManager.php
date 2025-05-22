<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AllergensRelationManager extends RelationManager
{
    protected static string $relationship = 'allergens';

    protected static ?string $title = 'Allergens'; // For Filament's relation manager tab

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Allergens');
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('Name'))
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Name'))
                    ->formatStateUsing(function ($record) {
                        return app()->getLocale() === 'de' && $record->name_de
                            ? $record->name_de
                            : $record->name;
                    })
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('code')
                    ->label(__('Allergen Code'))
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('calibrated_value')
                    ->label(__('Calibrated Value'))
                    ->formatStateUsing(fn ($state) => $state ? number_format($state, 2) : '-')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([])
            ->headerActions([
                // Tables\Actions\AttachAction::make()
                //     ->label(__('Attach Allergen')),
            ])
            ->actions([
                // No actions needed as import script handles everything
            ])
            ->bulkActions([
                // No bulk actions needed as import script handles everything
            ])
            ->modifyQueryUsing(function ($query) {
                return $query
                    ->select([
                        'allergens.*',
                        'latest_analysis.calibrated_value as calibrated_value'
                    ])
                    ->whereHas('users', function ($query) {
                        $query->where('users.id', $this->getOwnerRecord()->id);
                    })
                    ->leftJoin('analysis_allergens as latest_analysis', function ($join) {
                        $join->on('allergens.id', '=', 'latest_analysis.allergen_id')
                            ->whereRaw('latest_analysis.id = (
                                SELECT id FROM analysis_allergens
                                WHERE allergen_id = allergens.id
                                ORDER BY created_at DESC
                                LIMIT 1
                            )');
                    });
            });
    }
}
