@props([
    'recipe' => null,
    'context' => 'book',
    'bookId' => null,
    'isBookRecipes' => false,
])

@php
    $normalizeField = function($value) {
        return is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);
    };

    $record = isset($getRecord) && is_callable($getRecord) ? $getRecord() : $recipe;

    if (!$record || !isset($record->id_recipe)) {
        \Illuminate\Support\Facades\Log::error('Invalid recipe in recipe-actions', [
            'recipe' => $recipe ? (method_exists($recipe, 'toArray') ? $recipe->toArray() : (array) $recipe) : null,
            'context' => $context,
            'bookId' => $bookId,
            'isBookRecipes' => $isBookRecipes,
        ]);
        return;
    }

    $isFilamentContext = isset($this) && method_exists($this, 'getTable') && $this->getTable() instanceof \Filament\Tables\Table;
    $table = $isFilamentContext ? $this->getTable() : null;

    $formCondition = ($context === 'book' && !is_null($bookId));

    // Check recipe limits if we're in book context
    $canAddRecipe = true;
    if ($formCondition && !$isBookRecipes) {
        $book = \App\Models\Book::find($bookId);
        if ($book) {
            // Get the recipe's categories and determine the course
            $categories = $normalizeField($record->category ?? null);
            $primaryCategory = \App\Filament\Resources\BookResource::getPrimaryCategory($categories);
            $course = \App\Filament\Resources\BookResource::mapCategoryToCourse($primaryCategory);

            // Get recipe limits
            $recipeLimits = $book->getRecipesPerCourse();

            // Count current recipes in this course
            $currentCount = $book->recipes()
                ->where('course', $course)
                ->count();

            \Illuminate\Support\Facades\Log::debug('Recipe limit check', [
                'book_id' => $bookId,
                'recipe_id' => $record->id_recipe,
                'categories' => $categories,
                'primary_category' => $primaryCategory,
                'course' => $course,
                'recipe_limits' => $recipeLimits,
                'current_count' => $currentCount,
                'limit_for_course' => $recipeLimits[$course] ?? PHP_INT_MAX,
                'can_add' => $currentCount < ($recipeLimits[$course] ?? PHP_INT_MAX)
            ]);

            // Only allow adding if we haven't hit the limit
            $canAddRecipe = $currentCount < ($recipeLimits[$course] ?? PHP_INT_MAX);
        }
    }

    \Illuminate\Support\Facades\Log::info('Recipe actions rendering', [
        'recipe_id' => $record->id_recipe,
        'isFilamentContext' => $isFilamentContext,
        'table_type' => $table ? get_class($table) : 'null',
        'context' => $context,
        'bookId' => $bookId,
        'isBookRecipes' => $isBookRecipes,
        'record_exists' => isset($record),
        'form_condition' => $formCondition,
        'render_add_form' => ($formCondition && !$isBookRecipes),
        'can_add_recipe' => $canAddRecipe,
        'condition_details' => [
            'context_is_book' => ($context === 'book'),
            'bookId_exists' => !is_null($bookId),
            'not_isBookRecipes' => !$isBookRecipes,
        ],
    ]);
@endphp

<div class="flex justify-end items-center space-x-2">
    @if ($isFilamentContext)
        <!-- Filament context: Link to recipe.view route -->
        <x-filament::link
            icon="heroicon-o-eye"
            color="primary"
            :tooltip="__('Ansehen')"
            :href="route('recipe.view', $record->id_recipe)"
            wire:navigate
            class="fi-icon-btn h-9 w-9"
        />
        @if ($table && $table->getAction('edit') && $table->getAction('edit')->record($record)->isEnabled())
            <x-filament::icon-button
                icon="heroicon-o-pencil-square"
                color="warning"
                :tooltip="__('Bearbeiten')"
                :href="$table->getAction('edit')->record($record)->getUrl()"
            />
        @endif
        @if ($table && $table->getAction('detach') && $table->getAction('detach')->record($record)->isEnabled())
            {{ $table->getAction('detach')->record($record) }}
        @endif
    @else
        <!-- Custom context: Link to recipe.view route -->
        <x-filament::link
            icon="heroicon-o-eye"
            color="primary"
            :tooltip="__('Ansehen')"
            :href="route('recipe.view', $record->id_recipe)"
            class="fi-icon-btn h-9 w-9"
        />
        @if ($context === 'book' && !is_null($bookId))
            @if ($isBookRecipes)
                @php
                    \Illuminate\Support\Facades\Log::debug('Rendering remove button', ['recipe_id' => $record->id_recipe, 'bookId' => $bookId]);
                @endphp
                <x-filament::icon-button
                    icon="heroicon-o-trash"
                    color="danger"
                    :tooltip="__('Entfernen')"
                    wire:click="removeRecipe({{ $record->id_recipe }})"
                    wire:loading.attr="disabled"
                />
            @elseif ($canAddRecipe)
                @php
                    \Illuminate\Support\Facades\Log::debug('Rendering add button', ['recipe_id' => $record->id_recipe, 'bookId' => $bookId]);
                @endphp
                <x-filament::icon-button
                    icon="heroicon-o-plus"
                    color="success"
                    :tooltip="__('Hinzufügen')"
                    wire:click="addRecipe({{ $record->id_recipe }})"
                    wire:loading.attr="disabled"
                />
            @endif
        @else
            @php
                \Illuminate\Support\Facades\Log::debug('Rendering add to book button', ['recipe_id' => $record->id_recipe]);
            @endphp
            <x-filament::icon-button
                icon="heroicon-o-plus"
                color="success"
                :tooltip="__('Zu Buch hinzufügen')"
                class="add-to-book-btn"
                data-recipe-id="{{ $record->id_recipe }}"
            />
        @endif
    @endif
</div>
