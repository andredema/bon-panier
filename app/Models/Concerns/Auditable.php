<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Inscrit chaque création, modification et suppression au journal d'audit,
 * avec l'utilisateur connecté et les valeurs avant / après.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => $m->writeAudit('created', ['after' => $m->auditableValues($m->getAttributes())]));

        static::updated(function (Model $m) {
            $changed = $m->auditableValues($m->getChanges());

            if ($changed) {
                $before = array_intersect_key($m->getOriginal(), $changed);
                $m->writeAudit('updated', ['before' => $m->auditableValues($before), 'after' => $changed]);
            }
        });

        static::deleted(fn (Model $m) => $m->writeAudit('deleted', ['before' => $m->auditableValues($m->getAttributes())]));

        if (method_exists(static::class, 'restored')) {
            static::restored(fn (Model $m) => $m->writeAudit('restored', null));
        }
    }

    /** @param  array<string, mixed>  $values */
    protected function auditableValues(array $values): array
    {
        $values = array_diff_key($values, array_flip(['created_at', 'updated_at', 'deleted_at']));

        return array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $values);
    }

    protected function writeAudit(string $action, ?array $changes): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'changes' => $changes,
        ]);
    }
}
