<?php

namespace App\Console\Commands;

use App\Jobs\AssignRecipesJob;
use Filament\Actions\Imports\Models\Import;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class HandleFailedImports extends Command
{
    protected $signature = 'imports:handle-failed';
    protected $description = 'Process imports with failed rows after manual correction';

    public function handle()
    {
        $failedImports = Import::where('failed_rows', '>', 0)
            ->where('created_at', '<', now()->subHour())
            ->get();

        foreach ($failedImports as $import) {
            Log::info('Processing failed import', [
                'import_id' => $import->id,
                'failed_rows' => $import->failed_rows,
                'successful_rows' => $import->successful_rows,
            ]);

            if ($import->successful_rows > 0) {
                $patients = User::where('role', 'patient')
                    ->whereHas('analyses', function ($query) use ($import) {
                        $query->where('is_csv', true)->where('import_id', $import->id);
                    })
                    ->get();

                if ($patients->isNotEmpty()) {
                    try {
                        $job = new AssignRecipesJob($patients->all());
                        $job->handle();
                        Log::info('AssignRecipesJob executed for failed import', [
                            'import_id' => $import->id,
                            'patient_count' => $patients->count(),
                        ]);

                        $import->failed_rows = 0;
                        $import->save();
                        Log::info('Import marked as processed', ['import_id' => $import->id]);
                    } catch (\Exception $e) {
                        Log::error('Failed to process failed import', [
                            'import_id' => $import->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    Log::warning('No patients found for failed import', ['import_id' => $import->id]);
                }
            } else {
                Log::warning('No successful rows in failed import, manual correction needed', ['import_id' => $import->id]);
            }
        }
    }
}
