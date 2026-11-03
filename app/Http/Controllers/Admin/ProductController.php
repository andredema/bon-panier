<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Channel;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\Unit;
use App\Services\Catalog\ProductPhoto;
use App\Services\Catalog\SaleFormatRules;
use App\Support\Format;
use App\Support\Quantity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private ProductPhoto $photos) {}

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $categoryId = $request->integer('categorie') ?: null;

        $products = Product::query()
            ->with(['category', 'stockUnit'])
            ->withCount([
                'formats as retail_count' => fn ($f) => $f->where('channel', Channel::Retail->value),
                'formats as wholesale_count' => fn ($f) => $f->where('channel', Channel::Wholesale->value),
            ])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('brand', 'like', "%{$q}%")
                ->orWhere('barcode', $q)))
            ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->paginate(30)
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'categories' => Category::ordered()->get(),
            'q' => $q,
            'categoryId' => $categoryId,
            'canEdit' => Gate::allows('edit-catalog'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('edit-catalog');

        return $this->form(new Product([
            'is_visible' => true,
            'allow_preorder' => true,
            'alert_threshold' => '0.000',
            'stock_unit_id' => Unit::where('code', 'unite')->value('id'),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('edit-catalog');
        $product = $this->save($request, new Product);

        return redirect()->route('admin.products.edit', $product)->with('status', __('catalog.products.created'));
    }

    public function edit(Product $product): View
    {
        return $this->form($product);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('edit-catalog');
        $this->save($request, $product);

        return redirect()->route('admin.products.edit', $product)->with('status', __('catalog.products.updated'));
    }

    /** Archivage : le produit sort du catalogue mais reste lié aux commandes passées. */
    public function destroy(Product $product): RedirectResponse
    {
        Gate::authorize('edit-catalog');
        $product->delete();

        return redirect()->route('admin.products.index')->with('status', __('catalog.products.archived'));
    }

    private function form(Product $product): View
    {
        $product->loadMissing(['formats.unit', 'formats.netUnit', 'stockUnit', 'category']);
        $units = Unit::ordered()->get();

        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::ordered()->get(),
            'units' => $units,
            'canEdit' => Gate::allows('edit-catalog'),
            'stockUnitLocked' => $product->exists && $product->formats->isNotEmpty(),
            'formatRows' => $this->formatRows($product),
            'history' => $product->exists ? $this->history($product) : collect(),
        ]);
    }

    /** Lignes du formulaire, en saisie fr-CA (« 6,99 », « 0,5 »). */
    private function formatRows(Product $product): array
    {
        if (old('formats') !== null) {
            // Cases non cochées : absentes du formulaire renvoyé
            return array_map(fn (array $r) => [
                ...$r,
                'is_by_weight' => (bool) ($r['is_by_weight'] ?? false),
                'is_visible' => (bool) ($r['is_visible'] ?? false),
            ], array_values(old('formats')));
        }

        $qty = fn (?string $v) => $v === null ? '' : str_replace(Format::NBSP, ' ', Format::quantity($v));

        return $product->formats->map(fn (SaleFormat $f) => [
            'id' => $f->id,
            'channel' => $f->channel->value,
            'unit_id' => $f->unit_id,
            'label' => $f->label,
            'is_by_weight' => $f->is_by_weight,
            'content_qty' => $qty($f->content_qty),
            'net_qty' => $qty($f->net_qty),
            'net_unit_id' => $f->net_unit_id,
            'price' => number_format($f->price_cents / 100, 2, ',', ''),
            'min_qty' => $qty($f->min_qty),
            'step' => $qty($f->step),
            'is_visible' => $f->is_visible,
        ])->all();
    }

    private function history(Product $product)
    {
        $formatIds = SaleFormat::withTrashed()->where('product_id', $product->id)->pluck('id');

        return AuditLog::with('user')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('subject_type', $product->getMorphClass())->where('subject_id', (string) $product->id))
                ->orWhere(fn ($w) => $w->where('subject_type', (new SaleFormat)->getMorphClass())->whereIn('subject_id', $formatIds->map(fn ($id) => (string) $id))))
            ->latest('id')
            ->limit(8)
            ->get();
    }

    private function save(Request $request, Product $product): Product
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['required', Rule::exists('categories', 'id')],
            'brand' => ['nullable', 'string', 'max:80'],
            'barcode' => ['nullable', 'string', 'max:40', Rule::unique('products', 'barcode')->ignore($product)],
            'stock_unit_id' => ['required', Rule::exists('units', 'id')],
            'alert_threshold' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,webp', 'max:8192'],
            'remove_photo' => ['boolean'],
            'formats' => ['array'],
        ]);

        $stockUnit = Unit::findOrFail($data['stock_unit_id']);
        $errors = [];

        if ($product->exists && $product->stock_unit_id !== $stockUnit->id && $product->formats()->exists()) {
            $errors['stock_unit_id'] = __('catalog.rules.stock_unit_locked');
        }

        $threshold = filled($data['alert_threshold'] ?? null) ? $data['alert_threshold'] : '0';
        if (! Quantity::isValid($threshold) || Quantity::toMilli($threshold) < 0) {
            $errors['alert_threshold'] = __('validation.numeric', ['attribute' => __('catalog.products.alert_threshold')]);
        }

        $rules = new SaleFormatRules(Unit::all()->keyBy('id'));
        $formats = $rules->normalize($data['formats'] ?? [], $stockUnit, $request->boolean('is_visible'));

        if ($errors += $rules->errors()) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($request, $product, $data, $stockUnit, $threshold, $formats) {
            $product->fill([
                'name' => $data['name'],
                'category_id' => $data['category_id'],
                'brand' => $data['brand'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'stock_unit_id' => $stockUnit->id,
                'alert_threshold' => Quantity::fromMilli(Quantity::toMilli($threshold)),
                'is_frozen' => $request->boolean('is_frozen'),
                'is_taxable' => $request->boolean('is_taxable'),
                'is_visible' => $request->boolean('is_visible'),
                'allow_preorder' => $request->boolean('allow_preorder'),
            ]);

            if ($request->boolean('remove_photo') || $request->hasFile('photo')) {
                $this->photos->delete($product->photo_path);
                $product->photo_path = null;
            }
            if ($request->hasFile('photo')) {
                $product->photo_path = $this->photos->store($request->file('photo'));
            }

            $product->save();
            $this->syncFormats($product, $formats);
        });

        if ($rules->warnings()) {
            session()->flash('warnings', $rules->warnings());
        }

        return $product;
    }

    /** Met à jour, crée, et archive les formats retirés du formulaire. */
    private function syncFormats(Product $product, array $formats): void
    {
        $existing = $product->formats()->get()->keyBy('id');
        $kept = [];

        foreach ($formats as $row) {
            $id = $row['id'];
            unset($row['id']);

            if ($id && $existing->has($id)) {
                $existing[$id]->update($row);
                $kept[] = $id;
            } else {
                $kept[] = $product->formats()->create($row)->id;
            }
        }

        $existing->except($kept)->each->delete();
    }
}
