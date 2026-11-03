<?php

namespace Tests\Feature\Catalog;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\Unit;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->manager()->create();
    }

    private function settingsPayload(array $overrides = []): array
    {
        return array_replace([
            'shop_name' => 'Épicerie africaine Le Bon Panier',
            'shop_address' => '840, avenue du Marché',
            'shop_phone' => '',
            'shop_email' => '',
            'tax_tps_rate' => '5',
            'tax_tvq_rate' => '9,975',
            'tax_tps_number' => '',
            'tax_tvq_number' => '',
            'interac_email' => 'paiement@example.com',
            'wholesale_prices_visible_to' => 'all',
            'preorder_scope' => 'whole_line',
            'invoice_wholesale_issued_at' => 'handover',
            'shop_privacy_officer' => '',
            'pickup_days' => ['2', '3', '4', '5', '6'],
            'pickup_times' => '10:00-12:00, 14:00-16:00',
            'pickup_lead_hours' => '12',
            'pickup_horizon_days' => '7',
            'invoice_due_days' => '14',
            'delivery_fee' => '0',
            'delivery_zone' => '',
        ], $overrides);
    }

    public function test_tax_rates_default_to_quebec_rates(): void
    {
        $this->assertSame(5000, Settings::rate('tax.tps_rate'));
        $this->assertSame(9975, Settings::rate('tax.tvq_rate'));
        $this->assertSame('whole_line', Settings::get('preorder.scope'));
        $this->assertFalse(Settings::get('catalog.employees_can_edit'));
    }

    public function test_manager_saves_settings_and_rates_stay_exact(): void
    {
        $this->actingAs($this->manager)->put('/gestion/reglages', $this->settingsPayload([
            'tax_tvq_rate' => '9,975', 'tax_tps_number' => '123456789 RT0001', 'preorder_scope' => 'missing_part',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(9975, Settings::rate('tax.tvq_rate'));
        $this->assertSame('123456789 RT0001', Settings::get('tax.tps_number'));
        $this->assertSame('missing_part', Settings::get('preorder.scope'));
        $this->assertTrue(AuditLog::where('subject_id', 'tax.tps_number')->exists());
    }

    public function test_pickup_and_delivery_settings(): void
    {
        $this->actingAs($this->manager)->put('/gestion/reglages', $this->settingsPayload([
            'pickup_days' => ['6', '9', '2'], 'pickup_times' => '09:30-11:00,13:00-15:30',
            'delivery_enabled' => '1', 'delivery_fee' => '8,50',
        ]))->assertSessionHasNoErrors();

        $this->assertSame([2, 6], Settings::days('pickup.days'));
        $this->assertSame([['09:30', '11:00'], ['13:00', '15:30']], Settings::parseSlots(Settings::get('pickup.times')));
        $this->assertTrue(Settings::get('delivery.enabled'));
        $this->assertSame(850, Settings::get('delivery.fee'));

        $this->actingAs($this->manager)->put('/gestion/reglages', $this->settingsPayload([
            'pickup_times' => '12:00-10:00', 'pickup_lead_hours' => '-1', 'delivery_fee' => 'gratuit',
        ]))->assertSessionHasErrors(['pickup_times', 'pickup_lead_hours', 'delivery_fee']);
    }

    public function test_invalid_settings_are_refused(): void
    {
        $this->actingAs($this->manager)->put('/gestion/reglages', $this->settingsPayload([
            'tax_tvq_rate' => '9,9755', 'interac_email' => 'pas-un-courriel', 'preorder_scope' => 'nimporte',
        ]))->assertSessionHasErrors(['tax_tvq_rate', 'interac_email', 'preorder_scope']);

        $this->assertSame(9975, Settings::rate('tax.tvq_rate'));
    }

    public function test_settings_page_shows_open_questions(): void
    {
        $this->actingAs($this->manager)->get('/gestion/reglages')
            ->assertOk()
            ->assertSee('9,975', false)
            ->assertSee(__('catalog.settings.groups.rules'))
            ->assertSee(__('catalog.settings.open_question'))
            ->assertSee('Les employés peuvent modifier les produits et les prix')
            ->assertSee('Seulement la partie manquante')
            ->assertDontSee('catalog.settings.', false);
    }

    public function test_every_setting_has_a_french_label(): void
    {
        foreach (array_keys(Settings::definitions()) as $key) {
            $tKey = 'catalog.settings.labels.'.str_replace('.', '_', $key);
            $this->assertNotSame($tKey, __($tKey), "Libellé manquant pour {$key}");
        }
    }

    public function test_employee_cannot_open_settings(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/gestion/reglages')->assertForbidden();
        $this->actingAs($employee)->get('/gestion/reglages/categories')->assertForbidden();
        $this->actingAs($employee)->get('/gestion/reglages/unites')->assertForbidden();
    }

    public function test_categories_get_a_unique_slug_and_keep_their_products(): void
    {
        $this->actingAs($this->manager)->post('/gestion/reglages/categories', ['name' => 'Épices', 'icon' => 'piment', 'palette' => 'terre', 'sort_order' => 1]);
        $this->actingAs($this->manager)->post('/gestion/reglages/categories', ['name' => 'Épices', 'icon' => 'piment', 'palette' => 'terre', 'sort_order' => 2]);

        $this->assertSame(['epices', 'epices-2'], Category::orderBy('id')->pluck('slug')->all());

        $used = Category::first();
        Product::factory()->create(['category_id' => $used->id]);
        $this->actingAs($this->manager)->delete("/gestion/reglages/categories/{$used->id}")->assertSessionHasErrors('category');
        $this->assertModelExists($used);
    }

    public function test_palette_must_be_one_of_the_accessible_palettes(): void
    {
        $this->actingAs($this->manager)->post('/gestion/reglages/categories', ['name' => 'Boissons', 'icon' => 'bouteille', 'palette' => '#ff0', 'sort_order' => 1])
            ->assertSessionHasErrors('palette');
    }

    public function test_manager_adds_a_count_unit(): void
    {
        $this->actingAs($this->manager)->post('/gestion/reglages/unites', [
            'label' => 'Botte', 'short_label' => 'botte', 'default_step' => '1',
        ])->assertSessionHasNoErrors();

        $unit = Unit::firstWhere('code', 'botte');
        $this->assertSame('count', $unit->kind->value);
        $this->assertFalse($unit->is_system);
    }

    public function test_deactivating_a_unit_keeps_existing_formats(): void
    {
        $sac = Unit::firstWhere('code', 'sac');
        $format = SaleFormat::factory()->create(['unit_id' => $sac->id]);

        $this->actingAs($this->manager)->put("/gestion/reglages/unites/{$sac->id}", [
            'label' => 'Sac', 'short_label' => 'sac', 'default_step' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertFalse($sac->fresh()->is_active);
        $this->assertModelExists($format);
    }

    public function test_measure_units_keep_their_decimal_rule(): void
    {
        $kg = Unit::firstWhere('code', 'kg');

        $this->actingAs($this->manager)->put("/gestion/reglages/unites/{$kg->id}", [
            'label' => 'Kilogramme (kg)', 'short_label' => 'kg', 'default_step' => '0,5', 'is_active' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($kg->fresh()->allows_decimals);
    }
}
