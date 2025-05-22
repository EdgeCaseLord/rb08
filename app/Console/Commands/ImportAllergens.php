<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Allergen;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

class ImportAllergens extends Command
{
    protected $signature = 'app:import-allergens';
    protected $description = 'Import allergens from a CSV file into the database';

    public function handle()
    {
        DB::beginTransaction();
        try {
            Log::info('Starting allergen import process');
            $filePath = storage_path('app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'export_matching_table.csv');
            Log::debug('Checking for CSV file existence', ['file_path' => $filePath]);

            if (!file_exists($filePath)) {
                $this->error("CSV file not found at: $filePath");
                Log::error('CSV file not found', ['path' => $filePath]);
                DB::rollBack();
                return 1;
            }

            Log::info('CSV file found, attempting to read', ['file_path' => $filePath]);
            try {
                $csvContent = file_get_contents($filePath);
                $csvContent = mb_convert_encoding($csvContent, 'UTF-8', 'auto');
                file_put_contents($filePath, $csvContent);
                $csv = array_map('str_getcsv', file($filePath));
                Log::info('Successfully read CSV file', ['row_count' => count($csv)]);
            } catch (Throwable $e) {
                $this->error("Error reading the CSV file: " . $e->getMessage());
                Log::error('Error reading CSV file', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                DB::rollBack();
                return 1;
            }

            $headers = array_map('trim', array_shift($csv));
            Log::info('CSV headers processed', [
                'header_count' => count($headers),
                'headers' => $headers,
            ]);

            $requiredHeaders = ['allergen_code', 'allergen_name_en'];
            $missingHeaders = array_diff($requiredHeaders, $headers);
            if (!empty($missingHeaders)) {
                $this->error("CSV file is missing required columns: " . implode(', ', $missingHeaders));
                Log::error('Missing required CSV headers', [
                    'missing_headers' => $missingHeaders,
                    'required_headers' => $requiredHeaders,
                ]);
                DB::rollBack();
                return 1;
            }
            Log::debug('All required headers present', ['required_headers' => $requiredHeaders]);

            $rowCount = 0;
            $skippedRows = 0;

            Log::info('Starting to process CSV rows', ['total_rows' => count($csv)]);
            foreach ($csv as $rowIndex => $row) {
                $rowCount++;
                $row = array_pad($row, count($headers), null);
                $row = array_slice($row, 0, count($headers));
                $rowData = array_combine($headers, $row);

                if (empty($rowData['allergen_code']) || empty($rowData['allergen_name_en'])) {
                    $this->warn("Skipping row $rowIndex due to missing 'allergen_code' or 'allergen_name_en': " . json_encode($rowData));
                    Log::warning('Skipping row due to missing required fields', [
                        'row_index' => $rowIndex,
                        'allergen_code' => $rowData['allergen_code'] ?? 'missing',
                        'allergen_name_en' => $rowData['allergen_name_en'] ?? 'missing',
                        'row_data' => $rowData,
                    ]);
                    $skippedRows++;
                    continue;
                }

                try {
                    $allergen = Allergen::firstOrCreate(
                        ['code' => $rowData['allergen_code']],
                        [
                            'name' => $rowData['allergen_name_en'],
                            'name_latin' => isset($rowData['allergen_name_latin']) ? $rowData['allergen_name_latin'] : null,
                        ]
                    );
                    Log::info('Allergen processed', [
                        'row_index' => $rowIndex,
                        'allergen_id' => $allergen->id,
                        'code' => $rowData['allergen_code'],
                        'name' => $rowData['allergen_name_en'],
                        'name_latin' => isset($rowData['allergen_name_latin']) ? $rowData['allergen_name_latin'] : null,
                    ]);
                } catch (Throwable $e) {
                    $this->error("Error processing allergen at row $rowIndex: " . $e->getMessage());
                    Log::error('Error processing allergen', [
                        'row_index' => $rowIndex,
                        'allergen_code' => $rowData['allergen_code'],
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'row_data' => $rowData,
                    ]);
                    continue;
                }
            }

            DB::commit();
            $this->info("Allergen import completed. Rows processed: $rowCount, Rows skipped: $skippedRows");
            Log::info('Allergen import completed', [
                'rows_processed' => $rowCount,
                'rows_skipped' => $skippedRows,
            ]);
            return 0;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error('Unexpected error during allergen import: ' . $e->getMessage());
            Log::error('Unexpected error during allergen import', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 1;
        }
    }
}
