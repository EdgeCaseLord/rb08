<?php

namespace App\Filament\Imports;

use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class TestImporter extends Importer
{
    protected static ?string $model = null;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('sample_code')->guess(['SampleCode', 'sample_code']),
        ];
    }

    public function beforeImport(Import $import): void
    {
        Log::info('TestImporter beforeImport called', [
            'import_id' => $import->id,
            'import_rows' => $import->getCsvRowsCount(),
            'file_path' => $import->file_path,
        ]);
    }

    public function resolveRecord(): ?Model
    {
        try {
            Log::info('TestImporter resolveRecord called', ['data' => $this->data]);
            return null;
        } catch (\Exception $e) {
            Log::error('Error in TestImporter resolveRecord', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $this->data,
            ]);
            throw $e;
        }
    }

    public function afterImport(Import $import): void
    {
        Log::info('TestImporter afterImport called', [
            'import_id' => $import->id,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $import->getFailedRowsCount(),
            'import_status' => $import->status,
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Test import completed with ' . number_format($import->successful_rows) . ' rows imported.';
        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' rows failed to import.';
        }
        return $body;
    }
}
