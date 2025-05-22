<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
//use Illuminate\Database\Eloquent\SoftDeletes;

class AnalysisAllergen extends Model
{
    use HasFactory;

    protected $table = 'analysis_allergens';

    protected $fillable = [
        'analysis_id',
        'allergen_id',
        'antigen_id',
        'calibrated_value',
        'signal_noise',
    ];

    protected $casts = [
        'id' => 'integer',
        'analysis_id' => 'integer',
        'allergen_id' => 'integer',
        'calibrated_value' => 'float',
        'signal_noise' => 'float',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(Analysis::class);
    }

    public function allergen(): BelongsTo
    {
        return $this->belongsTo(Allergen::class);
    }

    protected static function booted()
    {
        static::creating(function ($analysisAllergen) {
            if ($analysisAllergen->code && $analysisAllergen->antigen_name) {
                $allergen = Allergen::firstOrCreate(
                    ['code' => $analysisAllergen->code],
                    [
                        'name' => $analysisAllergen->antigen_name,
                        'description' => null,
                        'name_latin' => null,
                        'description_de' => null,
                    ]
                );
                $analysisAllergen->allergen_id = $allergen->id;

                $patient = $analysisAllergen->analysis->patient;
                if ($patient) {
                    $patient->allergens()->syncWithoutDetaching([$allergen->id]);
                    Log::info('Allergen assigned to patient', [
                        'patient_id' => $patient->id,
                        'allergen_id' => $allergen->id,
                        'allergen_name' => $allergen->name,
                    ]);
                }

                // Clear temporary fields
                $analysisAllergen->code = null;
                $analysisAllergen->antigen_name = null;
            }
        });
    }
}
