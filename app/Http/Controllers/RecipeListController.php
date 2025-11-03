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
        if ($r instanceof Recipe) {
            $arr = $r->toArray();
        } else {
            $arr = is_array($r) ? $r : (array)$r;
        }
        $media = isset($arr['media']) ? (is_string($arr['media']) ? (json_decode($arr['media'], true) ?: []) : (is_array($arr['media']) ? $arr['media'] : [])) : [];
        $images = isset($arr['images']) ? (is_string($arr['images']) ? (json_decode($arr['images'], true) ?: []) : (is_array($arr['images']) ? $arr['images'] : [])) : [];

        $categoryRaw = isset($arr['category']) ? (is_string($arr['category']) ? (json_decode($arr['category'], true) ?: $arr['category']) : $arr['category']) : [];
        $dietsRaw = isset($arr['diets']) ? (is_string($arr['diets']) ? (json_decode($arr['diets'], true) ?: $arr['diets']) : $arr['diets']) : [];
        $allergensRaw = isset($arr['allergens']) ? (is_string($arr['allergens']) ? (json_decode($arr['allergens'], true) ?: $arr['allergens']) : $arr['allergens']) : [];

        $category = $this->normalizeArrayLabels($categoryRaw);
        $diets = $this->normalizeArrayLabels($dietsRaw);
        $allergens = $this->normalizeArrayLabels($allergensRaw);

        $thumb = null;
        if (!empty($media['search'])) {
            $thumb = is_array($media['search']) ? ($media['search'][0] ?? null) : $media['search'];
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
            'diets' => $diets,
            'allergens' => $allergens,
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
                if ($v === true) $out[] = (string)$k;
            }
            return array_values(array_unique(array_filter($out)));
        }

        // list
        if (is_array($val)) {
            $out = [];
            foreach ($val as $item) {
                if (is_string($item)) {
                    $s = trim($item);
                    if ($s !== '' && strtolower($s) !== 'true' && strtolower($s) !== 'false') {
                        $out[] = $s;
                    }
                    continue;
                }
                if (is_bool($item)) continue;
                if (is_object($item)) $item = (array)$item;
                if (is_array($item)) {
                    // object-like
                    $name = $item['name'] ?? ($item['label'] ?? ($item['title'] ?? ($item['value'] ?? null)));
                    if (is_string($name) && strtolower($name) !== 'true' && strtolower($name) !== 'false') {
                        $out[] = $name;
                        continue;
                    }
                    // dict boolean fallback
                    foreach ($item as $k => $v) {
                        if ($v === true) $out[] = (string)$k;
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
        $result = $service->fetchAvailableRecipesForPatient($user, $filters, $perPage, $offset);
        $recipeIds = $result['recipe_ids'] ?? [];
        $total = $result['total']['value'] ?? 0;
        if (empty($recipeIds)) {
            return response()->json(['items' => [], 'total' => 0, 'page' => $page, 'perPage' => $perPage]);
        }
        $details = $service->fetchRecipeDetailsBatch($recipeIds);
        $items = array_map(function ($r) {
            return $this->mapRecipeMinimal($r);
        }, $details);
        return response()->json([
            'items' => array_values($items),
            'total' => (int)$total,
            'page' => $page,
            'perPage' => $perPage,
        ]);
    }
}
