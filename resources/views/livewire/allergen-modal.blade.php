<div wire:init="mount" wire:key="allergen-modal-{{ $analysis_id }}">
    @if($this->table)
        {{ $this->table }}
    @else
        <p>{{ __('No table data available.') }}</p>
    @endif
</div>
