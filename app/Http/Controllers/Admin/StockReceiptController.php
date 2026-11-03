<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExpectedArrival;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Services\Stock\StockLevels;
use App\Services\Stock\StockReceiving;
use App\Support\Quantity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Entrées en stock : toute l'équipe reçoit les arrivages. */
class StockReceiptController extends Controller
{
    public function __construct(private StockLevels $levels) {}

    public function index(Request $request): View
    {
        $from = $this->dateOrNull($request->query('du'));
        $to = $this->dateOrNull($request->query('au'));

        $receipts = StockReceipt::query()
            ->with(['supplier', 'user'])
            ->withCount('lots')
            ->withSum('lots', 'cost_cents')
            ->when($from, fn ($q) => $q->whereDate('received_on', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('received_on', '<=', $to))
            ->orderByDesc('received_on')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('admin.stock.journal', compact('receipts', 'from', 'to'));
    }

    public function create(Request $request): View
    {
        $soon = ExpectedArrival::open()
            ->with('product')
            ->whereDate('expected_on', '<=', today()->addDays(7))
            ->whereHas('product')
            ->orderBy('expected_on')
            ->get()
            ->unique('product_id');

        $fields = ['source', 'received_qty', 'other_label', 'other_content', 'lot_number', 'best_before', 'unit_cost'];

        // Après une erreur : les lignes saisies reviennent avec les données de leur produit
        $lines = collect(old('lines', []))
            ->map(fn ($l) => ($p = Product::find((int) ($l['product_id'] ?? 0)))
                ? ['product' => $this->payload($p)] + array_map('strval', array_intersect_key($l + array_fill_keys($fields, ''), array_flip($fields)))
                : null)
            ->filter()->values();

        // Ligne préremplie depuis la fiche produit (« Entrée en stock »)
        if ($lines->isEmpty() && $request->integer('produit') && ($product = Product::find($request->integer('produit')))) {
            $payload = $this->payload($product);
            $lines->push(['product' => $payload, 'source' => $payload['sources'][0]['value'], 'received_qty' => '1']
                + array_fill_keys(['other_label', 'other_content', 'lot_number', 'best_before', 'unit_cost'], ''));
        }

        return view('admin.stock.create', [
            'suppliers' => Supplier::active()->orderBy('name')->get(),
            'soon' => $soon->map(fn (ExpectedArrival $a) => $this->payload($a->product) + ['expected_on' => $a->expected_on->toDateString()])->values(),
            'lines' => $lines,
        ]);
    }

    /** Recherche pour le lecteur de codes-barres et la saisie du nom. */
    public function products(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        if ($q === '') {
            return response()->json([]);
        }

        $products = Product::query()
            ->where(fn ($w) => $w->where('barcode', $q)
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('brand', 'like', "%{$q}%"))
            ->orderByRaw('barcode = ? desc', [$q])
            ->orderBy('name')
            ->limit(8)
            ->get();

        return response()->json($products->map(fn (Product $p) => $this->payload($p))->values());
    }

    public function store(Request $request, StockReceiving $receiving): RedirectResponse
    {
        $receipt = $receiving->receive(
            $request->only(['supplier_id', 'received_on', 'delivery_note_no', 'notify_preorders']),
            array_values((array) $request->input('lines', [])),
            $request->user(),
        );

        $redirect = $request->boolean('print_labels')
            ? redirect()->route('admin.receipts.labels', $receipt)
            : redirect()->route('admin.receipts.show', $receipt);

        return $redirect
            ->with('status', __('stock.receipts.saved', ['number' => $receipt->number()]))
            ->with('warnings', $receiving->warnings() ?: null);
    }

    public function show(StockReceipt $receipt): View
    {
        $receipt->load(['supplier', 'user', 'lots.product.stockUnit']);
        $served = OrderItem::where('served_receipt_id', $receipt->id)->with(['order.customer', 'product.stockUnit'])->orderBy('id')->get();

        return view('admin.stock.show', compact('receipt', 'served'));
    }

    public function labels(StockReceipt $receipt): View
    {
        $receipt->load(['supplier', 'lots.product.stockUnit']);

        return view('admin.stock.labels', compact('receipt'));
    }

    /** Données d'un produit pour l'écran d'entrée en stock. */
    private function payload(Product $product): array
    {
        $product->loadMissing(['stockUnit', 'formats']);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'brand' => $product->brand,
            'barcode' => $product->barcode,
            'unit' => $product->stockUnit->short_label,
            'on_hand_milli' => $this->levels->onHandMilli($product),
            'available_milli' => $this->levels->availableMilli($product),
            // Précommandes en attente, dans l'ordre de service (millièmes de l'unité de stock)
            'preorders' => OrderItem::pendingPreorder()->where('order_items.product_id', $product->id)
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->orderBy('orders.created_at')->orderBy('orders.id')->orderBy('order_items.id')
                ->select('order_items.*')->get()
                ->map(fn (OrderItem $i) => Quantity::toMilli($i->stock_qty))->values(),
            'sources' => StockReceiving::sourcesFor($product),
        ];
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        return Carbon::createFromFormat('!Y-m-d', $value)?->toDateString();
    }
}
