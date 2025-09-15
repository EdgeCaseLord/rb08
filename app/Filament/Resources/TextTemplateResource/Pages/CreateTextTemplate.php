<?php

namespace App\Filament\Resources\TextTemplateResource\Pages;

use App\Filament\Resources\TextTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;

class CreateTextTemplate extends CreateRecord
{
    protected static string $resource = TextTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Log::debug('Before Resource mutateFormDataBeforeCreate', $data);
        // Always update the hidden fields for the currently selected language before merging
        $lang = $data['language'] ?? 'de';
        if ($lang === 'de') {
            $data['subject_de'] = $data['subject_by_language'] ?? ($data['subject_de'] ?? '');
            $data['body_de'] = $data['body_by_language'] ?? ($data['body_de'] ?? '');
        } elseif ($lang === 'en') {
            $data['subject_en'] = $data['subject_by_language'] ?? ($data['subject_en'] ?? '');
            $data['body_en'] = $data['body_by_language'] ?? ($data['body_en'] ?? '');
        }
        $data['subject'] = [
            'de' => $data['subject_de'] ?? '',
            'en' => $data['subject_en'] ?? '',
        ];
        $data['body'] = [
            'de' => $data['body_de'] ?? '',
            'en' => $data['body_en'] ?? '',
        ];
        $data = \App\Filament\Resources\TextTemplateResource::mutateFormDataBeforeCreate($data);
        Log::debug('After Resource mutateFormDataBeforeCreate', $data);
        return $data;
    }

    protected function getFormActions(): array
    {
        // Only return valid Action or ActionGroup instances
        return [
            ...parent::getFormActions(),
        ];
    }
}
