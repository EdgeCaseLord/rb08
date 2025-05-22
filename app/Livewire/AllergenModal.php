<?php

namespace App\Livewire;

use App\Models\AnalysisAllergen;
use Filament\Forms;
use Filament\Tables;
use Livewire\Component;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Table as FilamentTable;
use Filament\Forms\Concerns\InteractsWithForms;

class AllergenModal extends Component implements Tables\Contracts\HasTable, HasForms
{
    use Tables\Concerns\InteractsWithTable;
    use InteractsWithForms;

    public $analysis_id;

    public function mount($analysis_id)
    {
        $this->analysis_id = $analysis_id;
        Log::debug('AllergenModal mounted', ['analysis_id' => $analysis_id]);
    }

    public function table(FilamentTable $table): FilamentTable
    {
        $query = AnalysisAllergen::query()
            ->where('analysis_id', $this->analysis_id)
            ->with('allergen');

        // Debug: Log the query results
        $results = $query->get();
        Log::info('AllergenModal Query Results', [
            'analysis_id' => $this->analysis_id,
            'count' => $results->count(),
            'data' => $results->toArray(),
        ]);

        $table = $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('allergen.name')
                    ->label(__('Allergen Name'))
                    ->sortable()
                    ->searchable()
                    ->default('-'),
                Tables\Columns\TextColumn::make('allergen.code')
                    ->label(__('Code'))
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('calibrated_value')
                    ->label(__('Calibrated Value'))
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => number_format($state, 2)),
                Tables\Columns\TextColumn::make('signal_noise')
                    ->label(__('Signal Noise'))
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => number_format($state, 2)),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->emptyStateHeading(__('No allergens found'))
            ->emptyStateDescription(__('No allergens are associated with this analysis.'));

        Log::debug('AllergenModal table initialized', [
            'columns' => array_keys($table->getColumns()),
            'query_count' => $results->count(),
        ]);

        return $table;
    }

    public function makeFilamentTranslatableContentDriver(): ?\Filament\Support\Contracts\TranslatableContentDriver
    {
        return null;
    }

    public function hasCachedForm(): bool
    {
        return false;
    }

    public function makeForm(): \Filament\Forms\Form
    {
        return Forms\Form::make($this)->schema([]);
    }

    public function render()
    {
        Log::debug('AllergenModal rendering', [
            'analysis_id' => $this->analysis_id,
            'table_exists' => isset($this->table),
        ]);
        return view('livewire.allergen-modal');
    }
}
?>
