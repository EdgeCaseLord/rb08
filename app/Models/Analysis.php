<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class Analysis extends Model
{
    use HasFactory;

    protected $table = 'analyses';

    protected $fillable = [
        'qr_code', 'sample_code', 'sample_date', 'patient_code', 'patient_name',
        'patient_date_of_birth', 'assay_date', 'test_date', 'test_by',
        'approval_date', 'approval_by', 'additional_information',
        'patient_id', 'doctor_id', 'lab_id', 'import_id', 'is_csv',
    ];

    protected $casts = [
        'id' => 'integer',
        'sample_date' => 'date',
        'patient_date_of_birth' => 'date',
        'assay_date' => 'date',
        'test_date' => 'date',
        'approval_date' => 'date',
        'patient_id' => 'integer',
        'doctor_id' => 'integer',
        'lab_id' => 'integer',
        'import_id' => 'integer',
        'is_csv' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function lab(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lab_id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(\Filament\Actions\Imports\Models\Import::class, 'import_id');
    }

    public function analysisAllergens(): HasMany
    {
        return $this->hasMany(AnalysisAllergen::class, 'analysis_id');
    }

    protected static function booted()
    {
        static::creating(function ($analysis) {
            Log::info('Analyse wird erstellt', [
                'sample_code' => $analysis->sample_code,
                'ist_csv' => $analysis->is_csv,
            ]);

            // Set lab_id
            $user = auth()->user();
            if ($user) {
                if ($user->isLab()) {
                    $analysis->lab_id = $user->id;
                } elseif ($user->isAdmin()) {
                    $firstLab = User::labs()->first();
                    $analysis->lab_id = $firstLab?->id;
                }
                if (!$analysis->lab_id) {
                    Log::warning('No lab_id set for analysis', [
                        'sample_code' => $analysis->sample_code,
                        'user_id' => $user->id,
                    ]);
                }
            }

            // Doctor creation or selection
            if (!empty($analysis->approval_by)) {
                $doctor = User::where('name', 'like', '%' . trim($analysis->approval_by) . '%')
                    ->where('role', 'doctor')
                    ->first();
                if (!$doctor) {
                    $doctor = User::create([
                        'name' => $analysis->approval_by,
                        'email' => strtolower(str_replace('dr', '.', $analysis->approval_by)) . '@rezept-butler.com',
                        'password' => Hash::make('password'),
                        'role' => 'doctor',
                        'lab_id' => $analysis->lab_id,
                    ]);
                    Log::info('Arzt erstellt', ['id' => $doctor->id, 'name' => $doctor->name]);
                }
                $analysis->doctor_id = $doctor->id;
            } else {
                Log::warning('Kein Genehmiger für Analyse angegeben', [
                    'sample_code' => $analysis->sample_code,
                ]);
                if (!$analysis->is_csv) {
                    $defaultDoctor = User::where('role', 'doctor')->first();
                    if ($defaultDoctor) {
                        $analysis->doctor_id = $defaultDoctor->id;
                        Log::info('Standard-Arzt zugewiesen', ['arzt_id' => $defaultDoctor->id]);
                    }
                }
            }

            // Patient creation or selection
            if ($analysis->patient_code) {
                $patient = User::where('patient_code', $analysis->patient_code)->first();
                if (!$patient) {
                    $patient = User::create([
                        'name' => $analysis->patient_name ?? 'Unbekannter Patient',
                        'email' => strtolower(str_replace(' ', '.', $analysis->patient_name ?? 'unknown')) . '.' . ($analysis->patient_code ?? 'unknown') . '@rezept-butler.com',
                        'password' => Hash::make('password'),
                        'role' => 'patient',
                        'patient_code' => $analysis->patient_code,
                        'birthdate' => $analysis->patient_date_of_birth,
                        'doctor_id' => $analysis->doctor_id,
                        'lab_id' => $analysis->lab_id,
                    ]);
                    Log::info('Patient erstellt', [
                        'id' => $patient->id,
                        'name' => $patient->name,
                        'patient_code' => $patient->patient_code,
                        'arzt_id' => $patient->doctor_id,
                    ]);
                } else {
                    if (!$patient->doctor_id && $analysis->doctor_id) {
                        $patient->doctor_id = $analysis->doctor_id;
                        $patient->save();
                        Log::info('Arzt-ID des Patienten aktualisiert', [
                            'patient_id' => $patient->id,
                            'arzt_id' => $patient->doctor_id,
                        ]);
                    }
                }
                $analysis->patient_id = $patient->id;
            } else {
                Log::error('Fehlende Patientendaten für Analyse', [
                    'sample_code' => $analysis->sample_code,
                    'patient_code' => $analysis->patient_code,
                    'patient_name' => $analysis->patient_name,
                    'patient_geburtstag' => $analysis->patient_date_of_birth,
                ]);
                throw new \Exception('Patienten-Code ist erforderlich.');
            }
        });

        static::created(function ($analysis) {
            Log::info('Analyse erstellt', [
                'id' => $analysis->id,
                'sample_code' => $analysis->sample_code,
                'ist_csv' => $analysis->is_csv,
                'import_id' => $analysis->import_id,
            ]);

            /*
             * Deprecated: Manual entry logic is inactive as all analyses are now created via CSV upload.
             * This logic is retained for potential reactivation if manual entry is needed in the future.
             *
            $patient = User::find($analysis->patient_id);
            if (!$patient) {
                Log::warning('Patient für Rezeptzuweisung nicht gefunden', [
                    'patient_id' => $analysis->patient_id,
                ]);
                return;
            }

            if ($analysis->code) {
                // Find or create Allergen based on code
                $allergen = Allergen::firstOrCreate(
                    ['code' => $analysis->code],
                    [
                        'name' => $analysis->antigen_name ?? 'Unknown Allergen',
                        'description' => null,
                        'name_latin' => null,
                        'description_de' => null,
                    ]
                );

                // Create AnalysisAllergen record with allergen_id
                AnalysisAllergen::create([
                    'analysis_id' => $analysis->id,
                    'allergen_id' => $allergen->id,
                    'calibrated_value' => $analysis->calibrated_value,
                    'signal_noise' => $analysis->signal_noise,
                ]);

                // Remove temporary allergen data from Analysis
                $analysis->update([
                    'antigen_id' => null,
                    'antigen_name' => null,
                    'code' => null,
                    'calibrated_value' => null,
                    'signal_noise' => null,
                ]);
            }

            // Dispatch recipe job only for manual entries
            if (!$analysis->is_csv) {
                try {
                    AssignRecipesJob::dispatch($patient)
                        ->onQueue('default');
                    Log::info('AssignRecipesJob für manuelle Eingabe gestartet', [
                        'patient_id' => $patient->id,
                        'patient_code' => $patient->patient_code,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Fehler beim Starten von AssignRecipesJob für manuelle Eingabe', [
                        'patient_id' => $patient->id,
                        'fehler' => $e->getMessage(),
                    ]);
                }
            } else {
                Log::debug('CSV-Import erkannt, AssignRecipesJob wird von AnalysisImporter gesteuert', [
                    'import_id' => $analysis->import_id,
                    'patient_id' => $patient->id,
                ]);
            }
            */

            Log::debug('CSV-Import erkannt, AssignRecipesJob wird von AnalysisImporter gesteuert', [
                'import_id' => $analysis->import_id,
                'patient_id' => $analysis->patient_id,
            ]);
        });
    }
}
