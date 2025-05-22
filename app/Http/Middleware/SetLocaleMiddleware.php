<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetLocaleMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $language = auth()->check() ? (auth()->user()->settings['language'] ?? 'de') : 'de';
        \Log::info('SetLocaleMiddleware: Setting locale', ['language' => $language, 'user_id' => auth()->user()?->id, 'current_locale' => \Illuminate\Support\Facades\App::getLocale()]);
        App::setLocale($language);
        \Log::info('SetLocaleMiddleware: Locale after setting', ['locale' => \Illuminate\Support\Facades\App::getLocale()]);
        return $next($request);
    }
}
