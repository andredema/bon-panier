<?php

namespace App\Services\Reports;

use App\Support\Format;
use Carbon\CarbonImmutable;

/**
 * Période d'un rapport : un mois (« 2026-10 »), un trimestre (« 2026-T3 ») ou des dates libres.
 */
final class Period
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $key,
        public readonly string $label,
    ) {}

    public static function fromRequest(?string $key, ?string $from = null, ?string $to = null): self
    {
        if ($from && $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) && $from <= $to) {
            $f = CarbonImmutable::parse($from)->startOfDay();
            $t = CarbonImmutable::parse($to)->endOfDay();

            return new self($f, $t, 'libre', __('reports.period.range', ['from' => Format::date($f), 'to' => Format::date($t)]));
        }

        if ($key && preg_match('/^(\d{4})-T([1-4])$/', $key, $m)) {
            $f = CarbonImmutable::create((int) $m[1], ((int) $m[2] - 1) * 3 + 1, 1)->startOfDay();

            return new self($f, $f->addMonths(2)->endOfMonth(), $key, __('reports.period.quarter', ['n' => $m[2] === '1' ? '1er' : $m[2].'e', 'year' => $m[1]]));
        }

        $month = $key && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $key) ? CarbonImmutable::parse($key.'-01') : CarbonImmutable::now()->startOfMonth();

        return new self($month->startOfMonth(), $month->endOfMonth(), $month->format('Y-m'), ucfirst($month->locale('fr_CA')->translatedFormat('F Y')));
    }

    /** Choix proposés : les 12 derniers mois et les 4 derniers trimestres. */
    public static function options(): array
    {
        $now = CarbonImmutable::now();
        $months = collect(range(0, 11))->mapWithKeys(fn ($i) => [
            $now->subMonths($i)->format('Y-m') => ucfirst($now->subMonths($i)->locale('fr_CA')->translatedFormat('F Y')),
        ]);
        $quarters = collect(range(0, 3))->mapWithKeys(function ($i) use ($now) {
            $d = $now->subMonths($i * 3);
            $q = intdiv($d->month - 1, 3) + 1;

            return ["{$d->year}-T{$q}" => __('reports.period.quarter', ['n' => $q === 1 ? '1er' : $q.'e', 'year' => $d->year])];
        });

        return ['months' => $months->all(), 'quarters' => $quarters->all()];
    }

    /** Sous-titre « du 1er au 31 octobre 2026 ». */
    public function span(): string
    {
        return __('reports.period.span', ['from' => Format::date($this->from), 'to' => Format::date($this->to)]);
    }

    public function slug(): string
    {
        return $this->from->format('Y-m-d').'_'.$this->to->format('Y-m-d');
    }
}
