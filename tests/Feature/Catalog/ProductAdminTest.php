<?php

namespace Tests\Feature\Catalog;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\Unit;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\CatalogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = User::factory()->manager()->create();
        $this->category = Category::factory()->create(['name' => 'Farines et féculents']);
    }

    private function id(string $code): int
    {
        return Unit::where('code', $code)->value('id');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Gari jaune',
            'category_id' => $this->category->id,
            'stock_unit_id' => $this->id('kg'),
            'alert_threshold' => '20',
            'is_visible' => '1',
            'allow_preorder' => '1',
            'formats' => [
                ['channel' => 'detail', 'unit_id' => $this->id('sac'), 'label' => 'Sac de 1 kg', 'content_qty' => '1', 'price' => '6,99', 'min_qty' => '1', 'step' => '1', 'is_visible' => '1'],
                ['channel' => 'detail', 'unit_id' => $this->id('kg'), 'label' => 'Vrac, au poids', 'is_by_weight' => '1', 'price' => '6,49', 'min_qty' => '0,5', 'step' => '0,5', 'is_visible' => '1'],
                ['channel' => 'gros', 'unit_id' => $this->id('sac'), 'label' => 'Sac de 25 kg', 'content_qty' => '25', 'price' => '129,00', 'min_qty' => '2', 'step' => '1', 'is_visible' => '1'],
            ],
        ], $overrides);
    }

    public function test_manager_creates_a_product_with_formats(): void
    {
        $response = $this->actingAs($this->manager)->post('/gestion/produits', $this->payload());

        $product = Product::firstWhere('name', 'Gari jaune');
        $response->assertRedirect("/gestion/produits/{$product->id}/edit");

        $this->assertSame('20.000', $product->alert_threshold);
        $this->assertTrue($product->allow_preorder);
        $this->assertFalse($product->is_taxable);

        $formats = $product->formats()->get();
        $this->assertCount(3, $formats);
        $this->assertSame([699, 649, 12900], $formats->sortBy('sort_order')->pluck('price_cents')->values()->all());

        $bulk = $formats->firstWhere('label', 'Vrac, au poids');
        $this->assertTrue($bulk->is_by_weight);
        $this->assertSame('1.000', $bulk->content_qty);
        $this->assertSame('0.500', $bulk->step);
    }

    public function test_invalid_format_is_refused_in_french_and_nothing_is_saved(): void
    {
        $payload = $this->payload();
        $payload['formats'][2]['price'] = 'abc';

        $this->actingAs($this->manager)->post('/gestion/produits', $payload)
            ->assertSessionHasErrors(['formats.2.price' => __('catalog.rules.price')]);

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('sale_formats', 0);
    }

    public function test_removed_format_is_archived_not_deleted(): void
    {
        $this->actingAs($this->manager)->post('/gestion/produits', $this->payload());
        $product = Product::first();
        $keep = $product->formats()->get()->sortBy('sort_order')->values();

        $payload = $this->payload();
        $payload['formats'] = [
            ['id' => $keep[0]->id] + $payload['formats'][0],
            ['id' => $keep[2]->id] + $payload['formats'][2],
        ];
        $payload['formats'][0]['price'] = '7,49';

        $this->actingAs($this->manager)->put("/gestion/produits/{$product->id}", $payload)->assertRedirect();

        $this->assertSame(2, $product->formats()->count());
        $this->assertSame(749, $keep[0]->fresh()->price_cents);
        $this->assertSoftDeleted('sale_formats', ['id' => $keep[1]->id]);
    }

    public function test_price_change_is_written_to_the_audit_log_with_the_user(): void
    {
        $this->actingAs($this->manager)->post('/gestion/produits', $this->payload());
        $format = SaleFormat::firstWhere('label', 'Sac de 1 kg');

        $payload = $this->payload();
        $payload['formats'] = [['id' => $format->id] + $payload['formats'][0]];
        $payload['formats'][0]['price'] = '7,25';
        $this->actingAs($this->manager)->put("/gestion/produits/{$format->product_id}", $payload);

        $log = AuditLog::where('subject_type', (new SaleFormat)->getMorphClass())
            ->where('subject_id', (string) $format->id)->where('action', 'updated')->firstOrFail();

        $this->assertSame($this->manager->id, $log->user_id);
        $this->assertSame(['price_cents' => 699], $log->changes['before']);
        $this->assertSame(['price_cents' => 725], $log->changes['after']);
    }

    public function test_stock_unit_cannot_change_once_formats_exist(): void
    {
        $this->actingAs($this->manager)->post('/gestion/produits', $this->payload());
        $product = Product::first();

        $this->actingAs($this->manager)->put("/gestion/produits/{$product->id}", $this->payload(['stock_unit_id' => $this->id('g')]))
            ->assertSessionHasErrors('stock_unit_id');

        $this->assertSame($this->id('kg'), $product->fresh()->stock_unit_id);
    }

    public function test_wholesale_dearer_than_retail_saves_with_a_warning(): void
    {
        $payload = $this->payload();
        $payload['formats'][2]['price'] = '199';

        $this->actingAs($this->manager)->post('/gestion/produits', $payload)
            ->assertSessionHas('warnings');
        $this->assertDatabaseCount('sale_formats', 3);
    }

    public function test_photo_is_resized_to_webp(): void
    {
        Storage::fake('public');

        $this->actingAs($this->manager)->post('/gestion/produits', $this->payload([
            'photo' => UploadedFile::fake()->image('gari.jpg', 1800, 1200),
        ]))->assertSessionHasNoErrors();

        $path = Product::first()->photo_path;
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([900, 600], [$w, $h]);
    }

    public function test_archiving_hides_the_product_but_keeps_it(): void
    {
        $product = Product::factory()->create();

        $this->actingAs($this->manager)->delete("/gestion/produits/{$product->id}")->assertRedirect('/gestion/produits');

        $this->assertSoftDeleted($product);
        $this->actingAs($this->manager)->get('/gestion/produits')->assertDontSee($product->name);
    }

    public function test_employee_sees_catalog_read_only_by_default(): void
    {
        $employee = User::factory()->create();
        $product = Product::factory()->create(['name' => 'Malta']);

        $this->actingAs($employee)->get('/gestion/produits')->assertOk()->assertSee('Malta')->assertDontSee('Nouveau produit');
        $this->actingAs($employee)->get("/gestion/produits/{$product->id}/edit")->assertOk()->assertSee(__('catalog.products.read_only'));
        $this->actingAs($employee)->put("/gestion/produits/{$product->id}", $this->payload())->assertForbidden();
        $this->actingAs($employee)->get('/gestion/produits/create')->assertForbidden();
    }

    public function test_employee_may_edit_when_the_setting_allows_it(): void
    {
        Settings::set(['catalog.employees_can_edit' => true]);
        $employee = User::factory()->create();

        $this->actingAs($employee)->post('/gestion/produits', $this->payload())->assertRedirect();
        $this->assertDatabaseHas('products', ['name' => 'Gari jaune']);
    }

    public function test_search_and_category_filter(): void
    {
        $other = Category::factory()->create();
        Product::factory()->create(['name' => 'Gari jaune', 'category_id' => $this->category->id]);
        Product::factory()->create(['name' => 'Malta', 'brand' => 'Guinness', 'category_id' => $other->id]);

        $this->actingAs($this->manager)->get('/gestion/produits?q=guin')->assertSee('Malta')->assertDontSee('Gari jaune');
        $this->actingAs($this->manager)->get("/gestion/produits?categorie={$this->category->id}")->assertSee('Gari jaune')->assertDontSee('Malta');
    }

    public function test_demo_catalog_edit_pages_render(): void
    {
        $this->seed(CatalogDemoSeeder::class);

        foreach (Product::all() as $product) {
            $this->actingAs($this->manager)->get("/gestion/produits/{$product->id}/edit")->assertOk()->assertSee($product->name);
        }
    }
}
