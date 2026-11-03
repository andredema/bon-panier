<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Réglages typés de l'épicerie : valeur enregistrée, sinon valeur par défaut
 * de config/bonpanier.php. Mis en cache jusqu'à la prochaine modification.
 */
final class Settings
{
    private const CACHE_KEY = 'bonpanier.settings';

    /** @return array<string, array<string, mixed>> */
    public static function definitions(): array
    {
        return config('bonpanier.settings');
    }

    public static function get(string $key): mixed
    {
        $def = self::definition($key);
        $stored = self::stored();

        return array_key_exists($key, $stored) ? self::cast($def, $stored[$key]) : $def['default'];
    }

    /** @param  array<string, mixed>  $values */
    public static function set(array $values): void
    {
        foreach ($values as $key => $value) {
            $def = self::definition($key);
            $value = self::cast($def, $value);

            if (($def['type'] === 'enum') && ! in_array($value, $def['options'], true)) {
                throw new InvalidArgumentException("Valeur non permise pour {$key}.");
            }

            $setting = Setting::firstOrNew(['key' => $key]);
            $setting->value = self::serialize($def, $value);
            $setting->save();
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** Taux en millièmes de pour cent (9,975 % → 9975). */
    public static function rate(string $key): int
    {
        return (int) self::get($key);
    }

    public static function isOpenQuestion(string $key): bool
    {
        return (bool) (self::definition($key)['open_question'] ?? false);
    }

    /** @return array<string, string|null> */
    private static function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => Setting::pluck('value', 'key')->all());
    }

    private static function definition(string $key): array
    {
        return self::definitions()[$key] ?? throw new InvalidArgumentException("Réglage inconnu : {$key}");
    }

    private static function cast(array $def, mixed $value): mixed
    {
        return match ($def['type']) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'rate', 'int', 'money' => (int) $value,
            default => (string) ($value ?? ''),
        };
    }

    /** Jours de la semaine ISO (1 = lundi … 7 = dimanche) d'un réglage « days ». */
    public static function days(string $key): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) self::get($key))),
            fn (int $d) => $d >= 1 && $d <= 7,
        )));
    }

    /**
     * Plages horaires d'un réglage « slots » : « 10:00-12:00, 14:00-16:00 ».
     *
     * @return list<array{0: string, 1: string}>|null nul si le format est invalide
     */
    public static function parseSlots(string $value): ?array
    {
        $slots = [];
        foreach (array_filter(array_map('trim', explode(',', $value))) as $part) {
            if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)\s*-\s*([01]\d|2[0-3]):([0-5]\d)$/', $part, $m)) {
                return null;
            }
            $start = "{$m[1]}:{$m[2]}";
            $end = "{$m[3]}:{$m[4]}";
            if ($end <= $start) {
                return null;
            }
            $slots[] = [$start, $end];
        }

        return $slots ?: null;
    }

    private static function serialize(array $def, mixed $value): string
    {
        return match ($def['type']) {
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
