<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryPalette;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\Catalog\ProductPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Catégories (cartes de la page de commande) ; réservé au gérant. */
class CategoryController extends Controller
{
    /** Les cartes sont petites : 480 px suffisent, même sur écran haute densité. */
    private const IMAGE_WIDTH = 480;

    public function __construct(private ProductPhoto $images) {}

    public function index(): View
    {
        return view('admin.settings.categories', [
            'categories' => Category::ordered()->withCount('products')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = new Category($this->validated($request));
        $this->applyImage($request, $category);
        $category->save();

        return back()->with('status', __('catalog.categories.created'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->fill($this->validated($request));
        $this->applyImage($request, $category);
        $category->save();

        return back()->with('status', __('catalog.categories.updated'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->withTrashed()->exists()) {
            return back()->withErrors(['category' => __('catalog.categories.not_empty')]);
        }

        $this->images->delete($category->image_path);
        $category->delete();

        return back()->with('status', __('catalog.categories.deleted'));
    }

    /** Remplace ou retire l'image ; l'ancien fichier est supprimé. */
    private function applyImage(Request $request, Category $category): void
    {
        if ($request->boolean('remove_image') || $request->hasFile('image')) {
            $this->images->delete($category->image_path);
            $category->image_path = null;
        }

        if ($request->hasFile('image')) {
            try {
                $category->image_path = $this->images->store($request->file('image'), 'categories', self::IMAGE_WIDTH);
            } catch (RuntimeException) {
                back()->withErrors(['image' => __('catalog.categories.image_unreadable')])->throwResponse();
            }
        }
    }

    private function validated(Request $request): array
    {
        // L'image est traitée à part (applyImage)
        return Arr::except($request->validate([
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['required', Rule::in(Category::ICONS)],
            'palette' => ['required', Rule::enum(CategoryPalette::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
            'remove_image' => ['boolean'],
        ], [], ['image' => __('catalog.categories.image')]), ['image', 'remove_image']);
    }
}
