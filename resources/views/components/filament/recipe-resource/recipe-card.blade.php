@props([
    'recipe' => null,
    'context' => 'book',
    'bookId' => null,
    'isBookRecipes' => false,
    'showActions' => true,
    'canAddToBook' => true,
])

@php
    use App\Filament\Livewire\AvailableRecipesTable;
    $normalizeField = function($value) {
        return is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);
    };

    // Always normalize the recipe using our utility
    $record = AvailableRecipesTable::normalizeRecipe($recipe);

    $id = $record['id_external'] ?? $record['id'] ?? $record['id_recipe'] ?? null;
    if (!$record || !$id) {
        \Illuminate\Support\Facades\Log::error('Invalid recipe in recipe-card', [
            'recipe' => $record ?? null,
            'context' => $context,
            'bookId' => $bookId,
            'isBookRecipes' => $isBookRecipes,
        ]);
        return;
    }

    $media = $record['media'] ?? [];
    $previewImageUrl = !empty($media['preview']) ? $media['preview'][0] : null;

    $title = $record['title'] ?? '';
    $category = $record['category'] ?? [];
    if (is_string($category)) {
        $category = (json_decode($category, true) ?: [$category]);
    } elseif (!is_array($category)) {
        $category = [];
    }
    if (is_array($category) && !array_is_list($category)) {
        $category = array_values($category);
    }
    $categoryText = !empty($category) ? implode(', ', $category) : __('Keine');

    $allergens = $record['allergens'] ?? [];
    $presentAllergens = [];
    if (!empty($allergens)) {
        foreach ($allergens as $allergen) {
            if (is_array($allergen)) {
                $name = $allergen['name_de'] ?? $allergen['name'] ?? $allergen['allergen'] ?? null;
                if (($allergen['value'] ?? false) === true || ($allergen['value'] ?? $allergen['allergen'] ?? false)) {
                    if ($name) $presentAllergens[] = $name;
                }
            } elseif (is_string($allergen)) {
                $presentAllergens[] = $allergen;
            }
        }
    }
    $allowedAllergens = [
        'Glutenhaltiges Getreide' => ['de' => 'Glutenhaltiges Getreide', 'en' => 'Cereals containing gluten'],
        'Hühnerei' => ['de' => 'Hühnerei', 'en' => 'Eggs'],
        'Erdnüsse' => ['de' => 'Erdnüsse', 'en' => 'Peanuts'],
        'Milch' => ['de' => 'Milch', 'en' => 'Milk'],
        'Sellerie' => ['de' => 'Sellerie', 'en' => 'Celery'],
        'Sesamsamen' => ['de' => 'Sesamsamen', 'en' => 'Sesame seeds'],
        'Lupinen' => ['de' => 'Lupinen', 'en' => 'Lupin'],
        'Krebstiere' => ['de' => 'Krebstiere', 'en' => 'Crustaceans'],
        'Fisch' => ['de' => 'Fisch', 'en' => 'Fish'],
        'Soja' => ['de' => 'Soja', 'en' => 'Soybeans'],
        'Schalenfrüchte' => ['de' => 'Schalenfrüchte', 'en' => 'Tree nuts'],
        'Senf' => ['de' => 'Senf', 'en' => 'Mustard'],
        'Schwefeldioxid und Sulfit' => ['de' => 'Schwefeldioxid und Sulfit', 'en' => 'Sulphur dioxide and sulphites'],
        'Weichtiere' => ['de' => 'Weichtiere', 'en' => 'Molluscs'],
    ];
    $filteredAllergens = collect($presentAllergens)
        ->filter(fn($a) => array_key_exists($a, $allowedAllergens))
        ->values();
    $allergensText = !$filteredAllergens->isEmpty()
        ? implode(', ', $filteredAllergens->map(fn($a) => $allowedAllergens[$a][app()->getLocale()] ?? $allowedAllergens[$a]['de'])->all())
        : __('Keine');

    $favorites = [];
    if (!empty($bookId)) {
        $book = \App\Models\Book::find($bookId);
        if ($book && $book->patient) {
            $favorites = $book->patient->settings['favorites'] ?? [];
        }
    }
    if (empty($favorites) && auth()->check()) {
        $favorites = auth()->user()->settings['favorites'] ?? [];
    }
    $isFavorite = in_array($id, $favorites);

    $internalId = $record['id_recipe'] ?? null;
    if (!$internalId && isset($record['id_external'])) {
        $internalId = \App\Models\Recipe::where('id_external', $record['id_external'])->value('id_recipe');
    }
    if (!$internalId && isset($record['id'])) {
        $internalId = \App\Models\Recipe::where('id_external', $record['id'])->value('id_recipe');
    }

    $diets = $record['diets'] ?? [];
    $dietTranslations = [
        'vegetarian' => 'Vegetarisch',
        'vegan' => 'Vegan',
        'glutenfree' => 'Glutenfrei',
        'gluten-free' => 'Glutenfrei',
        'lactosefree' => 'Laktosefrei',
        'lactose-free' => 'Laktosefrei',
        'lowcarb' => 'Low-Carb',
        'low-carb' => 'Low-Carb',
        'paleo' => 'Paleo',
        'keto' => 'Keto',
        'halal' => 'Halal',
        'kosher' => 'Koscher',
        'alcohol-free' => app()->getLocale() === 'de' || !app()->getLocale() ? 'ohne Alkohol' : 'alcohol-free',
        'none' => 'Keine',
    ];
    $dietList = [];
    if (!empty($diets)) {
        foreach ($diets as $diet) {
            if (is_array($diet)) {
                $name = $diet['diet'] ?? $diet['name'] ?? null;
                $value = $diet['value'] ?? true;
                if ($value && $name) {
                    $dietList[] = $dietTranslations[$name] ?? $name;
                }
            } elseif (is_string($diet)) {
                $dietList[] = $dietTranslations[$diet] ?? $diet;
            }
        }
    }
    $dietsText = !empty($dietList) ? implode(', ', $dietList) : __('Keine');
@endphp

<div class="bg-white rounded-lg shadow-md overflow-hidden relative">
    <!-- Image -->
    <div class="aspect-w-16 aspect-h-9">
        @if ($previewImageUrl)
            <img src="{{ $previewImageUrl }}?v={{ time() }}"
                 alt="{{ $title }}"
                 class="w-full h-48 object-cover object-center">
        @else
            <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                <span class="text-gray-500 text-sm">{{ __('Kein Bild') }}</span>
            </div>
        @endif
    </div>

    <!-- Content -->
    <div class="p-4">
        <h3 class="text-lg font-semibold text-gray-800 mb-2">{{ $title }}</h3>

        <div class="space-y-2 text-sm text-gray-600">
            <p><strong>{{ __('Kategorie') }}:</strong> {{ $categoryText }}</p>
            <p><strong>{{ __('Allergene') }}:</strong> {{ $allergensText }}</p>
            <p><strong>{{ __('Ernährungsweise') }}:</strong> {{ $dietsText }}</p>
        </div>

        @if($showActions)
            <div class="mt-4 flex justify-end space-x-2">
                <!-- View Recipe -->
                @if($internalId)
                @php \Log::debug('Rendering view button', ['internalId' => $internalId]); @endphp
                <x-filament::icon-button
                    icon="heroicon-o-eye"
                    color="primary"
                    :tooltip="__('Rezept ansehen')"
                    x-on:click.prevent="$dispatch('openRecipeModal', [{{ $internalId }}])"
                />
                @endif

                <!-- Favorite Heart Icon (conditional buttons) -->
                @if($isFavorite)
                    <x-filament::icon-button
                        icon="heroicon-s-heart"
                        color="danger"
                        tooltip="{{ __('Remove from Favorites') }}"
                        x-on:click.prevent="if(confirm('Wirklich aus Favoriten entfernen?')) { $wire.removeFromFavorites({{ $id }}) }"
                    />
                @else
                    <x-filament::icon-button
                        icon="heroicon-o-heart"
                        color="gray"
                        tooltip="{{ __('Add to Favorites') }}"
                        wire:click="addToFavorites({{ $id }})"
                    />
                @endif

                @if($context === 'book' && $isBookRecipes)
                    <!-- Remove from Book -->
                    <x-filament::icon-button
                        icon="heroicon-o-trash"
                        color="danger"
                        :tooltip="__('Aus Buch entfernen')"
                        wire:click="removeRecipe({{ $id }})"
                        wire:loading.attr="disabled"
                    />
                @endif
                @if($context === 'available' || $context === 'favorites')
                    <!-- Add to Book from Available or Favorites -->
                    <x-filament::icon-button
                        icon="heroicon-o-plus"
                        color="success"
                        :tooltip="__('Zum Buch hinzufügen')"
                        wire:click.prevent="addToBook({{ $id }})"
                        wire:loading.attr="disabled"
                    />
                @endif
            </div>
        @endif
    </div>
</div>
