@use(App\Filament\Resources\BookResource)
<x-filament-panels::page>

    @php $bookId = $record->id ?? null; @endphp
    @php
        // Hardened normalization for all recipe fields that may be JSON or array
        $normalizeField = function($value) {
            return is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);
        };
        $book = $record ?? null;
        $isEdit = !empty($book) && !empty($book->id);
        $patient = $book ? $book->patient : null;
        // Try to get the latest analysis for the patient
        $analysis = $patient ? ($patient->analyses()->latest('created_at')->first()) : null;
    @endphp
    <div class="space-y-8">
        <div class="mb-6">
            <div class="bg-white border border-gray-200 rounded-lg shadow p-6 flex flex-col md:flex-row md:items-center md:gap-8 gap-4">
                <div class="flex-1">
                    <form method="POST" action="{{ $isEdit ? route('book.update', ['book' => $book->id]) : BookResource::getUrl('create') }}">
                        @csrf
                        @if($isEdit)
                            <input type="hidden" name="_method" value="PUT">
                        @endif
                        <div class="flex flex-col md:flex-row md:items-center gap-4">
                            <div class="flex-1 min-w-0">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Titel') }}</label>
                                <input type="text" name="title" value="{{ old('title', $book->title ?? '') }}" class="filament-input w-full rounded-lg text-lg py-3" required />
                            </div>
                            <div class="flex flex-row flex-shrink-0 gap-4 items-end justify-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Patient') }}</label>
                                @if($isEdit)
                                    @if($patient)
                                        <a href="{{ route('filament.admin.resources.patients.edit', $patient->id) }}" class="text-primary-600 underline" target="_blank">{{ $patient->name }}</a>
                                    @else
                                        <div class="py-2">-</div>
                                    @endif
                                @else
                                    <select name="patient_id" class="filament-input w-full rounded-lg" required>
                                        <option value="">{{ __('Bitte wählen') }}</option>
                                        @foreach(\App\Models\User::where('role', 'patient')->get() as $p)
                                            <option value="{{ $p->id }}" @if(old('patient_id', $book->patient_id ?? null) == $p->id) selected @endif>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Analyse') }}</label>
                                @php $bookAnalysis = $book && $book->analysis ? $book->analysis : $analysis; @endphp
                                @if($bookAnalysis)
                                    <a href="{{ route('filament.admin.resources.analyses.edit', $bookAnalysis->id) }}" class="text-primary-600 underline" target="_blank">
                                        {{ $bookAnalysis->sample_code ?? (__('Analyse') . ' #' . $bookAnalysis->id) }}
                                    </a>
                                @else
                                    <span class="py-2 text-gray-400">{{ __('Keine Analyse gefunden') }}</span>
                                @endif
                            </div>
                            <div x-data="{ status: @js($book->status ?? 'Warten auf Versand') }"
                                 x-init="window.addEventListener('bookStatusUpdated', e => { if (e.detail.id == @js($book->id)) status = e.detail.status });
                                          window.addEventListener('bookRecipesChanged', () => { $wire.$refresh() });">
                                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('Status') }}</label>
                                @php
                                    $statusColors = [
                                        'Versendet' => 'bg-green-100 text-green-800',
                                        'Warten auf Versand' => 'bg-blue-100 text-blue-800',
                                        'Geändert nach Versand' => 'bg-yellow-100 text-yellow-800',
                                    ];
                                @endphp
                                <span :class="{
                                    'bg-green-100 text-green-800': status === 'Versendet',
                                    'bg-blue-100 text-blue-800': status === 'Warten auf Versand',
                                    'bg-yellow-100 text-yellow-800': status === 'Geändert nach Versand',
                                    'bg-gray-100 text-gray-800': !['Versendet','Warten auf Versand','Geändert nach Versand'].includes(status)
                                }" class="inline-block px-3 py-1 rounded-full text-xs font-semibold" x-text="status"></span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">{{ $isEdit ? __('Speichern') : __('Buch anlegen') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <x-filament::section heading="Rezepte im Buch" collapsible="true" class="max-h-[80vh] overflow-y-auto">
            @livewire('book-recipes-table', ['bookId' => $bookId])
        </x-filament::section>
        <x-filament::section heading="Favoriten" collapsible="true" class="max-h-[80vh] overflow-y-auto">
            @livewire('favorite-recipes-table', ['bookId' => $bookId])
        </x-filament::section>
        <x-filament::section heading="Verfügbare Rezepte" collapsible="true" class="max-h-[80vh] overflow-y-auto">
            @livewire('available-recipes-table', ['bookId' => $bookId])
        </x-filament::section>
    </div>
</x-filament-panels::page>
