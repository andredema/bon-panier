<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Format;
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

            $label = __('catalog.settings.labels.'.$field);

            $values[$key] = match ($def['type']) {
                'bool' => $request->boolean($field),
                'rate' => $this->rate($input, $key, $errors),
                'enum' => in_array($input, $def['options'], true) ? $input : ($errors[$field] = __('validation.in', ['attribute' => $label])),
                'int' => filter_var($input, FILTER_VALIDATE_INT, ['options' => ['min_range' => $def['min'], 'max_range' => $def['max']]]) !== false
                    ? (int) $input
                    : ($errors[$field] = __('validation.between.numeric', ['attribute' => $label, 'min' => $def['min'], 'max' => $def['max']])),
                'money' => $this->money($input, $field, $errors),
                'days' => implode(',', array_values(array_intersect(['1', '2', '3', '4', '5', '6', '7'], (array) $request->input($field, [])))),
                'slots' => Settings::parseSlots((string) $input) !== null
                    ? trim((string) $input)
                    : ($errors[$field] = __('catalog.settings.slots_invalid')),
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

    private function money(mixed $input, string $field, array &$errors): ?int
    {
        try {
            $cents = Format::parseMoney(filled($input) ? (string) $input : '0');
        } catch (\InvalidArgumentException) {
            $cents = -1;
        }

        if ($cents < 0) {
            $errors[$field] = __('stock.rules.cost');

            return null;
        }

        return $cents;
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
