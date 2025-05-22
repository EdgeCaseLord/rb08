<div>
    <div class="mb-2 p-2 bg-[#FEF0E8] rounded text-xs flex justify-end">
        <div class="text-right w-full text-[#FF6100] font-bold">
        @php
            use App\Filament\Livewire\AvailableRecipesTable;
            $book = \App\Models\Book::find($bookId);
            $patient = $book ? $book->patient : null;
            $maxPerCourse = $book ? $book->getRecipesPerCourse() : ['starter'=>5,'main_course'=>5,'dessert'=>5];
            $courseLabels = ['starter'=>__('Vorspeisen'),'main_course'=>__('Hauptgerichte'),'dessert'=>__('Desserts')];
            // Normalize all recipes to arrays
            $recipesArr = ($recipes ?? collect())->map(function($r) { return is_object($r) ? AvailableRecipesTable::recipeModelToArray($r) : (is_array($r) ? $r : []); })->all();
            // Group by course
            $grouped = [];
            foreach ($recipesArr as $r) {
                $course = $r['course'] ?? 'main_course';
                $grouped[$course][] = $r;
            }
            // Sort courses by defined order
            $courseOrder = array_keys($maxPerCourse);
            uksort($grouped, function($a, $b) use ($courseOrder) {
                $ia = array_search($a, $courseOrder);
                $ib = array_search($b, $courseOrder);
                return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
            });
            // Sort recipes within each course alphabetically by title
            foreach ($grouped as &$recipesInCourse) {
                usort($recipesInCourse, function($a, $b) {
                    return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
                });
            }
            unset($recipesInCourse);
            // Calculate counts for each course
            $counts = [];
            foreach ($grouped as $course => $recipesInCourse) {
                $counts[$course] = count($recipesInCourse);
            }
            $settings = $patient ? ($patient->settings ?? []) : [];
            $favorites = $settings['favorites'] ?? [];
            $totalFavs = [];
            foreach($favorites as $favId) {
                $recipe = \App\Models\Recipe::where('id_external', $favId)->orWhere('id_recipe', $favId)->first();
                if($recipe) {
                    $c = $recipe->course ?? 'main_course';
                    $totalFavs[$c] = ($totalFavs[$c] ?? 0) + 1;
                }
            }
        @endphp
        @foreach($maxPerCourse as $course => $max)
            <span class="ml-4">
                {{ $courseLabels[$course] ?? ucfirst($course) }}: {{ $counts[$course] ?? 0 }}/{{ $totalFavs[$course] ?? 0 }}
            </span>
        @endforeach
        </div>
    </div>
    <!-- <div class="text-xs text-gray-400 mb-2">DEBUG: count={{ ($recipes ?? collect())->count() }} | ids=[{{ ($recipes ?? collect())->pluck('id_recipe')->join(', ') }}]</div> -->
    @if(count($grouped))
        @foreach($grouped as $course => $recipesInCourse)
            <div class="mb-2">
                <div class="bg-orange-200 text-orange-900 font-bold px-4 py-2 rounded mb-2 inline-block">
                    {{ $courseLabels[$course] ?? ucfirst($course) }}
                </div>
            </div>
            <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
                @foreach($recipesInCourse as $recipe)
                    <div class="mb-4 break-inside-avoid">
                        <x-filament.recipe-resource.recipe-card
                            :recipe="$recipe"
                            :context="'favorites'"
                            :bookId="$bookId"
                            :isBookRecipes="false"
                            :showActions="true"
                            wire:key="fav-recipe-{{ $recipe['id'] ?? $recipe['id_external'] ?? $recipe['id_recipe'] }}"
                        />
                    </div>
                @endforeach
            </div>
            @if(!$loop->last)
                <hr class="my-6 border-orange-200">
            @endif
        @endforeach
    @else
        <div class="text-gray-400 py-2">Keine Favoriten verfügbar.</div>
    @endif
</div>
