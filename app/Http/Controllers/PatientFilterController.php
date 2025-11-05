<?php

namespace App\Http\Controllers;

use App\Jobs\CreateBookJob;
use App\Models\Book;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PatientFilterController extends Controller
{
    public function save(Request $request, $patient)
    {
        try {
            $patientModel = $patient instanceof User ? $patient : User::find($patient);
            if (!$patientModel) {
                return response()->json(['error' => 'Patient not found'], 404);
            }

            // Support JSON payload { filters: {...}, updateBookWithFilters?: bool }
            $payload = $request->all();
            $filters = $payload['filters'] ?? $payload ?? [];

            \Log::debug('PatientFilterController received filters', [
                'patient_id' => $patient,
                'raw_filters' => $filters,
            ]);

            // Build a legacy-compatible filter set
            $filterSet = [
                'filterTitle' => $filters['filterTitle'] ?? '',
                'filterIngredients' => $filters['filterIngredients'] ?? '',
                'filterAllergen' => $filters['filterAllergen'] ?? [],
                'filterCategory' => $filters['filterCategory'] ?? [],
                'filterCountry' => $filters['filterCountry'] ?? [],
                'filterCourse' => $filters['filterCourse'] ?? [],
                'filterDiets' => $filters['filterDiets'] ?? [],
                'filterDifficulty' => $filters['filterDifficulty'] ?? [],
                'filterMaxTime' => $filters['filterMaxTime'] ?? [],
                'filterSubstances' => $filters['filterSubstances'] ?? [],
                'updateBookWithFilters' => (bool)($payload['updateBookWithFilters'] ?? false),
            ];
            
            // Normalize filter formats: convert {key: true} to ['key']
            $dictKeys = ['filterAllergen', 'filterCategory', 'filterCourse', 'filterDiets', 'filterDifficulty', 'filterMaxTime'];
            foreach ($dictKeys as $k) {
                if (isset($filterSet[$k]) && is_array($filterSet[$k])) {
                    $v = $filterSet[$k];
                    // If it's an associative array with boolean values, convert to indexed array of keys
                    if (array_values($v) !== $v) {
                        $filterSet[$k] = array_keys(array_filter($v, fn($x) => $x === true || $x === '1' || $x === 1));
                    }
                }
            }
            
            // Countries: ensure it's an array of values
            if (isset($filterSet['filterCountry']) && is_array($filterSet['filterCountry'])) {
                if (array_values($filterSet['filterCountry']) !== $filterSet['filterCountry']) {
                    $filterSet['filterCountry'] = array_keys(array_filter($filterSet['filterCountry'], fn($x) => $x === true || $x === '1' || $x === 1));
                }
            }

            // Persist into patient settings
            $settings = is_array($patientModel->settings) ? $patientModel->settings : (json_decode($patientModel->settings ?? '{}', true) ?: []);
            $settings['recipe_filter_set'] = $filterSet;
            // Save the total count if provided
            if (isset($payload['availTotal'])) {
                $settings['recipe_filter_total'] = (int)$payload['availTotal'];
            }
            
            \Log::debug('Saving patient filters', [
                'patient_id' => $patientModel->id,
                'filterSet' => $filterSet,
            ]);
            
            $patientModel->settings = $settings;
            $patientModel->save();

            // Optionally recreate latest book
            if (!empty($filterSet['updateBookWithFilters'])) {
                $latestBook = Book::where('patient_id', $patientModel->id)->latest()->first();
                if ($latestBook) {
                    CreateBookJob::dispatch($patientModel, null, $latestBook->id, $filterSet);
                }
            }

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('PatientFilterController.save failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Server error'], 500);
        }
    }
}
