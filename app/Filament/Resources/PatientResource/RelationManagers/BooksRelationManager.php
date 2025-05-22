<?php

namespace App\Filament\Resources\PatientResource\RelationManagers;

use App\Filament\Resources\BookResource;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class BooksRelationManager extends RelationManager
{
    protected static string $relationship = 'books';

    protected static ?string $title = 'Books';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Books');
    }
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('Title'))
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('recipes')
                    ->label(__('Recipes'))
                    ->getStateUsing(function ($record) {
                        $recipeTitles = $record->recipes()->pluck('title')->toArray();
                        return implode(', ', $recipeTitles) ?: __('No Recipes');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Create Book'))
                    ->url(fn () => BookResource::getUrl('create', ['patient_id' => $this->getOwnerRecord()->id])),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label(__('Edit'))
                    ->color('primary')
                    ->url(fn ($record) => BookResource::getUrl('edit', ['record' => $record->id]))
                    ->authorize(fn ($record) => BookResource::canEdit($record))
                    ->after(function () {
                        Log::info('BooksRelationManager: EditAction triggered');
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label(__('Delete'))
                    ->color('danger')
                    ->after(function () {
                        Log::info('BooksRelationManager: DeleteAction triggered');
                    }),
            ])
            ->recordAction('edit')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->label(__('Delete Books')),
                ]),
            ]);
    }

    public static function canViewForRecord($ownerRecord, $user): bool
    {
        $user = auth()->user();
        $canView = $user && ($user->isAdmin() || ($user->isLab() && $user->id === $ownerRecord->lab_id));
        Log::info('BooksRelationManager canViewForRecord', [
            'user_id' => $user?->id,
            'role' => $user?->role,
            'isAdmin' => $user?->isAdmin(),
            'isLab' => $user?->isLab(),
            'patient_id' => $ownerRecord->id,
            'lab_id' => $ownerRecord->lab_id,
            'canView' => $canView,
        ]);
        return $canView;
    }

    protected function canCreate(): bool
    {
        $user = auth()->user();
        $ownerRecord = $this->getOwnerRecord();
        $canCreate = $user && ($user->isAdmin() || ($user->isLab() && $user->id === $ownerRecord->lab_id));
        Log::info('BooksRelationManager canCreate', [
            'user_id' => $user?->id,
            'role' => $user?->role,
            'isAdmin' => $user?->isAdmin(),
            'isLab' => $user?->isLab(),
            'patient_id' => $ownerRecord->id,
            'lab_id' => $ownerRecord->lab_id,
            'canCreate' => $canCreate,
        ]);
        return $canCreate;
    }

    protected function canEdit($record): bool
    {
        $user = auth()->user();
        $ownerRecord = $this->getOwnerRecord();
        $canEdit = $user && ($user->isAdmin() || ($user->isLab() && $user->id === $ownerRecord->lab_id));
        Log::info('BooksRelationManager canEdit', [
            'user_id' => $user?->id,
            'role' => $user?->role,
            'isAdmin' => $user?->isAdmin(),
            'isLab' => $user?->isLab(),
            'patient_id' => $ownerRecord->id,
            'lab_id' => $ownerRecord->lab_id,
            'book_id' => $record->id,
            'canEdit' => $canEdit,
        ]);
        return $canEdit;
    }

    protected function canDelete($record): bool
    {
        $user = auth()->user();
        $ownerRecord = $this->getOwnerRecord();
        $canDelete = $user && ($user->isAdmin() || ($user->isLab() && $user->id === $ownerRecord->lab_id));
        Log::info('BooksRelationManager canDelete', [
            'user_id' => $user?->id,
            'role' => $user?->role,
            'isAdmin' => $user?->isAdmin(),
            'isLab' => $user?->isLab(),
            'patient_id' => $ownerRecord->id,
            'lab_id' => $ownerRecord->lab_id,
            'book_id' => $record->id,
            'canDelete' => $canDelete,
        ]);
        return $canDelete;
    }
}
