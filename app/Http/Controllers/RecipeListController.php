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
        $category = isset($arr['category']) ? (is_string($arr['category']) ? (json_decode($arr['category'], true) ?: []) : (is_array($arr['category']) ? $arr['category'] : [])) : [];
        $diets = isset($arr['diets']) ? (is_string($arr['diets']) ? (json_decode($arr['diets'], true) ?: []) : (is_array($arr['diets']) ? $arr['diets'] : [])) : [];
        $allergens = isset($arr['allergens']) ? (is_string($arr['allergens']) ? (json_decode($arr['allergens'], true) ?: []) : (is_array($arr['allergens']) ? $arr['allergens'] : [])) : [];

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
            'category' => array_values(array_filter($category)),
            'diets' => array_values(array_filter(is_array($diets) ? $diets : [])),
            'allergens' => array_values(array_filter(is_array($allergens) ? $allergens : [])),
        ];
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
