<div>
    <div class="mb-2 p-2 bg-[#FEF0E8] rounded text-xs flex justify-end">
        <div class="text-right w-full text-[#FF6100] font-bold">
        @php
            use App\Filament\Livewire\AvailableRecipesTable;
            $book = \App\Models\Book::find($bookId);
            $maxPerCourse = $book ? $book->getRecipesPerCourse() : ['starter'=>5,'main_course'=>5,'dessert'=>5];
            $courseLabels = ['starter'=>__('Vorspeisen'),'main_course'=>__('Hauptgerichte'),'dessert'=>__('Desserts')];
            $recipesArr = is_array($recipes ?? null) ? $recipes : (($recipes ?? collect())->all() ?? []);
            // Normalize all recipes
            $recipesArr = array_map(fn($r) => AvailableRecipesTable::normalizeRecipe($r), $recipesArr);
            // Group by course and sort
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
        @endphp
        @foreach($maxPerCourse as $course => $max)
            <span class="ml-4">
                {{ $courseLabels[$course] ?? ucfirst($course) }}: {{ $counts[$course] ?? 0 }}/{{ $max }}
            </span>
        @endforeach
        </div>
    </div>

    @if(count($grouped))
        @php
            $currentRecipes = $grouped[$currentCourse] ?? [];
        @endphp
        <div class="mb-2">
            <div class="bg-orange-200 text-orange-900 font-bold px-4 py-2 rounded mb-2 inline-block">
                {{ $courseLabels[$currentCourse] ?? ucfirst($currentCourse) }}
            </div>
        </div>
        <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
            @foreach($currentRecipes as $recipe)
                <div class="mb-4 break-inside-avoid">
                    <x-filament.recipe-resource.recipe-card
                        :recipe="$recipe"
                        :context="'book'"
                        :bookId="$bookId"
                        :isBookRecipes="true"
                        :showActions="true"
                        wire:key="book-recipe-{{ $recipe['id'] ?? $recipe['id_external'] ?? $recipe['id_recipe'] }}"
                    />
                </div>
            @endforeach
        </div>
        <div class="flex justify-between mt-4">
            @if($currentCourse !== $courseOrder[0])
                <button wire:click="prevCourse" wire:loading.attr="disabled" wire:target="prevCourse" class="bg-orange-200 text-orange-900 font-bold px-4 py-2 rounded transition-colors duration-150 hover:bg-[#FEF0E8] hover:text-[#FF6100] hover:font-bold">
                    &lt; {{ $courseLabels[$courseOrder[array_search($currentCourse, $courseOrder)-1]] ?? '' }}
                    <span wire:loading wire:target="prevCourse">
                        <svg class="animate-spin h-5 w-5 inline-block ml-2 text-[#FF6100]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    </span>
                </button>
            @else
                <span></span>
            @endif
            @if($currentCourse !== $courseOrder[count($courseOrder)-1])
                <button wire:click="nextCourse" wire:loading.attr="disabled" wire:target="nextCourse" class="bg-orange-200 text-orange-900 font-bold px-4 py-2 rounded ml-auto transition-colors duration-150 hover:bg-[#FEF0E8] hover:text-[#FF6100] hover:font-bold">
                    <span wire:loading wire:target="nextCourse">
                        <svg class="animate-spin h-5 w-5 inline-block mr-2 text-[#FF6100]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                    </span>
                    {{ $courseLabels[$courseOrder[array_search($currentCourse, $courseOrder)+1]] ?? '' }} &gt;
                </button>
            @endif
        </div>
    @else
        <div class="text-gray-400 py-2">Keine Rezepte im Buch.</div>
    @endif
</div>
