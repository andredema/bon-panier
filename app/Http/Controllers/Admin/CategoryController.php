<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CategoryPalette;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Catégories (cartes de la page de commande) ; réservé au gérant. */
class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.categories', [
            'categories' => Category::ordered()->withCount('products')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Category::create($this->validated($request));

        return back()->with('status', __('catalog.categories.created'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request));

        return back()->with('status', __('catalog.categories.updated'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->withTrashed()->exists()) {
            return back()->withErrors(['category' => __('catalog.categories.not_empty')]);
        }

        $category->delete();

        return back()->with('status', __('catalog.categories.deleted'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'icon' => ['required', Rule::in(Category::ICONS)],
            'palette' => ['required', Rule::enum(CategoryPalette::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]);
    }
}
