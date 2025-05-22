<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $book->title ?? 'Rezeptbuch' }}</title>
    <style>
        @page { margin: 0; }
        @font-face {
            font-family: 'Roboto-Regular';
            src: url('/fonts/Roboto-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        @font-face {
            font-family: 'Roboto-SemiBoldItalic';
            src: url('/fonts/Roboto-SemiBoldItalic.ttf') format('truetype');
            font-weight: 600;
            font-style: italic;
        }
        html, body {
            width: 100%; height: 100%; margin: 0; padding: 0;
            font-family: 'Roboto-Regular', Helvetica, Arial, sans-serif;
        }
        .cover, .cover-inner {
            width: 210mm; height: 297mm;
            position: relative;
            display: flex; justify-content: center; align-items: center; text-align: center;
            margin: 0; padding: 0; page-break-inside: avoid;
            background: transparent;
        }
        .cover img {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; z-index: -1; margin: 0; padding: 0;
        }
        .cover-box { background: #FF6100; border-radius: 12pt; padding: 20pt; box-shadow: 0 4pt 8pt rgba(0,0,0,0.3); }
        .cover-sans { font-family: 'Roboto-Regular', Helvetica, sans-serif !important; }
        .cover-inner { background: transparent; page-break-after: always; }
        .content {
            width: 186mm;
            height: 297mm;
            position: relative;
            background: transparent;
            box-sizing: border-box;
            page-break-after: always;
            display: flex;
            flex-direction: column;
            padding-top: 0;
            padding-bottom: 0;
        }
        .content-even {
            margin: 5mm 15mm 0mm 10mm;
        }
        .content-odd {
            margin: 5mm 10mm 0mm 15mm;
        }
        .header, .footer {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: transparent;
            font-family: 'Roboto-SemiBoldItalic', Helvetica, Arial, sans-serif !important;
            font-size: 10pt;
            box-sizing: border-box;
            padding: 0;
            margin: 0;
        }
        .header {
            height: 15mm;
            margin-bottom: 5mm;
        }
        .footer {
            height: 15mm;
            margin-top: auto;
            padding-bottom: 0mm;
        }
        .header-logo {
            height: 1cm;
        }
        .header-category {
            font-size: 12pt;
            font-style: italic;
            font-weight: 600;
        }
        .footer-page {
            font-size: 12pt;
            font-style: italic;
            font-weight: 600;
        }
        .footer-left { justify-content: flex-start; }
        .footer-right { justify-content: flex-end; }
        .page-body {
            flex: 1 1 auto;
            padding-top: 0;
            padding-bottom: 0;
            height: auto;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            min-height: 0;
            margin-bottom: 5mm;
        }
        .recipe-content { margin-bottom: 10pt; }
        .section { background: #f9fafb; border-radius: 8pt; padding: 5pt; margin-bottom: 5pt; }
        h3 { font-size: 12pt; margin: 0 0 5pt; }
        h4 { font-size: 10pt; margin: 0 0 5pt; }
        p, li { font-size: 8pt; margin: 0 0 3pt; }
        ul, ol { padding-left: 15pt; margin: 0; }
        .imprint, .toc, .erlaeuterungen { page-break-before: always; }
        .no-image { height: 100pt; background: #e5e7eb; line-height: 100pt; text-align: center; color: #4b5563; }
        .debug { color: red; font-size: 8pt; }
        .toc-list {
            list-style: none;
            padding-left: 0;
            width: 66.67%;
            margin: 0 auto;
            background: #f9fafb;
            border-radius: 8pt;
            padding: 15pt;
        }
        .toc-item {
            margin-bottom: 8pt;
            font-size: 11pt;
            line-height: 1.4;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
        }
        .toc-item .page-number {
            font-weight: 600;
            margin-right: 15pt;
        }
        .toc-item .recipe-name {
            flex: 1;
        }
        .toc-chapter {
            font-size: 14pt;
            font-weight: 600;
            margin: 15pt 0 8pt 0;
            color: #8B0000;
        }
        .toc-chapter:first-child {
            margin-top: 0;
        }
        .impressum-image {
            max-width: 100mm;
            max-height: 100mm;
            margin: 0 auto 10mm auto;
            display: block;
            border-radius: 12pt;
        }
        .impressum-title-block {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex: 1 1 auto;
        }
        .impressum-title {
            margin-top: 15mm;
            margin-bottom: 10mm;
            text-align: center;
        }
        .section.impressum-section {
            width: 100%;
            max-width: 120mm;
            margin: 0 auto 0 auto;
        }
        .impressum-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }
        .titel-impressum {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        .masstabelle td, .masstabelle th { border: 1px solid #000; }
    </style>
</head>
<body>
<!-- Debug Recipes -->
@php
    \Log::debug('Template recipes count: ' . count($recipes));
    foreach ($recipes as $index => $recipe) {
        \Log::debug("Template recipe $index: " . json_encode([
            'title' => $recipe->title,
            'media_raw' => $recipe->media,
            'media_decoded' => is_string($recipe->media ?? null) ? json_decode($recipe->media, true) : (is_array($recipe->media ?? null) ? $recipe->media : []),
            'course' => $recipe->course,
            'ingredients' => $recipe->ingredients,
            'steps' => $recipe->steps,
        ]));
    }

    // Define categories and group recipes
    $categories = ['Vorspeise', 'Hauptgericht', 'Dessert'];
    $grouped = collect($recipes)->groupBy(function($r) use ($categories) {
        $allowed = $categories;
        $cats = is_string($r->category ?? null) ? collect(json_decode($r->category, true)) : (is_array($r->category ?? null) ? collect($r->category) : collect());
        return $cats->first(fn($cat) => in_array($cat, $allowed));
    });

    // Initialize page tracking arrays
    $pageNumbers = [
        'chapters' => []
    ];

    // First pass: calculate chapter page numbers
    $currentPage = 4; // Start after cover, inner cover, and impressum
    foreach($categories as $cat) {
        $catRecipes = $grouped->get($cat, collect());
        if ($catRecipes->isEmpty()) continue;

        // Add empty page if next would be odd
        if($currentPage % 2 != 0) {
            $currentPage++;
        }

        // Chapter intro page
        $pageNumbers['chapters'][$cat] = $currentPage;
        $currentPage++;

        // Skip recipe pages in calculation
        foreach($catRecipes as $recipe) {
            $currentPage++;
        }
    }
@endphp
@if ($recipes->isEmpty())
    <div class="debug">No recipes available in template</div>
@endif

<!-- Front Cover -->
<div class="cover">
    @php
        $randomRecipe = $recipes->shuffle()->first();
        $randomMedia = $randomRecipe ? (is_string($randomRecipe->media ?? null) ? json_decode($randomRecipe->media, true) : (is_array($randomRecipe->media ?? null) ? $randomRecipe->media : null)) : null;
        $randomImage = $randomMedia && !empty($randomMedia['preview']) ? $randomMedia['preview'][0] : null;
        $currentPage = 0; // Initialize page counter at 0 since cover pages don't count
    @endphp
    @if ($randomImage)
        <img src="{{ $randomImage }}" alt="Cover Image">
    @else
        <div class="no-image">No cover image available (URL: {{ $randomImage ?? 'None' }})</div>
    @endif
    <div class="cover-box">
        <span style="font-size: 18pt; color: #8B0000;">Allergenfreies</span><br>
        <span class="cover-sans" style="font-size: 24pt; font-weight: bold;">Rezeptbuch</span><br>
        <span class="cover-sans" style="font-size: 14pt; font-style: italic;">von {{ $book->patient->name ?? 'Unbekannt' }}</span>
    </div>
</div>

<!-- Inner Front Cover (white page) -->
<div class="cover-inner"></div>

<!-- Impressum as first content page -->
@php $currentPage = 1; @endphp
<div class="content content-odd" style="page-break-inside: avoid; page-break-after: always;">
    <div class="header">

            <span class="header-category">&nbsp;</span>
            <img src="{{ asset('images/IFM-Logo.svg') }}" alt="IFM Logo" class="header-logo" />

    </div>
    <div class="page-body">
        <div class="titel-impressum">
            <div class="impressum-title-block">
                @if ($randomImage)
                    <img src="{{ $randomImage }}" alt="Impressum Bild" class="impressum-image">
                @else
                    <div class="no-image">No back cover image available (URL: {{ $randomImage ?? 'None' }})</div>
                @endif
                <div class="impressum-title">
                    <span style="font-size: 18pt; color: #8B0000;">Allergenfreies</span><br>
                    <span class="cover-sans" style="font-size: 24pt; font-weight: bold;">Rezeptbuch</span><br>
                    <span class="cover-sans" style="font-size: 14pt; font-style: italic;">von {{ $book->patient->name ?? 'Unbekannt' }}</span>
                </div>
            </div>
            <div class="section impressum-section">
                <h3>Impressum</h3>
                <p>Medizinisches Versorgungszentrum Institut für Mikroökologie GmbH </p>
                <p>Auf den Lüppen 8 </p>
                <p>35745 Herborn</p>
                <p>Telefon: 02772 9810 </p>
                <p>E-Mail: <a href="mailto:info@ifm-herborn.de">info@ifm-herborn.de</a></p>
                <p><strong>Haftungsausschluss:</strong> Die Rezepte sind allergenfrei zusammengestellt, aber individuelle Allergien sollten geprüft werden. Der Herausgeber übernimmt keine Haftung für allergische Reaktionen.</p>
            </div>
        </div>
    </div>
    <div class="footer footer-right">
        <h4><em>1</em></h4>
    </div>
</div>


<!-- Table of Contents -->
@php $currentPage++; @endphp
<div class="content {{ $currentPage % 2 == 0 ? 'content-even' : 'content-odd' }}">
    <div class="header">
        @if($currentPage % 2 == 0)
            <span class="header-category">Inhaltsverzeichnis</span>
            <img src="{{ asset('images/IFM-Logo.svg') }}" alt="IFM Logo" class="header-logo" />
        @else
            <img src="{{ asset('images/IFM-Logo.svg') }}" alt="IFM Logo" class="header-logo" />
            <span class="header-category">Inhaltsverzeichnis</span>
        @endif
    </div>
    <div class="page-body">
        <div class="section">
            @if ($recipes->isEmpty())
                <p>Keine Rezepte verfügbar</p>
            @else
                <ul class="toc-list">
                    @foreach($categories as $cat)
                        @php
                            $catRecipes = $grouped->get($cat, collect());
                            if ($catRecipes->isEmpty()) continue;
                            $catPlural = ['Vorspeise' => 'Vorspeisen', 'Hauptgericht' => 'Hauptgerichte', 'Dessert' => 'Desserts'][$cat] ?? $cat;
                        @endphp
                        <li class="toc-chapter">
                            <span class="recipe-name">{{ $catPlural }}</span>
                        </li>
                        @foreach($catRecipes as $recipe)
                            <li class="toc-item">
                                <span class="recipe-name">{{ $recipe->title ?? 'Unbekannt' }}</span>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
    <div class="footer {{ $currentPage % 2 == 0 ? 'footer-left' : 'footer-right' }}">
        <span class="footer-page"><h4><em>{{ $currentPage }}</em></h4></span>
    </div>
</div>

<!-- Erläuterungen -->
@php $currentPage++; // Increment for Erläuterungen page @endphp
<div class="content {{ $currentPage % 2 == 0 ? 'content-even' : 'content-odd' }}">
    <div class="header">
        @if($currentPage % 2 == 0)
            <span class="header-category">Erläuterungen</span>
            <img src="{{ asset('images/IFM-Logo.svg') }}" alt="IFM Logo" class="header-logo" />
        @else
            <img src="{{ asset('images/IFM-Logo.svg') }}" alt="IFM Logo" class="header-logo" />
            <span class="header-category">Erläuterungen</span>
        @endif
    </div>
    <div class="page-body">
        <div class="section">

            <h2>Sehr geehrte(r) {{ $book->patient->name ?? 'PatientIn' }},</h2>

            <p class="mt-8">Sie halten Ihr persönliches Kochbuch in den Händen, das Ihnen eine Anregung für den Einstieg in Ihre neue kulinarische Welt gibt. Zur Benutzung der Rezepte noch ein paar Erläuterungen:</p>

            <h4>Gewichtsangaben:</h4>
            <p>Zur Berechnung des Nährwertes der einzelnen Rezepte sind die mengenmäßig wichtigsten Zutaten mit Gewichtsangaben versehen. Die üblichen Bezeichnungen, wie Esslöffel, Teelöffel, Tasse oder Bund sind daher in Gramm oder Milliliter umgerechnet angegeben. Die folgende Tabelle gibt Ihnen einen Überblick über die Verwendung der Maßangaben:</p>

            <h4 class="mt-8">Nährwertangaben pro Portion</h4>
            <table class="masstabelle" style="width:100%; border-collapse: collapse; font-size: 8pt; margin-bottom: 8pt;">
                <thead>
                    <tr>
                        <th style="padding:2pt;">Menge</th>
                        <th style="padding:2pt;">Einheit</th>
                        <th style="padding:2pt;">Zutat</th>
                        <th style="padding:2pt;">Gewicht</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td style="padding:2pt;">1</td><td style="padding:2pt;">TL</td><td style="padding:2pt;">Zucker</td><td style="padding:2pt;">7 g</td></tr>
                    <tr><td>1</td><td>EL</td><td>Zucker</td><td>14 g</td></tr>
                    <tr><td>1</td><td>TL</td><td>Mehl</td><td>7 g</td></tr>
                    <tr><td>1</td><td>EL</td><td>Mehl</td><td>14 g</td></tr>
                    <tr><td>1</td><td>TL</td><td>Flüssigkeit (Öl, Wasser, Essig)</td><td>3 ml</td></tr>
                    <tr><td>1</td><td>EL</td><td>Flüssigkeit (Öl, Wasser, Essig)</td><td>7 ml</td></tr>
                    <tr><td>1</td><td>Pkg</td><td>Trockenehefe</td><td>7 g</td></tr>
                    <tr><td>1</td><td>Pkg</td><td>Vanillezucker</td><td>8 g</td></tr>
                    <tr><td>1</td><td>Pkg</td><td>Backpulver</td><td>16 g</td></tr>
                    <tr><td>1</td><td>Blatt</td><td>Gelatine</td><td>2 g</td></tr>
                    <tr><td>1</td><td>ganze</td><td>Vanilleschote</td><td>3 g</td></tr>
                    <tr><td>1</td><td>mittlere</td><td>Kartoffel</td><td>130 g</td></tr>
                    <tr><td>1</td><td>mittlere</td><td>Zwiebel</td><td>100 g</td></tr>
                    <tr><td>1</td><td>mittlere</td><td>Tomate</td><td>140 g</td></tr>
                    <tr><td>1</td><td>mittlere</td><td>Zehe Knoblauch</td><td>5 g</td></tr>
                    <tr><td>1</td><td>mittleres</td><td>Ei</td><td>65 g</td></tr>
                    <tr><td>1</td><td>mittlere</td><td>Zitrone</td><td>100 g</td></tr>
                    <tr><td>1</td><td>mittlere</td><td>Orange</td><td>200 g</td></tr>
                </tbody>
            </table>

            <h4 class="mt-8">Pfeffer & Öl:</h4>
            <p>Pfeffer kommt in nahezu jedem Gericht vor. Aber Pfeffer ist nicht gleich Pfeffer – es gibt eine vielfältige Auswahl von verschiedenen schärfenden Gewürzen. Diese sind z.B. Schwarzer und weißer Pfeffer, Cayenne-Pfeffer, Roter Pfeffer, bunter Pfeffer, Chili oder Peperoni etc., die sich beliebig durch einander ersetzen lassen. In diesem Rezeptbuch finden Sie deshalb in der Zutatenliste Pfeffer als allgemeine Angabe. Das gleiche gilt für die allgemeine Angabe "ÖL" in der Zutatenliste. Sie können je nach Verträglichkeit verschiedene Öle im Rotationsprinzip verwenden, z. B. Olivenöl, Sesamöl, Maiskeimöl, Kürbiskernöl, Sojaöl oder Sonnenblumenöl.</p>

            <h4 class="mt-8">Butter und Sahne:</h4>
            <p>Bei einer Allergie Typ III auf Kuhmilch der Stärke 0 und 1 können verschiedene Produkte wie Sojasahne oder Margarine durch Butter und Sahne ersetzt werden. Sie haben dadurch die Möglichkeit die Rotation zu erweitern.</p>

            <h4 class="mt-8">Glutenfreie Produkte:</h4>
            <p>Viele Fertigprodukte wie Nudeln oder Brot bestehen aus einer Vielzahl von Zutaten, wie Reis, Mais, Soja, Erbsen oder Linsen. Beim Kauf solcher Produkte sollten Sie deshalb auf die Zusammensetzung achten, um eventuell vorkommende unverträgliche Zutaten auszuschließen. Bitte verwenden Sie überwiegend sortenreine Produkte (z.B. nur aus Reis oder Mais), um die Rotation bestmöglich gestalten zu können. Aufgrund der Fülle der Produkte kann hierfür keine Nährwertangabe gemacht werden.</p>

            <p class="mt-8">Nun wünschen wir Ihnen viel Erfolg und Spaß beim Kochen und vor allem beim Essen.</p>
        </div>
    </div>
    <div class="footer {{ $currentPage % 2 == 0 ? 'footer-left' : 'footer-right' }}">
        <span class="footer-page"><h4><em>{{ $currentPage }}</em></h4></span>
    </div>
</div>

<!-- Recipes -->
@foreach($categories as $cat)
    @php
        $catRecipes = $grouped->get($cat, collect());
        if ($catRecipes->isEmpty()) continue;
        $randomRecipe = $catRecipes->shuffle()->first();
        $randomMedia = $randomRecipe ? (is_string($randomRecipe->media ?? null) ? json_decode($randomRecipe->media, true) : (is_array($randomRecipe->media ?? null) ? $randomRecipe->media : null)) : null;
        $randomImage = null;
        if ($randomMedia) {
            if (!empty($randomMedia['preview_no_wm'])) {
                $randomImage = $randomMedia['preview_no_wm'][0];
            }
            elseif (!empty($randomMedia['preview'])) {
                $randomImage = $randomMedia['preview'][0];
            }
        }
        $catPlural = ['Vorspeise' => 'Vorspeisen', 'Hauptgericht' => 'Hauptgerichte', 'Dessert' => 'Desserts'][$cat] ?? $cat;
    @endphp

    <!-- Add empty page if next page would be odd -->
    @if($currentPage % 2 != 0)
        @php $currentPage++; @endphp
        <div class="content {{ $currentPage % 2 == 0 ? 'content-even' : 'content-odd' }}">
            <div class="header">
                @if($currentPage % 2 == 0)
                    <span class="header-category">Notizen</span>
                    <img src="{{ asset('images/IFM-Logo.svg') }}" alt="IFM Logo" class="header-logo" />
                @else
                    <img src="{{ asset('images/IFM-Logo.svg') }}" alt="IFM Logo" class="header-logo" />
                    <span class="header-category">Notizen</span>
                @endif
            </div>
            <div class="page-body">
                <!-- Empty page for notes -->
            </div>
            <div class="footer {{ $currentPage % 2 == 0 ? 'footer-left' : 'footer-right' }}">
                <span class="footer-page"><h4><em>{{ $currentPage }}</em></h4></span>
            </div>
        </div>
    @endif

    <!-- Chapter Intro Page -->
    @php $currentPage++; @endphp
    <div class="cover" style="page-break-after: always;">
        @if ($randomImage)
            <img src="{{ $randomImage }}" alt="Chapter Image">
        @endif
        <div class="cover-box">
            <span class="cover-sans" style="font-size: 32pt; font-weight: bold;"><em>{{ $catPlural }}</em></span>
        </div>
    </div>

    <!-- Recipes for this category -->
    @foreach($catRecipes as $recipe)
        @php
            $media_decoded = is_string($recipe->media ?? null) ? json_decode($recipe->media, true) : (is_array($recipe->media ?? null) ? $recipe->media : []);
            $cats = is_string($recipe->category ?? null) ? collect(json_decode($recipe->category, true)) : (is_array($recipe->category ?? null) ? collect($recipe->category) : collect());
            $categoriesArr = is_string($recipe->category ?? null) ? collect(json_decode($recipe->category, true)) : (is_array($recipe->category ?? null) ? collect($recipe->category) : collect());
        @endphp
        @php $currentPage++; @endphp
        <div class="content {{ $currentPage % 2 == 0 ? 'content-even' : 'content-odd' }}">
            <div class="header">
                @php
                    $headerCategory = $categoriesArr->first(function($cat) use ($cats) {
                        return $cats->contains($cat);
                    });
                    $headerCategoryPlural = ['Vorspeise' => 'Vorspeisen', 'Hauptgericht' => 'Hauptgerichte', 'Dessert' => 'Desserts'][$headerCategory] ?? $headerCategory;
                @endphp
                @if($currentPage % 2 == 0)
                    <span class="header-category">{{ $headerCategoryPlural ?? '' }}</span>
                    <img src="{{ asset('images/IFM-Logo.svg') }}" alt="IFM Logo" class="header-logo" />
                @else
                    <img src="{{ asset('images/IFM-Logo.svg') }}" alt="IFM Logo" class="header-logo" />
                    <span class="header-category">{{ $headerCategoryPlural ?? '' }}</span>
                @endif
            </div>
            <div class="page-body">
                @include('filament.resources.recipe-resource.view-recipe-pdf', ['recipe' => $recipe, 'book' => $book])
            </div>
            <div class="footer {{ $currentPage % 2 == 0 ? 'footer-left' : 'footer-right' }}">
                <span class="footer-page"><h4><em>{{ $currentPage }}</em></h4></span>
            </div>
        </div>
    @endforeach
@endforeach



<!-- Inner Back Cover (white page) -->
<div class="cover-inner"></div>

<!-- Back Cover (must be last element in <body>) -->
<div class="cover">
    @if ($randomImage)
        <img src="{{ $randomImage }}" alt="Back Cover Image">
    @else
        <div class="no-image">No back cover image available (URL: {{ $randomImage ?? 'None' }})</div>
    @endif
</div>
</body>
</html>

