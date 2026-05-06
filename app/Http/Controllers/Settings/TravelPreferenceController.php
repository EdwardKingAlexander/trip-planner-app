<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TravelPreferenceController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/TravelPreferences', [
            'preference' => $request->user()->travelPreference()->firstOrCreate([]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'home_timezone' => ['required', 'timezone'],
            'default_currency' => ['required', 'string', 'size:3'],
            'traveler_profiles_text' => ['nullable', 'string', 'max:2000'],
            'packing_templates_text' => ['nullable', 'string', 'max:4000'],
        ]);

        $request->user()->travelPreference()->updateOrCreate([], [
            'home_timezone' => $validated['home_timezone'],
            'default_currency' => strtoupper($validated['default_currency']),
            'traveler_profiles' => $this->lines($validated['traveler_profiles_text'] ?? ''),
            'packing_templates' => $this->lines($validated['packing_templates_text'] ?? ''),
        ]);

        return back()->with('success', 'Travel preferences updated.');
    }

    private function lines(string $value): array
    {
        return collect(preg_split('/\R+/', $value))->map(fn ($line) => trim($line))->filter()->values()->all();
    }
}
