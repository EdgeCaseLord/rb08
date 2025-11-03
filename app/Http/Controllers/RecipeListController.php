<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Recipe;
use App\Models\User;
use App\Services\CookButlerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;

class RecipeListController extends Controller
{
    private function mapRecipeMinimal($r): array
    {
        // Accept multiple shapes: Eloquent Recipe, flat array, or CookButler API shape { id: { recipe: {...}, optional: {...} } }
        if ($r instanceof Recipe) {
            $arr = $r->toArray();
        } else {
            $arr = is_array($r) ? $r : (array)$r;
        }

        // If CookButler API shape detected
        if (isset($arr['recipe']) && is_array($arr['recipe'])) {
            $rec = $arr['recipe'];
            $opt = isset($arr['optional']) && is_array($arr['optional']) ? $arr['optional'] : [];
            // Merge top-level and recipe for robust field access
            $merged = array_merge($arr, $rec);

            // Media/thumbnail: prefer top-level media
            $mediaRaw = $merged['media'] ?? [];
            $media = is_string($mediaRaw) ? (json_decode($mediaRaw, true) ?: []) : (is_array($mediaRaw) ? $mediaRaw : []);
            $thumb = null;
            if (!empty($media['search'])) {
                $thumb = is_array($media['search']) ? ($media['search'][0] ?? null) : $media['search'];
            } elseif (!empty($media['preview_no_wm'])) {
                $thumb = is_array($media['preview_no_wm']) ? ($media['preview_no_wm'][0] ?? null) : $media['preview_no_wm'];
            } elseif (!empty($media['preview'])) {
                $thumb = is_array($media['preview']) ? ($media['preview'][0] ?? null) : $media['preview'];
            }

            // Allergens: array of objects { allergen: string, value: bool }
            $allergens = [];
            $allRaw = $arr['allergens'] ?? ($rec['allergens'] ?? []);
            if (!empty($allRaw) && is_array($allRaw)) {
                foreach ($allRaw as $it) {
                    if (is_array($it)) {
                        $name = $it['allergen'] ?? null;
                        $val = $it['value'] ?? null;
                        if ($name && $val === true) $allergens[] = (string)$name;
                    } elseif (is_string($it)) {
                        // If API already gives strings, take them directly
                        $s = trim($it);
                        if ($s !== '') $allergens[] = $s;
                    }
                }
                $allergens = array_values(array_unique(array_filter($allergens)));
            }
            // Convert to [{allergen: name, value: true}]
            $allergenObjs = array_map(fn($n) => ['allergen' => $n, 'value' => true], $allergens);

            // Categories/diets: prefer top-level, then optional, then recipe
            $category = $this->normalizeArrayLabels($arr['category'] ?? ($opt['category'] ?? ($rec['category'] ?? [])));
            // Extract truthy diets
            $dietsRaw = $arr['diets'] ?? ($opt['diets'] ?? ($rec['diets'] ?? []));
            $dietNames = [];
            if (is_array($dietsRaw)) {
                foreach ($dietsRaw as $it) {
                    if (is_array($it)) {
                        $name = $it['diet'] ?? ($it['name'] ?? ($it['label'] ?? ($it['title'] ?? null)));
                        $val = $it['value'] ?? null;
                        if ($name && $val === true) $dietNames[] = (string)$name;
                    }
                }
            }
            $dietNames = array_values(array_unique(array_filter($dietNames)));
            $dietObjs = array_map(fn($n) => ['diet' => $n, 'value' => true], $dietNames);

            return [
                'id_recipe' => $rec['id_recipe'] ?? ($rec['id'] ?? null),
                'id_external' => $rec['id_external'] ?? null,
                'id' => $rec['id'] ?? ($rec['id_recipe'] ?? null),
                'title' => $rec['title'] ?? '',
                'media' => [ 'search' => $thumb ? [$thumb] : [] ],
                'images' => $thumb ? [$thumb] : [],
                'category' => $category,
                'diets' => $dietObjs,
                'allergens' => $allergenObjs,
            ];
        }

        // Flat/eloquent shape fallback
        $media = isset($arr['media']) ? (is_string($arr['media']) ? (json_decode($arr['media'], true) ?: []) : (is_array($arr['media']) ? $arr['media'] : [])) : [];
        $images = isset($arr['images']) ? (is_string($arr['images']) ? (json_decode($arr['images'], true) ?: []) : (is_array($arr['images']) ? $arr['images'] : [])) : [];

        $categoryRaw = isset($arr['category']) ? (is_string($arr['category']) ? (json_decode($arr['category'], true) ?: $arr['category']) : $arr['category']) : [];
        $dietsRaw = isset($arr['diets']) ? (is_string($arr['diets']) ? (json_decode($arr['diets'], true) ?: $arr['diets']) : $arr['diets']) : [];
        $allergensRaw = isset($arr['allergens']) ? (is_string($arr['allergens']) ? (json_decode($arr['allergens'], true) ?: $arr['allergens']) : $arr['allergens']) : [];

        $category = $this->normalizeArrayLabels($categoryRaw);
        // Truthy-only transform for diets/allergens if objects with value are present
        $dietNames = [];
        if (is_array($dietsRaw)) {
            foreach ($dietsRaw as $it) {
                if (is_array($it)) {
                    $name = $it['diet'] ?? ($it['name'] ?? ($it['label'] ?? ($it['title'] ?? null)));
                    $val = $it['value'] ?? null;
                    if ($name && $val === true) $dietNames[] = (string)$name;
                }
            }
        }
        $dietNames = array_values(array_unique(array_filter($dietNames)));
        $dietObjs = array_map(fn($n) => ['diet' => $n, 'value' => true], $dietNames);

        $allergenNames = [];
        if (is_array($allergensRaw)) {
            foreach ($allergensRaw as $it) {
                if (is_array($it)) {
                    $name = $it['allergen'] ?? ($it['name'] ?? null);
                    $val = $it['value'] ?? null;
                    if ($name && $val === true) $allergenNames[] = (string)$name;
                }
            }
        }
        $allergenNames = array_values(array_unique(array_filter($allergenNames)));
        $allergenObjs = array_map(fn($n) => ['allergen' => $n, 'value' => true], $allergenNames);

        $thumb = null;
        if (!empty($media['search'])) {
            $thumb = is_array($media['search']) ? ($media['search'][0] ?? null) : $media['search'];
        } elseif (!empty($media['preview_no_wm'])) {
            $thumb = is_array($media['preview_no_wm']) ? ($media['preview_no_wm'][0] ?? null) : $media['preview_no_wm'];
        } elseif (!empty($media['preview'])) {
            $thumb = is_array($media['preview']) ? ($media['preview'][0] ?? null) : $media['preview'];
        } elseif (!empty($images)) {
            $thumb = $images[0] ?? null;
        }

        return [
            'id_recipe' => $arr['id_recipe'] ?? null,
            'id_external' => $arr['id_external'] ?? null,
            'id' => $arr['id'] ?? null,
            'title' => $arr['title'] ?? '',
            'media' => [ 'search' => $thumb ? [$thumb] : [] ],
            'images' => $thumb ? [$thumb] : [],
            'category' => $category,
            'diets' => $dietObjs,
            'allergens' => $allergenObjs,
        ];
    }

    /**
     * Normalize various list shapes to an array of human-readable strings.
     * Supports:
     * - associative dict of booleans: return keys where value === true
     * - array of objects: use name|label|title|value
     * - array of strings: drop literal 'true'/'false'
     * - single object dict: treat as dict of booleans
     */
    private function normalizeArrayLabels($val): array
    {
        // decode strings like "[...]" already done by caller; keep safety here
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $val = $decoded;
            } else {
                $s = trim($val);
                if ($s !== '' && strtolower($s) !== 'true' && strtolower($s) !== 'false') {
                    return [$s];
                }
                return [];
            }
        }

        // single object treated as dict
        if (is_object($val)) {
            $val = (array)$val;
        }

        // dict of booleans
        if (is_array($val) && $this->isAssoc($val)) {
            $out = [];
            foreach ($val as $k => $v) {
                $kStr = (string)$k;
                if ($v === true && $kStr !== '' && strcasecmp($kStr, 'value') !== 0 && $kStr[0] !== ':') $out[] = $kStr;
            }
            return array_values(array_unique(array_filter($out)));
        }

        // list
        if (is_array($val)) {
            $out = [];
            foreach ($val as $item) {
                if (is_string($item)) {
                    $s = trim($item);
                    if ($s !== '' && strcasecmp($s, 'value') !== 0 && $s[0] !== ':' && strtolower($s) !== 'true' && strtolower($s) !== 'false') {
                        $out[] = $s;
                    }
                    continue;
                }
                if (is_bool($item)) continue;
                if (is_object($item)) $item = (array)$item;
                if (is_array($item)) {
                    // Special case: { allergen|diet: name, value: bool }
                    if ((array_key_exists('allergen', $item) || array_key_exists('diet', $item)) && array_key_exists('value', $item)) {
                        $k = isset($item['allergen']) ? (string)$item['allergen'] : (isset($item['diet']) ? (string)$item['diet'] : '');
                        if ($k !== '' && ($item['value'] === true)) {
                            $out[] = $k;
                            continue;
                        }
                    }
                    // Special case: { name|label|title: name, value: bool }
                    if (array_key_exists('value', $item)) {
                        $n = isset($item['name']) ? (string)$item['name'] : (isset($item['label']) ? (string)$item['label'] : (isset($item['title']) ? (string)$item['title'] : ''));
                        if ($n !== '' && ($item['value'] === true)) {
                            $out[] = $n;
                            continue;
                        }
                    }
                    // object-like (do not use 'value' as a display name)
                    $name = $item['name'] ?? ($item['label'] ?? ($item['title'] ?? ($item['allergen'] ?? ($item['diet'] ?? null))));
                    if (is_string($name) && $name !== '' && strcasecmp($name, 'value') !== 0 && $name[0] !== ':' && strtolower($name) !== 'true' && strtolower($name) !== 'false') {
                        $out[] = $name;
                        continue;
                    }
                    // dict boolean fallback
                    foreach ($item as $k => $v) {
                        $kStr = (string)$k;
                        if ($v === true && $kStr !== '' && strcasecmp($kStr, 'value') !== 0 && $kStr[0] !== ':') $out[] = $kStr;
                    }
                }
            }
            return array_values(array_unique(array_filter($out)));
        }

        return [];
    }

    private function isAssoc(array $arr): bool
    {
        if ([] === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public function bookRecipes(Request $request, Book $book)
    {
        $perPage = max(1, min(60, (int)$request->get('perPage', 24)));
        $page = max(1, (int)$request->get('page', 1));
        $query = $book->recipes()->orderBy('title');
        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn($r) => $this->mapRecipeMinimal($r))->all();
        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public function favorites(Request $request)
    {
        $perPage = max(1, min(60, (int)$request->get('perPage', 24)));
        $page = max(1, (int)$request->get('page', 1));
        $user = Auth::user();
        if (!$user) return response()->json(['items' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage]);
        $favoriteIds = $user->settings['favorites'] ?? [];
        $query = Recipe::whereIn('id_recipe', $favoriteIds)->orderBy('title');
        $total = $query->count();
        $items = $query->forPage($page, $perPage)->get()->map(fn($r) => $this->mapRecipeMinimal($r))->all();
        return response()->json([
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }

    public function addFavorite($id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['ok' => false], 401);
        $settings = $user->settings ?? [];
        $fav = $settings['favorites'] ?? [];
        if (!in_array($id, $fav)) {
            $fav[] = $id;
            $settings['favorites'] = array_values(array_unique($fav));
            $user->settings = $settings;
            $user->save();
        }
        return response()->json(['ok' => true]);
    }

    public function removeFavorite($id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['ok' => false], 401);
        $settings = $user->settings ?? [];
        $fav = $settings['favorites'] ?? [];
        $fav = array_values(array_filter($fav, fn($x) => (string)$x !== (string)$id));
        $settings['favorites'] = $fav;
        $user->settings = $settings;
        $user->save();
        return response()->json(['ok' => true]);
    }

    public function available(Request $request, CookButlerService $service)
    {
        $perPage = max(1, min(60, (int)$request->get('perPage', 24)));
        $page = max(1, (int)$request->get('page', 1));
        $offset = ($page - 1) * $perPage;

        $user = Auth::user();
        if (!$user) return response()->json(['items' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage]);

        $filters = [];
        // Request a larger pool and slice locally to avoid upstream paging quirks
        $poolSize = max($perPage * 10, 60);
        $result = $service->fetchAvailableRecipesForPatient($user, $filters, $poolSize, 0);
        $recipeIds = $result['recipe_ids'] ?? [];
        $total = $result['total']['value'] ?? 0;
        if (empty($recipeIds)) {
            return response()->json(['items' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage]);
        }
        // Deterministic pagination window regardless of upstream behavior
        $start = max(0, ($page - 1) * $perPage);
        $sliceIds = array_slice(array_values($recipeIds), $start, $perPage);
        if (empty($sliceIds)) {
            return response()->json(['items' => [], 'total' => (int)$total, 'page' => $page, 'perPage' => $perPage]);
        }
        $details = $service->fetchRecipeDetailsBatch($sliceIds);
        // $details may be associative keyed by id; normalize to values array
        if (is_array($details) && $this->isAssoc($details)) {
            $details = array_values($details);
        }
        $items = array_map(function ($r) {
            return $this->mapRecipeMinimal($r);
        }, is_array($details) ? $details : []);
        // Items already correspond to the sliced IDs; ensure numeric index
        $items = array_values($items);
        return response()->json([
            'items' => array_values($items),
            'total' => (int)$total,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }
}
