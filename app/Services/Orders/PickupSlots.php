<?php

namespace App\Services\Orders;

use App\Support\Format;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Créneaux de ramassage ou de livraison proposés au client, tirés des réglages :
 * jours ouverts, plages horaires, délai minimal et horizon.
 * Une commande avec précommande ne propose que des créneaux après l'arrivage attendu.
 */
final class PickupSlots
{
    /**
     * @return list<array{value: string, start: CarbonImmutable, end: CarbonImmutable, label: string}>
     */
    public function available(?CarbonInterface $now = null, ?CarbonInterface $notBeforeDay = null): array
    {
        $now = CarbonImmutable::instance($now ?? now());
        $earliest = $now->addHours((int) Settings::get('pickup.lead_hours'));
        $days = Settings::days('pickup.days');
        $times = Settings::parseSlots((string) Settings::get('pickup.times')) ?? [];
        $horizon = (int) Settings::get('pickup.horizon_days');

        // Précommandes : premier créneau le lendemain de l'arrivage le plus tardif
        $firstDay = $notBeforeDay ? CarbonImmutable::instance($notBeforeDay)->startOfDay()->addDay() : $now->startOfDay();
        $lastDay = $firstDay->max($now->startOfDay())->addDays($horizon);

        $slots = [];
        for ($day = $firstDay->max($now->startOfDay()); $day->lte($lastDay); $day = $day->addDay()) {
            if (! in_array($day->isoWeekday(), $days, true)) {
                continue;
            }
            foreach ($times as [$from, $to]) {
                $start = $day->setTimeFromTimeString($from);
                $end = $day->setTimeFromTimeString($to);
                if ($start->lt($earliest)) {
                    continue;
                }
                $slots[] = [
                    'value' => $start->format('Y-m-d\TH:i').'/'.$end->format('H:i'),
                    'start' => $start,
                    'end' => $end,
                    'label' => $this->label($start, $end, $now),
                ];
            }
        }

        return $slots;
    }

    /** Retrouve un créneau proposé à partir de sa valeur de formulaire ; nul s'il n'est plus proposé. */
    public function find(string $value, ?CarbonInterface $now = null, ?CarbonInterface $notBeforeDay = null): ?array
    {
        foreach ($this->available($now, $notBeforeDay) as $slot) {
            if ($slot['value'] === $value) {
                return $slot;
            }
        }

        return null;
    }

    /** « Demain, 10 h – 12 h », « Samedi 7 nov., 14 h – 16 h ». */
    public function label(CarbonInterface $start, CarbonInterface $end, ?CarbonInterface $now = null): string
    {
        $now ??= now();
        $hours = Format::time($start).' – '.Format::time($end);

        if ($start->isSameDay($now)) {
            return __('shop.slots.today', ['hours' => $hours]);
        }
        if ($start->isSameDay($now->copy()->addDay())) {
            return __('shop.slots.tomorrow', ['hours' => $hours]);
        }

        return ucfirst($start->locale('fr_CA')->translatedFormat('l')).' '.Format::shortDate($start).', '.$hours;
    }
}
