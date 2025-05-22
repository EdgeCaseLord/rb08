@php
    $bookId = $bookId ?? null;
@endphp

<div>
    @livewire('recipes-table', [
        'bookId' => $bookId ?? null,
        'context' => $context ?? 'book',
        'showActions' => $showActions ?? true,
        'isBookRecipes' => $isBookRecipes ?? false,
    ])
</div>
