<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Theme;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::enum(Theme::class)],
        ]);

        $request->user()->update([
            'theme' => $validated['theme'],
        ]);

        return back();
    }
}
