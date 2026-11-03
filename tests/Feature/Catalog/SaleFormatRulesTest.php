<?php

namespace Tests\Feature\Catalog;

use App\Enums\Channel;
use App\Models\Unit;
use App\Services\Catalog\SaleFormatRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Règle 2 : formats de vente (unité, contenu, prix, minimum, pas). */
class SaleFormatRulesTest extends TestCase
{
    use RefreshDatabase;

    private SaleFormatRules $rules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rules = new SaleFormatRules(Unit::all()->keyBy('id'));
    }

    private function u(string $code): Unit
    {
        return Unit::where('code', $code)->firstOrFail();
    }

    private function row(array $overrides = []): array
    {
        return array_replace([
            'channel' => 'detail', 'unit_id' => $this->u('sac')->id, 'label' => 'Sac de 1 kg',
            'content_qty' => '1', 'price' => '6,99', 'min_qty' => '1', 'step' => '1', 'is_visible' => '1',
        ], $overrides);
    }

    public function test_valid_row_is_normalized_to_cents_and_decimals(): void
    {
        $out = $this->rules->normalize([$this->row(['content_qty' => '25', 'price' => '129,00', 'min_qty' => '2', 'channel' => 'gros'])], $this->u('kg'), true);

        $this->assertSame([], $this->rules->errors());
        $this->assertSame(Channel::Wholesale, $out[0]['channel']);
        $this->assertSame(12900, $out[0]['price_cents']);
        $this->assertSame('25.000', $out[0]['content_qty']);
        $this->assertSame('2.000', $out[0]['min_qty']);
        $this->assertSame(0, $out[0]['sort_order']);
    }

    public function test_by_weight_content_is_computed_from_units(): void
    {
        // Stock en grammes, vendu au kg : 1 kg vendu retire 1 000 g
        $out = $this->rules->normalize([$this->row([
            'unit_id' => $this->u('kg')->id, 'is_by_weight' => '1', 'content_qty' => '', 'min_qty' => '0,5', 'step' => '0,5',
        ])], $this->u('g'), true);

        $this->assertSame([], $this->rules->errors());
        $this->assertSame('1000.000', $out[0]['content_qty']);
        $this->assertSame('0.500', $out[0]['step']);
    }

    public function test_by_weight_needs_a_convertible_measure_unit(): void
    {
        $this->rules->normalize([$this->row(['unit_id' => $this->u('sac')->id, 'is_by_weight' => '1'])], $this->u('kg'), true);
        $this->assertArrayHasKey('formats.0.is_by_weight', $this->rules->errors());

        $this->rules->normalize([$this->row(['unit_id' => $this->u('l')->id, 'is_by_weight' => '1'])], $this->u('kg'), true);
        $this->assertArrayHasKey('formats.0.is_by_weight', $this->rules->errors());
    }

    public function test_formats_sold_by_piece_use_whole_quantities(): void
    {
        $this->rules->normalize([$this->row(['step' => '0,5', 'min_qty' => '0,5'])], $this->u('kg'), true);

        $this->assertArrayHasKey('formats.0.step', $this->rules->errors());
        $this->assertArrayHasKey('formats.0.min_qty', $this->rules->errors());
    }

    public function test_minimum_must_be_a_multiple_of_the_step(): void
    {
        $this->rules->normalize([$this->row(['unit_id' => $this->u('kg')->id, 'is_by_weight' => '1', 'step' => '0,5', 'min_qty' => '0,75'])], $this->u('kg'), true);
        $this->assertSame(__('catalog.rules.min_multiple'), $this->rules->errors()['formats.0.min_qty']);
    }

    public function test_price_and_content_are_required(): void
    {
        $this->rules->normalize([$this->row(['price' => '0', 'content_qty' => ''])], $this->u('kg'), true);
        $errors = $this->rules->errors();

        $this->assertArrayHasKey('formats.0.price', $errors);
        $this->assertArrayHasKey('formats.0.content_qty', $errors);

        $this->rules->normalize([$this->row(['price' => '6,999'])], $this->u('kg'), true);
        $this->assertArrayHasKey('formats.0.price', $this->rules->errors());
    }

    public function test_count_stock_needs_whole_content(): void
    {
        $this->rules->normalize([$this->row(['unit_id' => $this->u('unite')->id, 'content_qty' => '0,5'])], $this->u('unite'), true);
        $this->assertArrayHasKey('formats.0.content_qty', $this->rules->errors());
    }

    public function test_visible_product_needs_a_visible_format(): void
    {
        $this->rules->normalize([], $this->u('kg'), true);
        $this->assertArrayHasKey('formats', $this->rules->errors());

        $this->rules->normalize([$this->row(['is_visible' => '0'])], $this->u('kg'), true);
        $this->assertArrayHasKey('formats', $this->rules->errors());

        $this->rules->normalize([], $this->u('kg'), false);
        $this->assertSame([], $this->rules->errors());
    }

    public function test_warns_when_wholesale_costs_more_per_stock_unit(): void
    {
        // Détail : 6,99 $ le kg ; gros : 25 kg à 199 $ = 7,96 $/kg → avertissement
        $this->rules->normalize([
            $this->row(),
            $this->row(['channel' => 'gros', 'label' => 'Sac de 25 kg', 'content_qty' => '25', 'price' => '199']),
        ], $this->u('kg'), true);

        $this->assertSame([], $this->rules->errors());
        $this->assertCount(1, $this->rules->warnings());

        // 129 $ les 25 kg = 5,16 $/kg : pas d'avertissement
        $this->rules->normalize([
            $this->row(),
            $this->row(['channel' => 'gros', 'label' => 'Sac de 25 kg', 'content_qty' => '25', 'price' => '129']),
        ], $this->u('kg'), true);
        $this->assertSame([], $this->rules->warnings());
    }
}
