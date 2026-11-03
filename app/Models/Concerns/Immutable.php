<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * Document comptable émis : ni modification ni suppression (règle 5).
 * Seules les colonnes de $mutableAfterIssue (ex. date d'envoi du courriel) peuvent changer.
 */
trait Immutable
{
    public static function bootImmutable(): void
    {
        static::updating(function ($model) {
            $allowed = array_merge($model->mutableAfterIssue ?? [], ['updated_at']);
            if (array_diff(array_keys($model->getDirty()), $allowed)) {
                throw new LogicException('Un document émis ne se modifie pas : passez par une note de crédit.');
            }
        });

        static::deleting(fn () => throw new LogicException('Un document émis ne se supprime pas.'));
    }
}
