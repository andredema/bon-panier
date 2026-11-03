<?php

namespace Tests\Feature\Orders;

use App\Services\Orders\PickupSlots;
use App\Services\Orders\TaxCalculator;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxAndSlotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_example_from_the_specification(): void
    {
        // Cahier 5.3 : sous-total 247,42 $ dont 27,48 $ taxables → TPS 1,37 $, TVQ 2,74 $, total 251,53 $
        $t = app(TaxCalculator::class)->totals(24742, 2748);

        $this->assertSame(137, $t['tps']);
        $this->assertSame(274, $t['tvq']);
        $this->assertSame(25153, $t['total']);
    }

    public function test_taxes_round_half_up_on_the_total_not_per_line(): void
    {
        // 10 $ taxables : TPS 0,50 $ ; TVQ 0,9975 $ → 1,00 $
        $this->assertSame([50, 100], array_values(array_intersect_key(app(TaxCalculator::class)->totals(1000, 1000), array_flip(['tps', 'tvq']))));
        // 0,10 $ : TPS 0,005 → 0,01 (demi vers le haut)
        $this->assertSame(1, app(TaxCalculator::class)->totals(10, 10)['tps']);
        // Rien de taxable : aucune taxe
        $this->assertSame(0, app(TaxCalculator::class)->totals(5000, 0)['tvq']);
    }

    public function test_rates_come_from_settings(): void
    {
        Settings::set(['tax.tvq_rate' => 10000]);

        $this->assertSame(100, app(TaxCalculator::class)->totals(1000, 1000)['tvq']);
    }

    public function test_delivery_fee_is_taxed_only_when_set(): void
    {
        $calc = app(TaxCalculator::class);

        $this->assertSame(0, $calc->totals(1000, 0, 800, false)['tps']);
        $this->assertSame(40, $calc->totals(1000, 0, 800, true)['tps']);
        $this->assertSame(1800, $calc->totals(1000, 0, 800, false)['total']);
    }

    public function test_line_total_by_weight_is_rounded_to_the_cent(): void
    {
        $this->assertSame(449, TaxCalculator::lineTotal(299, '1.5'));   // 4,485 → 4,49
        $this->assertSame(1797, TaxCalculator::lineTotal(599, '3'));
    }

    public function test_slots_follow_days_hours_and_lead_time(): void
    {
        Settings::set(['pickup.days' => '2,6', 'pickup.times' => '10:00-12:00, 14:00-16:00', 'pickup.lead_hours' => 12, 'pickup.horizon_days' => 7]);
        $now = CarbonImmutable::parse('2026-11-02 23:00'); // lundi soir

        $slots = app(PickupSlots::class)->available($now);

        // Mardi 3 : 10 h est à moins de 12 h (premier moment possible : 11 h) → exclu ; 14 h retenu.
        // Samedi 7 : deux créneaux. Horizon de 7 jours : rien après le lundi 9.
        $this->assertSame(['2026-11-03T14:00/16:00', '2026-11-07T10:00/12:00', '2026-11-07T14:00/16:00'],
            array_column($slots, 'value'));
        $this->assertSame('Demain, 14 h – 16 h', $slots[0]['label']);
        $this->assertSame('Samedi 7 nov., 10 h – 12 h', $slots[1]['label']);
    }

    public function test_preorder_slots_start_after_the_arrival(): void
    {
        Settings::set(['pickup.days' => '1,2,3,4,5,6', 'pickup.times' => '10:00-12:00', 'pickup.lead_hours' => 0]);
        $now = CarbonImmutable::parse('2026-11-02 08:00');

        $slots = app(PickupSlots::class)->available($now, CarbonImmutable::parse('2026-11-06'));

        $this->assertSame('2026-11-07T10:00/12:00', $slots[0]['value']);
    }
}
