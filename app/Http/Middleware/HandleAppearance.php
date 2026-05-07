<?php

namespace App\Http\Middleware;

use App\Enums\Theme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share('appearance', $request->cookie('appearance') ?? 'system');
        View::share('theme', $this->theme($request));

        return $next($request);
    }

    private function theme(Request $request): string
    {
        $userTheme = $request->user()?->theme;

        if (Theme::tryFrom((string) $userTheme) !== null) {
            return $userTheme;
        }

        $cookieTheme = $request->cookie('theme');

        if (Theme::tryFrom((string) $cookieTheme) !== null) {
            return $cookieTheme;
        }

        return Theme::default()->value;
    }
}
