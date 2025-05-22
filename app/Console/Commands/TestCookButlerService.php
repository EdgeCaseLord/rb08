<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\CookButlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestCookButlerService extends Command
{
    protected $signature = 'cookbutler:test';
    protected $description = 'Test the CookButlerService by fetching recipes';

    public function handle()
    {
        $this->info('Testing CookButlerService...');

        try {
            $cookButlerService = new CookButlerService();
            $patient = User::where('role', 'patient')->first();

            if (!$patient) {
                $this->error('No patient found to test with. Please create a patient first.');
                return;
            }

            $course = 'dessert';
            $this->info('Fetching recipes for patient ID: ' . $patient->id);

            $recipeIds = $cookButlerService->fetchRecipesForPatientByCourse($patient, $course, 5);
            $this->info('Recipes fetched: ' . count($recipeIds));
            Log::info('CookButlerService test - Recipes fetched', [
                'patient_id' => $patient->id,
                'course' => $course,
                'recipe_ids' => $recipeIds,
                'recipe_count' => count($recipeIds),
            ]);

            if (!empty($recipeIds)) {
                $cookButlerService->fetchAndAssignRecipeDetails([$patient], $recipeIds);
                $this->info('Recipe details fetched and assigned');
                Log::info('CookButlerService test - Recipe details fetched and assigned', [
                    'patient_id' => $patient->id,
                    'recipe_ids' => $recipeIds,
                ]);
            }
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            Log::error('CookButlerService test failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
