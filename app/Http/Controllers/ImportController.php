<?php

namespace App\Http\Controllers;

use App\Filament\Imports\AnalysisImporter;
use Filament\Actions\Imports\ImportAction;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImportController extends Controller
{
    public function import(Request $request)
    {
        // Validate the uploaded file
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt',
        ]);

        try {
            // Store the uploaded CSV
            $filePath = $request->file('csv')->store('imports');

            // Create a Filament import record
            $import = \Filament\Actions\Imports\Models\Import::create([
                'user_id' => auth()->id(),
                'file_path' => $filePath,
                'importer' => AnalysisImporter::class,
                'status' => 'in_progress',
            ]);

            // Initialize and run the importer
            $importer = new AnalysisImporter($import);
            $importer->import($request->file('csv'));

            // Mark import as complete
            $import->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
            Log::info('Import marked as completed', ['import_id' => $import->id]);

            Notification::make()
                ->title('Import Successful')
                ->body('Analysis import processed successfully.')
                ->success()
                ->send();

            return redirect()->back()->with('success', 'Import processed successfully');
        } catch (\Exception $e) {
            Log::error('Import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            Notification::make()
                ->title('Import Failed')
                ->body('Failed to process import: ' . $e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return redirect()->back()->with('error', 'Failed to process import: ' . $e->getMessage());
        }
    }
}
