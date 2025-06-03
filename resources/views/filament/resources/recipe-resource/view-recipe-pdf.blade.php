<div class="recipe-content">
    <style>
    @font-face {
        font-family: 'Roboto-Regular';
        src: url('/fonts/Roboto-Regular.ttf') format('truetype');
        font-weight: normal;
        font-style: normal;
    }
    .recipe-content {
        width: 100%;
        margin: 0;
        font-family: 'Roboto-Regular', Helvetica, Arial, sans-serif;
        background: transparent;
        font-size: 9pt;
    }
    .recipe-header {
        display: grid;
        grid-template-columns: 1fr 62mm;
        align-items: stretch;
        gap: 6mm;
        margin-bottom: 4mm;
        width: 100%;
    }
    .recipe-header-left {
        background: #FF6100;
        border-radius: 12pt;
        padding: 12pt 16pt 12pt 16pt;
        color: #fff;
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
    }
    .recipe-header-right {
        display: flex;
        align-items: stretch;
        justify-content: flex-end;
        background: none;
    }
    .recipe-image {
        width: 62mm;
        height: 48mm;
        object-fit: cover;
        border-radius: 12pt;
        background: #eee;
    }
    .recipe-title {
        font-size: 16pt;
        font-weight: bold;
        margin-bottom: 10pt;
        color: #fff;
    }
    .recipe-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6pt;
        margin-bottom: 10pt;
    }
    .recipe-tag {
        background: #fff;
        color: #FF6100;
        border-radius: 12pt;
        padding: 2pt 10pt;
        font-size: 9pt;
        font-weight: 500;
        display: inline-block;
    }
    .recipe-meta {
        font-size: 9pt;
        margin-bottom: 2pt;
        color: #fff;
    }
    .recipe-main {
        display: grid;
        grid-template-columns: 0.7fr 1fr;
        gap: 4mm;
    }
    .recipe-main-left, .recipe-main-right {
        display: flex;
        flex-direction: column;
        gap: 4mm;
    }
    .card {
        background: transparent;
        border-radius: 8pt;
        box-shadow: none;
        padding: 7pt 10pt 7pt 10pt;
        margin: 0;
        border: 1px solid #eee;
    }
    .card-orange-title {
        color: #FF6100;
        font-size: 12pt;
        font-weight: bold;
        margin-bottom: 6pt;
    }
    .nutrients-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 3mm;
    }
    .substances-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 2mm;
        margin-top: 2mm;
    }
    .substance-box {
        background: #FFF3E0;
        color: #FF6100;
        border-radius: 6pt;
        padding: 3pt 4pt;
        font-size: 8pt;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }
    .substance-name {
        font-weight: bold;
        margin-bottom: 1pt;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .substance-value {
        color: #666;
    }
    .nutrient-box {
        background: #FFF3E0;
        color: #FF6100;
        border-radius: 6pt;
        text-align: center;
        font-size: 9pt;
        font-weight: bold;
        padding: 4pt 0 2pt 0;
        margin-bottom: 0;
        min-width: 18mm;
        min-height: 14mm;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .nutrient-label {
        font-size: 9pt;
        font-weight: normal;
        color: #FF6100;
        margin-bottom: 1pt;
    }
    .nutrient-value {
        font-size: 9pt;
        font-weight: bold;
        color: #FF6100;
    }
    .recipe-times-allergens-row {
        display: grid;
        grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
        gap: 4mm;
        width: 100%;
        box-sizing: border-box;
    }
    .card-times, .card-allergens {
        width: 100%;
        min-width: 0;
        box-sizing: border-box;
    }
    .times-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 9pt;
        table-layout: fixed;
    }
    .times-table td, .times-table th {
        padding: 1pt 4pt;
        border: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .times-table .total-row {
        font-weight: bold;
    }
    .allergens-list {
        font-size: 9pt;
        margin: 0;
        padding-left: 0;
        list-style: none;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .steps-list {
        counter-reset: step;
        margin: 0;
        padding-left: 0;
    }
    .step-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 5pt;
    }
    .step-number {
        background: #FF6100;
        color: #fff;
        border-radius: 50%;
        width: 12pt;
        height: 12pt;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 9pt;
        font-weight: bold;
        margin-right: 7pt;
        flex-shrink: 0;
    }
    .step-text {
        font-size: 9pt;
        color: #333;
    }
    .ingredient-part {
        font-weight: bold;
        margin-top: 6pt;
        margin-bottom: 2pt;
        font-size: 9pt;
        list-style: none;
        padding-left: 0;
    }
    .ingredients-list {
        list-style: disc;
        padding-left: 16pt;
        margin: 0;
    }
    .substances-list {
        font-size: 9pt;
        color: #666;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    .substances-list li {
        margin-bottom: 1pt;
    }
    .nutrients-compact-table {
        width: 100%;
        max-width: 70mm;
        border-collapse: collapse;
        font-size: 6.5pt;
        margin: 0;
        margin-bottom: 1mm;
        line-height: 1.05;
        table-layout: fixed;
        word-break: break-word;
    }
    .nutrients-compact-table th, .nutrients-compact-table td {
        padding: 0.5pt 2pt;
        border: none;
        line-height: 1.05;
        word-break: break-word;
    }
    .nutrients-compact-table th {
        background: #FF6100;
        color: #fff;
        font-weight: bold;
        font-size: 7pt;
    }
    .nutrients-compact-table tr {
        break-inside: avoid;
    }
    .nutrients-compact-table tr:nth-child(even) {
        background: #FFF5ED;
    }
    .nutrients-compact-table tr:nth-child(odd) {
        background: #fff;
    }
    </style>

    @php
        // Normalize recipe data for both array and object
        $data = $recipe ?? $record ?? null;
        if (!$data) {
            throw new Exception('No recipe provided to view-recipe-pdf.');
        }
        if (is_object($data) && get_class($data) === 'stdClass') {
            $data = (array) $data;
        }
        // Helper for JSON fields
        $normalizeField = function($value) {
            return is_string($value) ? json_decode($value, true) : (is_array($value) ? $value : []);
        };
        // Course map
        $courseMap = [
            'starter' => 'Vorspeise',
            'main_course' => 'Hauptgericht',
            'dessert' => 'Dessert',
        ];
        $course = $data['course'] ?? $data->course ?? null;
        $categories = is_string($data['category'] ?? null) ? collect(json_decode($data['category'] ?? '[]', true))->filter() : (is_array($data['category'] ?? null) ? collect($data['category'])->filter() : collect());
        $diets = is_string($data['diets'] ?? null) ? json_decode($data['diets'] ?? '[]', true) : (is_array($data['diets'] ?? null) ? $data['diets'] : []);
        $presentDiets = !empty($diets) ? collect($diets)->filter(fn($diet) => $diet['value'] ?? false)->pluck('diet')->all() : [];
        $country = $data['country'] ?? $data->country ?? null;
        $media = is_string($data['media'] ?? null) ? json_decode($data['media'] ?? '[]', true) : (is_array($data['media'] ?? null) ? $data['media'] : []);
        $previewImageUrl = !empty($media['preview_no_wm']) ? $media['preview_no_wm'][0] : (!empty($media['preview']) ? $media['preview'][0] : null);
        $ingredients = is_string($data['ingredients'] ?? null) ? json_decode($data['ingredients'] ?? '[]', true) : (is_array($data['ingredients'] ?? null) ? $data['ingredients'] : []);
        $substances = is_string($data['substances'] ?? null) ? collect(json_decode($data['substances'] ?? '[]', true)) : (is_array($data['substances'] ?? null) ? collect($data['substances']) : collect());
        $filteredSubstances = $substances->filter(function($s) {
            return isset($s['value']) && $s['value'] !== '' && $s['value'] !== null;
        });
        $times = is_string($data['time'] ?? null) ? json_decode($data['time'] ?? '[]', true) : (is_array($data['time'] ?? null) ? $data['time'] : []);
        $totalTime = collect($times)->firstWhere('timetype', 'Gesamt')['value'] ?? 'Unbekannt';
        $q1 = $data['yield_quantity_1'] ?? $data->yield_quantity_1 ?? null;
        $q2 = $data['yield_quantity_2'] ?? $data->yield_quantity_2 ?? null;
        $info = $data['yield_info'] ?? $data->yield_info ?? null;
        $externalId = $data['id_external'] ?? $data->id_external ?? null;
        $allergens = is_string($data['allergens'] ?? null) ? json_decode($data['allergens'] ?? '[]', true) : (is_array($data['allergens'] ?? null) ? $data['allergens'] : []);
        $presentAllergens = !empty($allergens) ? collect($allergens)
            ->filter(fn($a) => ($a['value'] ?? false) && !str_starts_with($a['allergen'] ?? '', 'pro_'))
            ->pluck('allergen')
            ->all() : [];
        $steps = is_string($data['steps'] ?? null) ? json_decode($data['steps'] ?? '[]', true) : (is_array($data['steps'] ?? null) ? $data['steps'] : []);
        // Nutrients
        $nutrientList = [
            'Ballaststoffe' => 'g',
            'Calcium' => 'mg',
            'Eisen' => 'mg',
            'Eiweiß (Protein)' => 'g',
            'Energie (Kilojoule)' => 'kJ',
            'Energie (Kilokalorien)' => 'kcal',
            'Fett' => 'g',
            'Kohlenhydrate, resorbierbar' => 'g',
            'Vitamin A Beta-Carotin' => 'µg',
            'Vitamin B1 Thiamin' => 'mg',
            'Vitamin B6 Pyridoxin' => 'mg',
            'Vitamin B9 gesamte Folsäure' => 'µg',
            'Vitamin C Ascorbinsäure' => 'mg',
            'Vitamin D Calciferole' => 'µg',
            'Zink' => 'mg',
            'Zucker (gesamt)' => 'g',
        ];
        // Ordered times for table
        $timesCol = collect($times);
        $orderedTimes = collect();
        $vorbereitung = $timesCol->firstWhere('timetype', 'Vorbereitung');
        $gesamtzeit = $timesCol->firstWhere('timetype', 'Gesamt');
        if ($vorbereitung) $orderedTimes->push($vorbereitung);
        foreach ($timesCol as $time) {
            if (($time['timetype'] ?? null) !== 'Vorbereitung' && ($time['timetype'] ?? null) !== 'Gesamt') $orderedTimes->push($time);
        }
        if ($gesamtzeit) $orderedTimes->push($gesamtzeit);
        // Determine book/patient locale
        $bookLocale = 'de';
        if (isset($book) && isset($book->patient) && isset($book->patient->settings['language'])) {
            $bookLocale = $book->patient->settings['language'] ?: 'de';
        } elseif (isset($patient) && isset($patient->settings['language'])) {
            $bookLocale = $patient->settings['language'] ?: 'de';
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
    @endphp
    <div class="recipe-header">
        <div class="recipe-header-left">
            <div class="recipe-title">{{ $data['title'] ?? $data->title ?? 'Unbekannt' }}</div>
            <div class="recipe-tags">
                @php
                    $showCourse = $course && isset($courseMap[$course]);
                    $catLower = $categories->map(fn($c) => mb_strtolower($c));
                    $courseLabel = $showCourse ? $courseMap[$course] : null;
                    $hasCourseInCategory = $courseLabel && $catLower->contains(mb_strtolower($courseLabel));
                @endphp
                @if($showCourse)
                    <span class="recipe-tag">{{ $courseLabel }}</span>
                @endif
                @if(!$hasCourseInCategory && $categories->count())
                    <span class="recipe-tag">{{ $categories->first() }}</span>
                @endif
                @foreach($presentDiets as $diet)
                    <span class="recipe-tag">
                        @if($bookLocale === 'de' && $diet === 'alcohol-free')
                            {{ 'ohne Alkohol' }}
                        @else
                            {{ $diet }}
                        @endif
                    </span>
                @endforeach
                @if($country)
                    <span class="recipe-tag">{{ $country }}</span>
                @endif
            </div>
            <div class="recipe-meta" style="display: flex; gap: 16pt; align-items: center;">
                <span>
                    <strong>Zeit:</strong> {{ $totalTime === 'Unbekannt' ? 'Unbekannt' : $totalTime . ' min' }}
                </span>
                <span>
                    <strong>Portionen:</strong>
                    @if($q1 && $q2 && $q1 != $q2)
                        {{ $q1 }} - {{ $q2 }}
                    @elseif($q1)
                        {{ $q1 }}
                    @else
                        -
                    @endif
                    @if($info)
                        <span class="text-xs text-gray-200 font-semibold">{{ $info }}</span>
                    @endif
                </span>
                @if($externalId)
                    <span><strong>ID:</strong> {{ $externalId }}</span>
                @endif
            </div>
        </div>
        <div class="recipe-header-right">
            @if ($previewImageUrl)
                <img src="{{ $previewImageUrl }}" alt="{{ $data['title'] ?? $data->title ?? 'Bild' }}" class="recipe-image">
            @else
                <div class="recipe-image" style="display:flex;align-items:center;justify-content:center;color:#aaa;">Kein Bild</div>
            @endif
        </div>
    </div>
    <div class="recipe-main">
        <div class="recipe-main-left">
            <div class="card">
                <div class="card-orange-title">Zutaten</div>
                @if (!empty($ingredients))
                    @php $lastPart = null; @endphp
                    @foreach ($ingredients as $ingredient)
                        @if (!empty($ingredient['part_text']) && $ingredient['part_text'] !== $lastPart)
                            @php $lastPart = $ingredient['part_text']; @endphp
                            <div class="ingredient-part">{{ $ingredient['part_text'] }}</div>
                        @endif
                        <ul class="ingredients-list">
                            <li>{{ $ingredient['quantity1'] ?? '' }} {{ $ingredient['unit'] ?? '' }} {{ $ingredient['product'] ?? 'Unknown' }}</li>
                        </ul>
                    @endforeach
                @else
                    <p>Keine Zutaten</p>
                @endif
            </div>
            <div class="card">
                <div class="card-orange-title">Nährwerte pro Portion</div>
                <table class="nutrients-compact-table">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Nährstoff</th>
                            <th style="text-align:right;">Menge</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(array_keys($nutrientList) as $i => $nutrient)
                        @php
                                $unit = $nutrientList[$nutrient];
                                $substance = $substances->first(function($s) use ($nutrient) {
                                    return isset($s['substance']) && stripos($s['substance'], strtok($nutrient, ' (')) !== false;
                                });
                                $value = $substance['portion']['amount'] ?? $substance['value'] ?? null;
                                $value = is_numeric($value) ? number_format($value, 1) : '–';
                        @endphp
                            <tr>
                                <td>{{ $nutrient }}</td>
                                <td style="text-align:right;">{{ $value }} {{ $unit }}</td>
                            </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="recipe-main-right">
            <div class="recipe-times-allergens-row">
                <div class="card card-times">
                    <div class="card-orange-title">Zubereitungszeiten</div>
                    <table class="times-table">
                        <tbody>
                            @foreach($orderedTimes as $time)
                                <tr class="{{ ($time['timetype'] ?? null) === 'Gesamt' ? 'total-row' : '' }}">
                                    <td>{{ $time['timetype'] ?? '' }}:</td>
                                    <td>{{ $time['value'] ?? '–' }} min</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="card card-allergens">
                    <div class="card-orange-title">Allergien</div>
                    @if (!$filteredAllergens->isEmpty())
                        <div class="text-xs text-gray-900 mb-2" style="font-size: 7pt;">
                            {{ implode(', ', $filteredAllergens->map(fn($a) => $allowedAllergens[$a][$bookLocale] ?? $allowedAllergens[$a]['de'])->all()) }}
                        </div>
                    @else
                        <p>Keine</p>
                    @endif
                </div>
            </div>
            <div class="card">
                <div class="card-orange-title">Zubereitung</div>
                @if (!empty($steps))
                    <ol class="steps-list">
                        @foreach ($steps as $i => $step)
                            <li class="step-item">
                                <span class="step-number">{{ $i+1 }}</span>
                                <span class="step-text">{{ $step['step_text'] ?? 'Unknown' }}</span>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p>Keine Schritte</p>
                @endif
            </div>
        </div>
    </div>
</div>
