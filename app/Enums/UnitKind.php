<?php

namespace App\Enums;

enum UnitKind: string
{
    case Count = 'count';
    case Mass = 'mass';
    case Volume = 'volume';

    public function isMeasure(): bool
    {
        return $this !== self::Count;
    }

    /** Unité de référence du prix comparatif : « kg » ou « L ». */
    public function comparisonLabel(): ?string
    {
        return match ($this) {
            self::Mass => 'kg',
            self::Volume => 'L',
            self::Count => null,
        };
    }
}
