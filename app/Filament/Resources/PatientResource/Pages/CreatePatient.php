<?php

namespace App\Filament\Resources\PatientResource\Pages;

use App\Filament\Resources\PatientResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreatePatient extends CreateRecord
{
    protected static string $resource = PatientResource::class;

    public function getTitle(): string
    {
        return 'Patient anlegen';
    }

    protected function authorizeAccess(): void
    {
        if (!auth()->check() || (!auth()->user()->isAdmin() && !auth()->user()->isLab())) {
            abort(403, 'Unauthorized');
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function beforeCreate(): void
    {
        if (!$this->data['doctor_id']) {
            throw new \Exception(__('The doctor field is required.'));
        }
        if (!$this->data['lab_id']) {
            throw new \Exception('Labor ist erforderlich.');
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->label(__('Save')),
        ];
    }

    protected function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();
        if ($user instanceof \App\Models\User && $user->isLab() && empty($data['lab_id'])) {
            $data['lab_id'] = $user->id;
        }
        return static::getModel()::create($data);
    }
}
