<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Quantity;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Réglages de l'épicerie ; réservé au gérant. */
class SettingsController extends Controller
{
    public function edit(): View
    {
        $groups = collect(Settings::definitions())
            ->map(fn ($def, $key) => $def + ['key' => $key, 'value' => Settings::get($key)])
            ->groupBy('group');

        return view('admin.settings.general', ['groups' => $groups]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = [];
        $errors = [];

        foreach (Settings::definitions() as $key => $def) {
            $field = str_replace('.', '_', $key);
            $input = $request->input($field);

            $values[$key] = match ($def['type']) {
                'bool' => $request->boolean($field),
                'rate' => $this->rate($input, $key, $errors),
                'enum' => in_array($input, $def['options'], true) ? $input : ($errors[$field] = __('validation.in', ['attribute' => __('catalog.settings.labels.'.$field)])),
                default => mb_substr(trim((string) $input), 0, 255),
            };

            if ($def['type'] === 'string' && str_ends_with($key, 'email') && filled($values[$key]) && ! filter_var($values[$key], FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = __('validation.email', ['attribute' => __('catalog.settings.labels.'.$field)]);
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        Settings::set($values);

        return back()->with('status', __('catalog.settings.saved'));
    }

    /** « 9,975 » (%) → 9975 (millièmes de pour cent). */
    private function rate(mixed $input, string $key, array &$errors): ?int
    {
        $field = str_replace('.', '_', $key);

        if (! Quantity::isValid((string) $input) || ($milli = Quantity::toMilli((string) $input)) < 0 || $milli > 100_000) {
            $errors[$field] = __('catalog.settings.rate_invalid');

            return null;
        }

        return $milli;
    }
}
