<?php

namespace App\Filament\Imports;

use App\Jobs\AssignRecipesJob;
use App\Models\Analysis;
use App\Models\Allergen;
use App\Models\User;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Facades\Filament;

class AnalysisImporter extends Importer
{
    protected static ?string $model = Analysis::class;

    public array $patients = [];
    public array $sampleCodes = [];
    public array $rows = [];
    protected float $threshold = 10; // Default threshold
    protected static int $skippedRows = 0; // Make it static

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('qr_code')->guess(['qr_code', 'QR-Code']),
            ImportColumn::make('sample_code')
                ->guess(['sample_code', 'SampleCode'])
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required']),
            ImportColumn::make('sample_date')->guess(['sample_date', 'SampleDate']),
            ImportColumn::make('patient_code')
                ->guess(['patient_code', 'Patient Code', 'Patient_Code'])
                ->requiredMappingForNewRecordsOnly()
                ->rules(['required']),
            ImportColumn::make('patient_name')->guess(['patient_name', 'PatientName', 'Patient_Name']),
            ImportColumn::make('patient_date_of_birth')->guess(['patient_date_of_birth', 'PatientDateOfBirth']),
            ImportColumn::make('assay_date')->guess(['assay_date', 'AssayDate']),
            ImportColumn::make('test_date')->guess(['test_date', 'TestDate']),
            ImportColumn::make('test_by')->guess(['test_by', 'TestBy']),
            ImportColumn::make('approval_date')->guess(['approval_date', 'ApprovalDate']),
            ImportColumn::make('approval_by')->guess(['approval_by', 'ApprovalBy']),
            ImportColumn::make('additional_information')->guess(['additional_information', 'AdditionalInformation']),
            ImportColumn::make('antigen_id')->guess(['antigen_id', 'AntigenID']),
            ImportColumn::make('antigen_name')->guess(['antigen_name', 'AntigenName']),
            ImportColumn::make('code')->guess(['code', 'Code']),
            ImportColumn::make('calibrated_value')
                ->guess(['calibrated_value', 'CalibratedValue'])
                ->castStateUsing(fn($state) => self::cleanCalibratedValue($state)),
            ImportColumn::make('signal_noise')->guess(['signal_noise', 'Signal-Noise'])->numeric(),
            ImportColumn::make('patient_title')->guess(['patient_title', 'PatientTitle']),
            ImportColumn::make('patient_first_name')->guess(['patient_first_name', 'PatientFirstName']),
            ImportColumn::make('doctor_title')->guess(['doctor_title', 'DoctorTitle']),
            ImportColumn::make('doctor_first_name')->guess(['doctor_first_name', 'DoctorFirstName']),
        ];
    }

    public function resolveRecord(): ?Model
    {
        $patientCode = $this->data['patient_code'] ?? null;
        $sampleCode = $this->data['sample_code'] ?? null;

        if (!$patientCode || !$sampleCode) {
            Log::warning('Skipping row due to missing required fields', [
                'patient_code' => $patientCode,
                'sample_code' => $sampleCode,
                'row' => $this->data,
            ]);
            return null;
        }

        // Check if sample code already exists in database, excluding current import
        $existingAnalysis = DB::table('analyses')
            ->where('sample_code', $sampleCode)
            ->where('import_id', '!=', $this->import->id)
            ->first();
        if ($existingAnalysis) {
            self::$skippedRows++; // Use static property
            Log::info('Skipping row due to existing sample code', [
                'sample_code' => $sampleCode,
                'existing_analysis_id' => $existingAnalysis->id,
            ]);
            return null;
        }

        if (!in_array($patientCode, $this->patients)) {
            $this->patients[] = $patientCode;
        }

        if (!isset($this->sampleCodes[$sampleCode])) {
            $analysis = Analysis::with(['patient' => fn($q) => $q->select('id', 'patient_code')])
                ->firstOrNew([
                    'sample_code' => $sampleCode,
                    'import_id' => $this->import->id,
                ]);

            $analysis->is_csv = true;
            $this->sampleCodes[$sampleCode] = $analysis;
        }

        return $this->sampleCodes[$sampleCode];
    }


    protected function afterSave(): void
    {
        $record = $this->record;
        $data = $this->data;

        // Set threshold based on user role
        $user = \Filament\Facades\Filament::auth()->user();
        if ($user && $user->role === 'lab') {
            $this->threshold = $user->settings['allergen_threshold'] ?? 10;
        } elseif ($user && $user->isAdmin()) {
            $firstLab = User::labs()->first();
            $this->threshold = $firstLab ? ($firstLab->settings['allergen_threshold'] ?? 10) : 10;
        }

        Log::debug('After save called', [
            'analysis_id' => $record->id,
            'sample_code' => $record->sample_code,
            'attributes' => $record->toArray(),
            'threshold' => $this->threshold,
        ]);

        // Collect row for debugging and allergen processing
        if (!empty($data['code']) && !empty($data['antigen_name']) && isset($data['calibrated_value'])) {
            $this->rows[] = array_merge($data, ['analysis_id' => $record->id]);
            Log::info('Row collected for allergen processing', [
                'analysis_id' => $record->id,
                'sample_code' => $record->sample_code,
                'patient_code' => $data['patient_code'] ?? null,
                'code' => $data['code'],
                'antigen_name' => $data['antigen_name'],
                'calibrated_value' => $data['calibrated_value'],
                'signal_noise' => $data['signal_noise'] ?? null,
            ]);
        } else {
            Log::warning('Row skipped due to missing allergen data', [
                'analysis_id' => $record->id,
                'sample_code' => $record->sample_code,
                'patient_code' => $data['patient_code'] ?? null,
                'code' => $data['code'] ?? null,
                'antigen_name' => $data['antigen_name'] ?? null,
                'calibrated_value' => $data['calibrated_value'] ?? null,
            ]);
            return;
        }

        try {
            DB::transaction(function () use ($record, $data) {
                // Only match allergen via code, do not update existing allergens
                $allergen = Allergen::where('code', $data['code'])->first();
                if (!$allergen) {
                    $allergen = Allergen::create([
                        'code' => $data['code'],
                        'name' => $data['antigen_name'],
                        'description' => null,
                        'name_latin' => null,
                        'description_de' => null,
                    ]);
                }

                $calibratedValue = (float)($data['calibrated_value'] ?? 0);
                $testDate = $data['test_date'] ? new \DateTime($data['test_date']) : null;

                // Link allergen to analysis if above threshold
                if ($calibratedValue >= $this->threshold) {
                    $record->analysisAllergens()->updateOrCreate(
                        ['allergen_id' => $allergen->id],
                        [
                            'antigen_id' => $data['antigen_id'] ?? null,
                            'calibrated_value' => $calibratedValue,
                            'signal_noise' => $data['signal_noise'] ?? null,
                        ]
                    );
                    Log::info('Allergen linked to analysis', [
                        'analysis_id' => $record->id,
                        'allergen_id' => $allergen->id,
                        'calibrated_value' => $calibratedValue,
                        'threshold' => $this->threshold,
                    ]);
                } else {
                    Log::debug('Allergen below threshold, not linked to analysis', [
                        'allergen_id' => $allergen->id,
                        'calibrated_value' => $calibratedValue,
                        'threshold' => $this->threshold,
                    ]);
                }

                // Assign allergen to patient
                $patient = $record->patient;
                if (!$patient) {
                    Log::warning('No patient found for analysis', [
                        'analysis_id' => $record->id,
                    ]);
                    return;
                }

                // Load existing allergen assignments
                $pivot = DB::table('allergen_user')
                    ->where('user_id', $patient->id)
                    ->where('allergen_id', $allergen->id)
                    ->first();

                if ($calibratedValue >= $this->threshold) {
                    $shouldAssign = true;
                    if ($pivot && $testDate) {
                        $pivotUpdatedAt = new \DateTime($pivot->updated_at);
                        if ($pivotUpdatedAt >= $testDate) {
                            $shouldAssign = false;
                            Log::info('Skipped allergen assignment; newer pivot record exists', [
                                'patient_id' => $patient->id,
                                'allergen_id' => $allergen->id,
                                'pivot_updated_at' => $pivot->updated_at,
                                'test_date' => $data['test_date'],
                            ]);
                        }
                    }

                    if ($shouldAssign) {
                        $patient->allergens()->syncWithoutDetaching([$allergen->id]);
                        DB::table('allergen_user')
                            ->where('user_id', $patient->id)
                            ->where('allergen_id', $allergen->id)
                            ->update(['updated_at' => now()]);
                        Log::info('Allergen assigned to patient', [
                            'patient_id' => $patient->id,
                            'allergen_id' => $allergen->id,
                            'allergen_name' => $allergen->name,
                            'calibrated_value' => $calibratedValue,
                            'threshold' => $this->threshold,
                        ]);
                    }
                } else {
                    Log::debug('Allergen below threshold, not linked to analysis', [
                        'allergen_id' => $allergen->id,
                        'calibrated_value' => $calibratedValue,
                        'threshold' => $this->threshold,
                    ]);

                    // If below threshold, unlink from patient if exists
                    $patient = $record->patient;
                    if ($patient) {
                        $pivot = DB::table('allergen_user')
                            ->where('user_id', $patient->id)
                            ->where('allergen_id', $allergen->id)
                            ->first();

                        if ($pivot) {
                            $pivotUpdatedAt = new \DateTime($pivot->updated_at);
                            $shouldUnlink = !$testDate || $pivotUpdatedAt < $testDate;
                            if ($shouldUnlink) {
                                $patient->allergens()->detach($allergen->id);
                                Log::info('Allergen unlinked from patient due to low calibrated value', [
                                    'patient_id' => $patient->id,
                                    'allergen_id' => $allergen->id,
                                    'calibrated_value' => $calibratedValue,
                                    'threshold' => $this->threshold,
                                ]);
                            } else {
                                Log::debug('Skipped unlinking allergen; older test date', [
                                    'patient_id' => $patient->id,
                                    'allergen_id' => $allergen->id,
                                    'pivot_updated_at' => $pivot->updated_at,
                                    'test_date' => $data['test_date'],
                                ]);
                            }
                        }
                    }
                }

            });
        } catch (\Exception $e) {
            Log::error('Failed to process allergen', [
                'error' => $e->getMessage(),
                'data' => $data,
                'analysis_id' => $record->id,
            ]);
            throw $e;
        }
    }

    protected static function cleanCalibratedValue($value): float
    {
        Log::info('Starting cleanCalibratedValue', [
            'original_value' => $value,
            'type' => gettype($value)
        ]);

        // Convert to string if not already
        $value = (string) $value;
        Log::info('After string conversion', ['value' => $value]);

        // Accept comma or dot as decimal separator
        $value = str_replace(',', '.', $value);

        // If the value is a simple float (e.g., 1.23, 4, 5.0), just cast
        if (preg_match('/^-?\d+(\.\d+)?$/', trim($value))) {
            return (float) $value;
        }

        // If the value contains tabs or spaces, split and join with decimal point
        if (strpos($value, "\t") !== false || strpos($value, " ") !== false) {
            $parts = preg_split('/[\t\s]+/', $value);
            $value = implode('.', $parts);
        }

        // Remove any non-numeric characters except decimal point
        $value = preg_replace('/[^0-9.\-]/', '', $value);
        Log::info('After removing non-numeric', ['value' => $value]);

        // Ensure only one decimal point
        $parts = explode('.', $value);
        if (count($parts) > 2) {
            $value = $parts[0] . '.' . implode('', array_slice($parts, 1));
            Log::info('After ensuring single decimal', ['value' => $value]);
        }

        $finalValue = (float) $value;
        Log::info('Final cleaned value', [
            'value' => $value,
            'final_value' => $finalValue,
            'type' => gettype($finalValue)
        ]);

        return $finalValue;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = __('Import completed with :successful_rows rows imported.', ['successful_rows' => number_format($import->successful_rows)]);

        if (self::$skippedRows > 0) {
            $body .= ' ' . __(':skipped_rows rows were skipped due to existing sample codes.', ['skipped_rows' => number_format(self::$skippedRows)]);
        }

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . __(':failed_rows rows failed.', ['failed_rows' => number_format($failedRowsCount)]);
        }

        $patientIds = Analysis::where('import_id', $import->id)
            ->distinct()
            ->pluck('patient_id')
            ->filter()
            ->toArray();
        $patients = User::whereIn('id', $patientIds)->where('role', 'patient')->get();

        if ($patients->isNotEmpty()) {
            AssignRecipesJob::dispatch($patients->all())->onQueue('default');
            $body .= ' ' . __('AssignRecipesJob started for :patient_count patients.', ['patient_count' => $patients->count()]);
            Log::info('AssignRecipesJob dispatched', [
                'import_id' => $import->id,
                'patient_count' => $patients->count(),
                'successful_rows' => $import->successful_rows,
                'failed_rows' => $failedRowsCount,
                'skipped_rows' => self::$skippedRows,
            ]);
        } else {
            Log::warning('No patients found for recipe assignment', ['import_id' => $import->id]);
        }

        Log::info('Import completed', [
            'import_id' => $import->id,
            'successful_rows' => $import->successful_rows,
            'failed_rows' => $failedRowsCount,
            'skipped_rows' => self::$skippedRows,
        ]);

        return $body;
    }
}
