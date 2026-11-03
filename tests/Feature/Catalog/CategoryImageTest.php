<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Lot;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Image facultative de chaque catégorie, réglée par le gérant, affichée sur la carte. */
class CategoryImageTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->manager = User::factory()->manager()->create();
    }

    public function test_manager_adds_a_category_with_an_image_resized_to_webp(): void
    {
        $this->actingAs($this->manager)->post(route('admin.settings.categories.store'), [
            'name' => 'Épices', 'icon' => 'piment', 'palette' => 'terre', 'sort_order' => 1,
            'image' => UploadedFile::fake()->image('epices.jpg', 1600, 1200),
        ])->assertRedirect()->assertSessionHasNoErrors();

        $category = Category::sole();
        $this->assertStringStartsWith('categories/', $category->image_path);
        Storage::disk('public')->assertExists($category->image_path);
        $this->assertSame(480, getimagesizefromstring(Storage::disk('public')->get($category->image_path))[0]);

        $this->get(route('admin.settings.categories.index'))->assertOk()->assertSee($category->imageUrl());
    }

    public function test_image_is_replaced_then_removed_and_files_cleaned_up(): void
    {
        $category = Category::factory()->create();
        $form = ['name' => $category->name, 'icon' => $category->icon, 'palette' => $category->palette->value, 'sort_order' => 1];
        $this->actingAs($this->manager);

        $this->put(route('admin.settings.categories.update', $category), $form + ['image' => UploadedFile::fake()->image('a.png', 300, 300)]);
        $first = $category->fresh()->image_path;

        $this->put(route('admin.settings.categories.update', $category), $form + ['image' => UploadedFile::fake()->image('b.png', 300, 300)]);
        $second = $category->fresh()->image_path;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        // Enregistrer sans fichier garde l'image ; « Retirer l'image » la supprime
        $this->put(route('admin.settings.categories.update', $category), $form);
        $this->assertSame($second, $category->fresh()->image_path);
        $this->put(route('admin.settings.categories.update', $category), $form + ['remove_image' => '1']);
        $this->assertNull($category->fresh()->image_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_non_image_files_are_refused(): void
    {
        $this->actingAs($this->manager)->post(route('admin.settings.categories.store'), [
            'name' => 'Épices', 'icon' => 'piment', 'palette' => 'terre', 'sort_order' => 1,
            'image' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('image');

        $this->assertSame(0, Category::count());
    }

    public function test_order_page_sends_the_image_to_the_category_card(): void
    {
        $category = Category::factory()->create(['image_path' => 'categories/epices.webp']);
        $product = Product::factory()->stockIn('unite')->create(['category_id' => $category->id]);
        Lot::factory()->qty('5')->create(['product_id' => $product->id]);
        SaleFormat::factory()->create(['product_id' => $product->id]);

        $this->get('/')->assertOk()->assertSee('epices.webp', false);
    }
}
