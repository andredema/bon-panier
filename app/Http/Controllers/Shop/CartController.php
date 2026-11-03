<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\SaleFormat;
use App\Services\Orders\Cart;
use App\Services\Orders\CartException;
use App\Services\Orders\PickupSlots;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Panier en JSON pour la page de commande (Alpine.js). Le serveur fait foi. */
class CartController extends Controller
{
    public function __construct(private Cart $cart, private PickupSlots $slots) {}

    public function show(): JsonResponse
    {
        return $this->respond();
    }

    public function add(Request $request): JsonResponse
    {
        return $this->attempt(fn () => $this->cart->add($this->format($request), (string) $request->input('qty')));
    }

    public function update(Request $request, int $format): JsonResponse
    {
        return $this->attempt(fn () => $this->cart->set($this->format($request, $format), (string) $request->input('qty')));
    }

    public function destroy(int $format): JsonResponse
    {
        $this->cart->remove($format);

        return $this->respond();
    }

    private function format(Request $request, ?int $id = null): SaleFormat
    {
        return SaleFormat::with(['product.stockUnit', 'unit'])->findOrFail($id ?? $request->integer('format_id'));
    }

    private function attempt(callable $action): JsonResponse
    {
        try {
            $action();
        } catch (CartException $e) {
            return $this->respond($e->getMessage(), 422);
        }

        return $this->respond();
    }

    private function respond(?string $error = null, int $status = 200): JsonResponse
    {
        $summary = $this->cart->summary();
        $notBefore = $summary['latest_arrival'] ? Carbon::parse($summary['latest_arrival']) : null;

        return response()->json([
            'cart' => $summary,
            // Les créneaux dépendent des précommandes du panier (après l'arrivage)
            'slots' => collect($this->slots->available(now(), $notBefore))->map(fn ($s) => ['value' => $s['value'], 'label' => $s['label']])->values(),
            'error' => $error,
        ], $status);
    }
}
