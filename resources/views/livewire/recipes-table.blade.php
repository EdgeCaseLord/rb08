<div>
@props([
    'bookRecipes' => [],
    'favoriteRecipes' => [],
    'availableRecipes' => [],
    'showActions' => false,
    'bookId' => null,
    'isBookRecipes' => false,
    'context' => 'book',
    'hasMore' => true,
    'loading' => false,
])

@php
    use App\Filament\Livewire\AvailableRecipesTable;
@endphp

{{-- Book Recipes Section --}}
@if(!empty($bookRecipes))
    <div class="mb-2 text-gray-700 font-semibold">Rezepte im Buch</div>
    <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
        @foreach($bookRecipes as $recipe)
            @php $recipe = AvailableRecipesTable::normalizeRecipe($recipe); @endphp
            @php if (!$recipe || (!isset($recipe['id']) && !isset($recipe['id_recipe']))) continue; @endphp
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
@endif

{{-- Favorite Recipes Section (not in book) --}}
@if(!empty($favoriteRecipes))
    <div class="mb-2 text-gray-700 font-semibold">Favoriten</div>
    <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
        @foreach($favoriteRecipes as $recipe)
            @php
                $inBook = false;
                $id = $recipe['id_external'] ?? $recipe['id'] ?? $recipe['id_recipe'] ?? null;
                foreach ($bookRecipes as $b) {
                    $bid = $b['id_external'] ?? $b['id'] ?? $b['id_recipe'] ?? null;
                    if ($bid && $id && $bid == $id) { $inBook = true; break; }
                }
                if ($inBook) continue;
            @endphp
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
@endif

{{-- Available Recipes Section (not in book or favorites) --}}
@if(!empty($availableRecipes))
    <div class="mb-2 text-gray-700 font-semibold">Verfügbare Rezepte</div>
    <div class="columns-1 sm:columns-2 xl:columns-3 2xl:columns-4 gap-4">
        @foreach($availableRecipes as $recipe)
            @php $recipe = AvailableRecipesTable::normalizeRecipe($recipe); @endphp
            @php if (!$recipe || (!isset($recipe['id']) && !isset($recipe['id_recipe']))) continue; @endphp
            <div class="mb-4 break-inside-avoid">
                <x-filament.recipe-resource.recipe-card
                    :recipe="$recipe"
                    :context="'available'"
                    :bookId="$bookId"
                    :isBookRecipes="false"
                    :showActions="$showActions"
                    wire:key="available-recipe-{{ $recipe['id'] ?? $recipe['id_external'] ?? $recipe['id_recipe'] }}"
                />
            </div>
        @endforeach
    </div>
    @if($loading)
        <div class="flex justify-center py-4">
            <svg class="animate-spin h-6 w-6 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
        </div>
    @endif
    @if($hasMore && !$loading)
        <div class="flex justify-center py-4">
            <button wire:click="loadMore" class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300">Mehr laden</button>
        </div>
    @endif
    @if(!$hasMore && !$loading)
        <div class="text-center text-gray-400 py-2">Keine weiteren Rezepte.</div>
    @endif
@endif
