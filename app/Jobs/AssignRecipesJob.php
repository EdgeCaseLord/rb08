<?php

namespace App\Jobs;

use App\Models\Recipe;
use App\Models\User;
use App\Services\CookButlerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Filament\Notifications\Notification;
use App\Models\Book;

class AssignRecipesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $patients;
    protected $importId;

    /**
     * Erstelle eine neue Job-Instanz.
     *
     * @param mixed $patients Array von Patienten (User-Objekte oder IDs)
     * @param int|null $importId Import-ID für CSV-Importe
     */
    public function __construct($patients, $importId = null)
    {
        $this->patients = is_array($patients) ? $patients : [$patients];
        $this->importId = $importId;
    }

    /**
     * Führe den Job aus, um sichere Rezepte zuzuweisen.
     *
     * @return int Statuscode (1 für Erfolg, 0 für Fehler)
     */
    public function handle(): int
    {
        try {
            Log::info('AssignRecipesJob gestartet', [
                'patient_count' => count($this->patients),
                'import_id' => $this->importId,
            ]);

            // Normalize patients to User objects
            $patients = collect($this->patients)->map(function ($patientData) {
                try {
                    if ($patientData instanceof User) {
                        return $patientData->role === 'patient' ? $patientData : null;
                    }
                    if (is_numeric($patientData)) {
                        $patient = User::find($patientData);
                        return $patient && $patient->role === 'patient' ? $patient : null;
                    }
                    if (is_string($patientData)) {
                        $patient = User::where('patient_code', $patientData)
                            ->orWhere('email', $patientData)
                            ->first();
                        return $patient && $patient->role === 'patient' ? $patient : null;
                    }
                    Log::warning('Invalid patient data provided', ['patient_data' => $patientData]);
                    return null;
                } catch (\Exception $e) {
                    Log::error('Error normalizing patient data', [
                        'patient_data' => $patientData,
                        'error' => $e->getMessage(),
                    ]);
                    return null;
                }
            })->filter()->unique('id');

            if ($patients->isEmpty()) {
                Log::warning('No valid patients found', ['input' => $this->patients]);
                return 0;
            }

            $service = new CookButlerService();
            $allRecipeIds = [];
            $courses = ['starter', 'main_course', 'dessert'];

            foreach ($patients as $patient) {
                // First recheck existing recipe assignments
                // $this->recheckExistingRecipeAssignments($patient);

                Log::info('Verarbeite Patient für Rezeptabholung', [
                    'patient_id' => $patient->id,
                    'allergens' => $patient->allergens->pluck('name')->toArray(),
                    'settings' => $patient->settings,
                    'recipe_totals' => $patient->recipe_totals,
                ]);

                foreach ($courses as $course) {
                    try {
                        $defaultRecipesPerCourse = [
                            'starter' => 5,
                            'main_course' => 5,
                            'dessert' => 5,
                        ];
                        $limit = $patient->settings['recipes_per_course'][$course]
                            ?? ($patient->lab->settings['recipes_per_course'][$course]
                                ?? $defaultRecipesPerCourse[$course]);
                        $totalRecipes = $patient->recipe_totals[$course] ?? 0;
                        $offset = $totalRecipes > 0 ? rand(0, max(0, $totalRecipes - $limit)) : 0;

                        $response = $service->fetchRecipesForPatientByCourse($patient, $course, $limit, $offset);
                        $recipeIds = $response['recipe_ids'] ?? [];

                        $totalRecipes = $response['total']['value'] ?? $totalRecipes;
                        if ($totalRecipes > 0) {
                            $recipeTotals = $patient->recipe_totals;
                            $recipeTotals[$course] = $totalRecipes;
                            $patient->recipe_totals = $recipeTotals;
                            $patient->save();
                            Log::info('Updated recipe totals for patient', [
                                'patient_id' => $patient->id,
                                'course' => $course,
                                'total_recipes' => $totalRecipes,
                            ]);
                        }

                        if (!empty($recipeIds)) {
                            $allRecipeIds = array_merge($allRecipeIds, $recipeIds);
                            Log::info('Rezepte für Gang abgerufen', [
                                'patient_id' => $patient->id,
                                'gang' => $course,
                                'rezept_ids' => $recipeIds,
                                'limit' => $limit,
                                'offset' => $offset,
                            ]);
                        } else {
                            Log::warning('Keine Rezepte für Gang abgerufen', [
                                'patient_id' => $patient->id,
                                'gang' => $course,
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('Fehler beim Abruf der Rezepte', [
                            'patient_id' => $patient->id,
                            'course' => $course,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            $allRecipeIds = array_unique($allRecipeIds);
            if (empty($allRecipeIds)) {
                Log::warning('Keine Rezepte für Patienten abgerufen');
                return 0;
            }

            // Fetch recipe details in batch
            $recipeDetails = $service->fetchRecipeDetailsBatch($allRecipeIds);
            if (empty($recipeDetails)) {
                Log::error('Keine Rezeptdetails abgerufen', ['rezept_ids' => $allRecipeIds]);
                return 0;
            }

            // Assign all fetched recipes to each patient (no more filtering)
            foreach ($patients as $patient) {
                try {
                    $this->assignRecipesToPatient($patient, $recipeDetails);
                    CreateBookJob::dispatch($patient)->onQueue('default');
                    Log::info('CreateBookJob für Patient gestartet', [
                        'patient_id' => $patient->id,
                        'rezept_anzahl' => count($recipeDetails),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error processing recipes for patient', [
                        'patient_id' => $patient->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('AssignRecipesJob abgeschlossen', ['patient_anzahl' => $patients->count()]);
            Notification::make()
                ->title('Rezeptzuweisung abgeschlossen')
                ->body('Sichere Rezepte wurden für ' . $patients->count() . ' Patienten zugewiesen.')
                ->success()
                ->send();

            return 1;
        } catch (\Exception $e) {
            Log::error('AssignRecipesJob fehlgeschlagen', [
                'fehler' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return 0;
        }
    }

    /**
     * Filtere Rezepte, um sicherzustellen, dass sie für den Patienten sicher sind.
     *
     * @param User $patient Der Patient
     * @param array $recipeDetails Die Rezeptdetails
     * @return array Sichere Rezeptdetails
     */
    // protected function filterSafeRecipes(User $patient, array $recipeDetails): array
    // {
    //     $patientAllergenIds = $patient->allergens()->pluck('id')->toArray();
    //     $safeRecipes = [];

    //     foreach ($recipeDetails as $recipeDetail) {
    //         try {
    //             $recipe = Recipe::firstOrCreate(
    //                 ['id_external' => $recipeDetail['id']],
    //                 [
    //                     'title' => $recipeDetail['title'] ?? "Rezept {$recipeDetail['id']}",
    //                     'language' => 'de-de',
    //                     'ingredients' => json_encode($recipeDetail['ingredients'] ?? []),
    //                     'allergens' => json_encode($this->identifyAllAllergens($recipeDetail['ingredients'] ?? [])),
    //                     'diets' => json_encode($recipeDetail['diets'] ?? []),
    //                 ]
    //             );

    //             $unsafe = DB::table('allergen_ingredient')
    //                 ->whereIn('ingredient_id', $recipe->ingredients()->pluck('id'))
    //                 ->whereIn('allergen_id', $patientAllergenIds)
    //                 ->exists();

    //             if (!$unsafe) {
    //                 $safeRecipes[] = $recipeDetail;
    //             } else {
    //                 Log::warning('Rezept unsicher für Patient', [
    //                     'rezept_id' => $recipe->id_external,
    //                     'patient_id' => $patient->id,
    //                 ]);
    //             }
    //         } catch (\Exception $e) {
    //             Log::error('Error filtering recipe', [
    //                 'recipe_id' => $recipeDetail['id'],
    //                 'patient_id' => $patient->id,
    //                 'error' => $e->getMessage(),
    //             ]);
    //         }
    //     }

    //     return $safeRecipes;
    // }

    /**
     * Identifiziere alle Allergene in den Zutaten eines Rezepts.
     *
     * @param array $ingredients Die Zutaten
     * @return array Alle identifizierten Allergene
     */
    // protected function identifyAllAllergens(array $ingredients): array
    // {
    //     try {
    //         $ingredientNames = array_map(fn($ing) => $ing['product'] ?? '', $ingredients);
    //         Log::info('Mapping ingredients to allergens', ['ingredients' => $ingredientNames]);
    //         $allergens = DB::table('allergen_ingredient')
    //             ->join('allergens', 'allergen_ingredient.allergen_id', '=', 'allergens.id')
    //             ->whereIn('allergen_ingredient.ingredient_id', DB::table('ingredients')
    //                 ->whereIn('name_de', $ingredientNames)
    //                 ->pluck('id'))
    //             ->pluck('allergens.name')
    //             ->unique()
    //             ->toArray();

    //         Log::info('Identified allergens for recipe', ['allergens' => $allergens]);
    //         return array_map(fn($allergen) => ['allergen' => $allergen, 'value' => true], $allergens);
    //     } catch (\Exception $e) {
    //         Log::error('Error identifying allergens', [
    //             'error' => $e->getMessage(),
    //         ]);
    //         return [];
    //     }
    // }

    /**
     * Assign all fetched recipes to the patient's book using the new book-recipe model.
     */
    protected function assignRecipesToPatient(User $patient, array $safeRecipes): void
    {
        $book = Book::where('patient_id', $patient->id)->first();
        if (!$book) {
            Log::warning('No book found for patient', ['patient_id' => $patient->id]);
            return;
        }
        foreach ($safeRecipes as $recipeDetail) {
            try {
                $recipe = Recipe::where('id_external', $recipeDetail['id'])->first();
                if ($recipe) {
                    $book->addRecipe($recipe->id_recipe);
                    Log::info('Recipe assigned to book', [
                        'book_id' => $book->id,
                        'patient_id' => $patient->id,
                        'recipe_id' => $recipe->id_recipe,
                    ]);
                } else {
                    Log::warning('Recipe not found for assignment', [
                        'patient_id' => $patient->id,
                        'recipe_external_id' => $recipeDetail['id'],
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Error assigning recipe to book', [
                    'patient_id' => $patient->id,
                    'recipe_id' => $recipeDetail['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
