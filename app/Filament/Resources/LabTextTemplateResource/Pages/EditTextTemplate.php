<?php

namespace App\Filament\Resources\TextTemplateResource\Pages;

use App\Filament\Resources\TextTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;

class EditTextTemplate extends EditRecord
{
    protected static string $resource = TextTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        Log::debug('Before Resource mutateFormDataBeforeSave', $data);
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
        $data = \App\Filament\Resources\TextTemplateResource::mutateFormDataBeforeSave($data);
        Log::debug('After Resource mutateFormDataBeforeSave', $data);
        return $data;
    }

    public function getFooter(): ?\Illuminate\Contracts\View\View
    {
        return view('filament.resources.text-template-save-warning');
    }
}
