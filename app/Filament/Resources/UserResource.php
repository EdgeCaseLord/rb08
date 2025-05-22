<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?int $navigationSort = 6;
    protected static ?string $navigationLabel = 'Benutzerverwaltung';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required(),
                Forms\Components\TextInput::make('avatar')
                    ->required(),
                Forms\Components\DateTimePicker::make('email_verified_at'),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->required(),
                Forms\Components\TextInput::make('username'),
                Forms\Components\DateTimePicker::make('trial_ends_at'),
                Forms\Components\TextInput::make('verification_code'),
                Forms\Components\TextInput::make('verified')
                    ->numeric(),
                Forms\Components\TextInput::make('phone')
                    ->tel(),
                Forms\Components\TextInput::make('address1'),
                Forms\Components\TextInput::make('address2'),
                Forms\Components\TextInput::make('zip'),
                Forms\Components\TextInput::make('city'),
                Forms\Components\TextInput::make('country'),
                Forms\Components\TextInput::make('state'),
                Forms\Components\TextInput::make('language'),
                Forms\Components\TextInput::make('timezone'),
                Forms\Components\TextInput::make('currency'),
                Forms\Components\TextInput::make('stripe_id'),
                Forms\Components\TextInput::make('card_brand'),
                Forms\Components\TextInput::make('card_last_four'),
                Forms\Components\TextInput::make('threshold')
                    ->label('Threshold (Grenzwert)')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->default(100.0)
                    ->visible(fn ($record) => $record && $record->isLab())
                    ->helperText('Threshold for allergen positivity, only applicable to lab users.'),

                Forms\Components\Select::make('role')
                    ->options(function () {
                        $user = auth()->user(); // Get the currently authenticated user.

                        // Define roles dynamically based on the current user's role.
                        if ($user->isAdmin()) {
                            // Admin users can assign Admin, Lab, Doctor, and Patient roles.
                            return [
                                'admin' => 'Admin',
                                'lab' => 'Lab',
                                'doctor' => 'Doctor',
                                'patient' => 'Patient',
                            ];
                        } elseif ($user->isLab()) {
                            // Lab users can assign Doctor and Patient roles.
                            return [
                                'doctor' => 'Doctor',
                                'patient' => 'Patient',
                            ];
                        } elseif ($user->isDoctor()) {
                            // Doctors can only assign the Patient role.
                            return [
                                'patient' => 'Patient',
                            ];
                        }

                        // Patients can't edit this field; return no options.
                        return [];
                    })
                    ->required()
                    ->label('Role')
                    ->disabled(function () {
                        $user = auth()->user();

                        // Disable the field for patients to prevent editing.
                        return $user->isPatient();
                    }),

                Forms\Components\Select::make('lab_id')
                    ->label('Laboratory')
                    ->options(function () {
                        // Fetch all users with the 'lab' role to populate the Select options.
                        return \App\Models\User::labs()->pluck('name', 'id');
                    })
                    ->searchable()
                    ->nullable() // Allow this to be null if not mandatory.
                    ->required(false) // Required if Admin or Lab is filling this field
                    ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isLab()), // Labs and Admins can assign this field.

                Forms\Components\Select::make('doctor_id')
                    ->label('Doctor')
                    ->options(function () {
                        // Fetch all users with the 'doctor' role to populate the Select options.
                        return \App\Models\User::doctors()->pluck('name', 'id');
                    })
                    ->searchable()
                    ->nullable()
                    ->required(false) // Required for Admins and Labs.
                    ->visible(fn () => auth()->user()->isAdmin() || auth()->user()->isLab()), // Only visible for Admins and Labs.
                Forms\Components\TextInput::make('patient_code'),
                Forms\Components\DatePicker::make('birthdate'),
                Forms\Components\CheckboxList::make('settings.diet_preferences')
                ->label(__('Diät-Präferenzen'))
                ->options([
                    'biologisch' => __('Biologisch'),
                    'eifrei' => __('Eifrei'),
                    'glutenfrei' => __('Glutenfrei'),
                    'histamin-free' => __('Histaminfrei'),
                    'laktosefrei' => __('Laktosefrei'),
                    'ohne Fisch' => __('Ohne Fisch'),
                    'ohne Fleisch' => __('Ohne Fleisch'),
                    'sojafrei' => __('Sojafrei'),
                    'vegan' => __('Vegan'),
                    'vegetarisch' => __('Vegetarisch'),
                    'weizenfrei' => __('Weizenfrei'),
                ])
                ->columns(2)
                ->helperText(__('Alle zutreffenden Präferenzen auswählen. Diese werden als positive Filter verwendet.')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('avatar')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('username')
                    ->searchable(),
                Tables\Columns\TextColumn::make('trial_ends_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('verification_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('verified')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address1')
                    ->searchable(),
                Tables\Columns\TextColumn::make('address2')
                    ->searchable(),
                Tables\Columns\TextColumn::make('zip')
                    ->searchable(),
                Tables\Columns\TextColumn::make('city')
                    ->searchable(),
                Tables\Columns\TextColumn::make('country')
                    ->searchable(),
                Tables\Columns\TextColumn::make('state')
                    ->searchable(),
                Tables\Columns\TextColumn::make('language')
                    ->searchable(),
                Tables\Columns\TextColumn::make('timezone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('currency')
                    ->searchable(),
                Tables\Columns\TextColumn::make('stripe_id')
                    ->searchable(),
                Tables\Columns\TextColumn::make('card_brand')
                    ->searchable(),
                Tables\Columns\TextColumn::make('card_last_four')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->searchable(),
                Tables\Columns\TextColumn::make('lab_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('threshold')
                    ->visible(fn () => auth()->user()->canEditLabSettings())
                    ->default('n/a'),
                Tables\Columns\TextColumn::make('doctor_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('patient_code')
                    ->searchable(),
                Tables\Columns\TextColumn::make('birthdate')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'lab' => 'Lab',
                        'doctor' => 'Doctor',
                        'patient' => 'Patient',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()->isAdmin();
    }
}
