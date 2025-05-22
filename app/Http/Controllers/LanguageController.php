<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class LanguageController extends Controller
{
    public function switch(Request $request)
    {
        $locale = $request->input('locale');
        if (in_array($locale, ['de', 'en'])) {
            App::setLocale($locale);
            session()->put('locale', $locale);
            if (auth()->check()) {
                $user = auth()->user();
                $user->settings = array_merge($user->settings ?? [], ['language' => $locale]);
                $user->save();
            }
        }
        return redirect()->back();
    }
}
