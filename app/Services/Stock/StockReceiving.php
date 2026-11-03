<?php

namespace App\Services\Stock;

use App\Events\StockReceived;
use App\Models\ExpectedArrival;
use App\Models\Lot;
use App\Models\Product;
use App\Models\SaleFormat;
use App\Models\StockReceipt;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Format;
use App\Support\Quantity;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Entrée en stock (cahier des charges 4.3) : un bon de réception, un lot par ligne.
 *
 * Format reçu d'une ligne (« source ») :
 * - « format:ID » : un format de vente du produit (carton de 50 boules → 50) ;
 * - « stock »     : directement dans l'unité de stock (12,5 kg) ;
 * - « other »     : un autre conditionnement, avec son libellé et son contenu.
 */
final class StockReceiving
{
    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param  array{supplier_id?: mixed, received_on?: mixed, delivery_note_no?: mixed, notify_preorders?: mixed}  $header
     * @param  list<array<string, mixed>>  $lines
     *
     * @throws ValidationException
     */
    public function receive(array $header, array $lines, ?User $user): StockReceipt
    {
        $this->warnings = [];
        $errors = [];

        $receivedOn = $this->date($header['received_on'] ?? null);
        if (! $receivedOn) {
            $errors['received_on'] = __('stock.rules.received_on');
        } elseif ($receivedOn->isAfter(today())) {
            $errors['received_on'] = __('stock.rules.received_on_future');
        }

        $deliveryNote = trim((string) ($header['delivery_note_no'] ?? ''));
        if (mb_strlen($deliveryNote) > 60) {
            $errors['delivery_note_no'] = __('validation.max.string', ['attribute' => __('stock.receipts.delivery_note'), 'max' => 60]);
        }

        if (filled($header['supplier_id'] ?? null) && ! Supplier::whereKey((int) $header['supplier_id'])->exists()) {
            $errors['supplier_id'] = __('validation.exists', ['attribute' => __('stock.receipts.supplier')]);
        }

        if (! $lines) {
            $errors['lines'] = __('stock.rules.no_lines');
        }

        $products = Product::with(['stockUnit', 'formats'])->whereIn('id', collect($lines)->pluck('product_id')->filter())->get()->keyBy('id');
        $rows = [];

        foreach (array_values($lines) as $i => $line) {
            $row = $this->line($i, $line, $products, $receivedOn, $errors);
            if ($row) {
                $rows[] = $row;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $receipt = DB::transaction(function () use ($header, $deliveryNote, $receivedOn, $rows, $user) {
            $receipt = StockReceipt::create([
                'supplier_id' => filled($header['supplier_id'] ?? null) ? (int) $header['supplier_id'] : null,
                'received_on' => $receivedOn,
                'delivery_note_no' => $deliveryNote ?: null,
                'user_id' => $user?->id,
                'notify_preorders' => filter_var($header['notify_preorders'] ?? true, FILTER_VALIDATE_BOOLEAN),
            ]);

            foreach ($rows as $n => $row) {
                $row['lot_number'] ??= 'L'.$receivedOn->format('ymd').'-'.$receipt->id.'-'.($n + 1);
                $receipt->lots()->create($row);
            }

            // Pour chaque produit reçu, l'arrivage attendu le plus ancien est considéré comme reçu
            foreach (array_unique(array_column($rows, 'product_id')) as $productId) {
                ExpectedArrival::open()->where('product_id', $productId)
                    ->orderBy('expected_on')->first()
                    ?->update(['status' => ExpectedArrival::RECEIVED, 'stock_receipt_id' => $receipt->id]);
            }

            return $receipt;
        });

        StockReceived::dispatch($receipt);

        return $receipt;
    }

    /** @return list<string> */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /**
     * Options de « format reçu » d'un produit, pour l'écran d'entrée en stock.
     *
     * @return list<array{value: string, label: string, content: string, decimals: bool}>
     */
    public static function sourcesFor(Product $product): array
    {
        $stock = $product->stockUnit;
        $options = $product->formats
            ->reject(fn (SaleFormat $f) => $f->is_by_weight)
            ->unique(fn (SaleFormat $f) => $f->label.'|'.$f->content_qty)
            ->map(fn (SaleFormat $f) => [
                'value' => 'format:'.$f->id,
                'label' => $f->label,
                'content' => $f->content_qty,
                'decimals' => false,
            ])->values()->all();

        $options[] = [
            'value' => 'stock',
            'label' => __('stock.receipts.source_stock', ['unit' => $stock->short_label]),
            'content' => '1.000',
            'decimals' => $stock->allows_decimals || $stock->isMeasure(),
        ];
        $options[] = ['value' => 'other', 'label' => __('stock.receipts.source_other'), 'content' => '', 'decimals' => false];

        return $options;
    }

    private function line(int $i, array $line, $products, ?Carbon $receivedOn, array &$errors): ?array
    {
        $key = fn (string $f) => "lines.{$i}.{$f}";
        $count = count($errors);

        $product = $products->get((int) ($line['product_id'] ?? 0));
        if (! $product) {
            $errors[$key('product_id')] = __('stock.rules.product');

            return null;
        }

        $stock = $product->stockUnit;
        $source = (string) ($line['source'] ?? '');
        $saleFormatId = null;
        $decimals = false;

        if (str_starts_with($source, 'format:')) {
            $format = $product->formats->firstWhere('id', (int) substr($source, 7));
            if (! $format || $format->is_by_weight) {
                $errors[$key('source')] = __('stock.rules.source');

                return null;
            }
            [$label, $content, $saleFormatId] = [$format->label, Quantity::toMilli($format->content_qty), $format->id];
        } elseif ($source === 'stock') {
            [$label, $content] = [__('stock.receipts.source_stock', ['unit' => $stock->short_label]), 1000];
            $decimals = $stock->allows_decimals || $stock->isMeasure();
        } elseif ($source === 'other') {
            $label = trim((string) ($line['other_label'] ?? ''));
            $content = Quantity::isValid($line['other_content'] ?? null) ? Quantity::toMilli($line['other_content']) : 0;
            if ($label === '' || mb_strlen($label) > 120) {
                $errors[$key('other_label')] = __('stock.rules.other_label');
            }
            if ($content <= 0) {
                $errors[$key('other_content')] = __('stock.rules.other_content', ['unit' => $stock->short_label]);
            }
        } else {
            $errors[$key('source')] = __('stock.rules.source');

            return null;
        }

        $qty = Quantity::isValid($line['received_qty'] ?? null) ? Quantity::toMilli($line['received_qty']) : 0;
        if ($qty <= 0) {
            $errors[$key('received_qty')] = __('stock.rules.qty');
        } elseif (! $decimals && $qty % 1000 !== 0) {
            $errors[$key('received_qty')] = __('stock.rules.qty_whole');
        }

        // Quantité convertie : doit tomber juste au millième de l'unité de stock
        $converted = $qty > 0 && ($content ?? 0) > 0 ? $qty * $content : 0;
        if ($converted && $converted % 1000 !== 0) {
            $errors[$key('received_qty')] = __('stock.rules.conversion');
        }

        $lotNumber = trim((string) ($line['lot_number'] ?? ''));
        if (mb_strlen($lotNumber) > 60) {
            $errors[$key('lot_number')] = __('validation.max.string', ['attribute' => __('stock.receipts.lot_number'), 'max' => 60]);
        }

        $bestBefore = null;
        if (filled($line['best_before'] ?? null)) {
            $bestBefore = $this->date($line['best_before']);
            if (! $bestBefore) {
                $errors[$key('best_before')] = __('validation.date', ['attribute' => __('stock.receipts.best_before')]);
            } elseif ($receivedOn && $bestBefore->lt($receivedOn)) {
                $this->warnings[] = __('stock.rules.best_before_past', ['product' => $product->name]);
            }
        }

        $unitCost = null;
        if (filled($line['unit_cost'] ?? null)) {
            try {
                $unitCost = Format::parseMoney((string) $line['unit_cost']);
            } catch (InvalidArgumentException) {
                $unitCost = -1;
            }
            if ($unitCost < 0) {
                $errors[$key('unit_cost')] = __('stock.rules.cost');
            }
        }

        if (count($errors) > $count) {
            return null;
        }

        $convertedMilli = intdiv($converted, 1000);

        return [
            'product_id' => $product->id,
            'sale_format_id' => $saleFormatId,
            'received_label' => $label,
            'received_qty' => Quantity::fromMilli($qty),
            'content_qty' => Quantity::fromMilli($content),
            'converted_qty' => Quantity::fromMilli($convertedMilli),
            'remaining_qty' => Quantity::fromMilli($convertedMilli),
            'lot_number' => $lotNumber !== '' ? $lotNumber : null,
            'best_before' => $bestBefore,
            'unit_cost_cents' => $unitCost,
            'cost_cents' => $unitCost === null ? null : Quantity::divRound($unitCost * $qty, 1000),
        ];
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $d = Carbon::createFromFormat('!Y-m-d', $value);

            return $d && $d->format('Y-m-d') === $value ? $d : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
