<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnalysisResource\Pages;
use App\Filament\Resources\AnalysisResource\RelationManagers;
use App\Jobs\ImportCsv;
use App\Filament\Imports\AnalysisImporter;
use App\Jobs\AssignRecipesJob;
use App\Models\Analysis;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ImportAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;

class AnalysisResource extends Resource
{
    protected static ?string $model = Analysis::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document';

    protected static ?int $navigationSort = 2;

    public static function getModelLabel(): string { return 'Analyse'; }
    public static function getPluralModelLabel(): string { return 'Analysen'; }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('qr_code')
                    ->label(__('QR Code')),
                Forms\Components\TextInput::make('sample_code')
                    ->label(__('Sample Code'))
                    ->required(),
                Forms\Components\DatePicker::make('sample_date')
                    ->label(__('Sample Date')),
                Forms\Components\TextInput::make('patient_code')
                    ->label(__('Patient Code')),
                Forms\Components\TextInput::make('patient_name')
                    ->label(__('Patient Name')),
                Forms\Components\DatePicker::make('patient_date_of_birth')
                    ->label(__('Patient Date of Birth')),
                Forms\Components\DatePicker::make('assay_date')
                    ->label(__('Assay Date')),
                Forms\Components\DatePicker::make('test_date')
                    ->label(__('Test Date')),
                Forms\Components\TextInput::make('test_by')
                    ->label(__('Test By')),
                Forms\Components\DatePicker::make('approval_date')
                    ->label(__('Approval Date')),
                Forms\Components\TextInput::make('approval_by')
                    ->label(__('Approval By')),
                Forms\Components\Textarea::make('additional_information')
                    ->label(__('Additional Information'))
                    ->columnSpanFull(),
//                Forms\Components\TextInput::make('antigen_id')
//                    ->label(__('Antigen ID')),
//                Forms\Components\TextInput::make('antigen_name')
//                    ->label(__('Antigen Name')),
//                Forms\Components\TextInput::make('code')
//                    ->label(__('Code')),
//                Forms\Components\TextInput::make('calibrated_value')
//                    ->label(__('Calibrated Value'))
//                    ->numeric(),
//                Forms\Components\TextInput::make('signal_noise')
//                    ->label(__('Signal Noise'))
//                    ->numeric(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('qr_code')
                    ->label(__('QR Code'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('sample_code')
                    ->label(__('Sample Code'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('sample_date')
                    ->label(__('Sample Date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('patient_code')
                    ->label(__('Patient Code'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('patient_name')
                    ->label(__('Patient Name'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('patient_date_of_birth')
                    ->label(__('Patient Date of Birth'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('assay_date')
                    ->label(__('Assay Date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('test_date')
                    ->label(__('Test Date'))
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('test_by')
                    ->label(__('Test By'))
                    ->searchable()
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
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
//                Tables\Columns\TextColumn::make('antigen_id')
//                    ->label(__('Antigen ID'))
//                    ->searchable()
//                    ->toggleable(isToggledHiddenByDefault: false),
//                Tables\Columns\TextColumn::make('antigen_name')
//                    ->label(__('Antigen Name'))
//                    ->searchable()
//                    ->toggleable(isToggledHiddenByDefault: false),
//                Tables\Columns\TextColumn::make('code')
//                    ->label(__('Code'))
//                    ->searchable()
//                    ->toggleable(isToggledHiddenByDefault: false),
//                Tables\Columns\TextColumn::make('calibrated_value')
//                    ->label(__('Calibrated Value'))
//                    ->numeric()
//                    ->sortable()
//                    ->toggleable(isToggledHiddenByDefault: false),
//                Tables\Columns\TextColumn::make('signal_noise')
//                    ->label(__('Signal Noise'))
//                    ->numeric()
//                    ->sortable()
//                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Updated At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('view_allergens')
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
                // Tables\Actions\EditAction::make(),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(AnalysisImporter::class)
                    ->options(['updateExisting' => true]) // Optional: Set default to true
                    ->label(__('Import Analyses (CSV)'))
                    ->color('primary'),
//                Tables\Actions\CreateAction::make()
//                    ->label(__('Create Analysis'))
//                    ->icon('heroicon-o-plus')
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->poll('5s');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAnalyses::route('/'),
            'create' => Pages\CreateAnalysis::route('/create'),
            'edit' => Pages\EditAnalysis::route('/{record}/edit'),
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        Log::info('Record creation started', $data);
        return parent::handleRecordCreation($data);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Log::info('Mutating data before create', ['data' => $data]);
        return $this->processAnalysisData($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Log::info('Mutating data before save', ['data' => $data]);
        return $this->processAnalysisData($data);
    }

    protected function processAnalysisData(array $data): array
    {
        Log::info('Processing analysis data', ['data' => $data]);

        if (empty($data['sample_code']) || empty($data['patient_code'])) {
            throw new \Exception('Sample code and patient code are required.');
        }

        return $data;
    }

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()->isAdmin() || auth()->user()->isLab();
    }
}
?>
