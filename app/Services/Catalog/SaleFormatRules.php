<?php

namespace App\Services\Catalog;

use App\Enums\Channel;
use App\Models\Unit;
use App\Support\Format;
use App\Support\Quantity;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Règles des formats de vente (cahier des charges 4.4 et 5.1).
 *
 * - « Contenu » : ce que retire du stock une quantité de 1, dans l'unité de stock.
 * - Vente au poids : l'unité de vente doit se convertir en unité de stock ; le contenu
 *   est alors calculé (1 kg vendu retire 1 kg, ou 1 000 g si le stock est en g).
 * - Sinon, la quantité commandée est un nombre entier de formats.
 * - Minimum et pas : minimum ≥ pas, et multiple du pas.
 */
final class SaleFormatRules
{
    /** @var array<string, string> */
    private array $errors = [];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @param  Collection<int, Unit>  $units  unités connues, indexées par id
     */
    public function __construct(private Collection $units) {}

    /**
     * @param  list<array<string, mixed>>  $rows  lignes du formulaire
     * @return list<array<string, mixed>> lignes prêtes à enregistrer
     */
    public function normalize(array $rows, Unit $stockUnit, bool $productVisible): array
    {
        $this->errors = [];
        $this->warnings = [];
        $out = [];

        foreach (array_values($rows) as $i => $row) {
            $normalized = $this->normalizeRow($i, $row, $stockUnit);
            if ($normalized) {
                $normalized['sort_order'] = $i;
                $out[] = $normalized;
            }
        }

        if ($productVisible && ! $this->errors && ! collect($out)->contains('is_visible', true)) {
            $this->errors['formats'] = __('catalog.rules.visible_needs_format');
        }

        if (! $this->errors) {
            $this->checkWholesalePrices($out);
        }

        return $out;
    }

    /** @return array<string, string> erreurs, clé « formats.N.champ » */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<string> avertissements qui n'empêchent pas l'enregistrement */
    public function warnings(): array
    {
        return $this->warnings;
    }

    private function normalizeRow(int $i, array $row, Unit $stockUnit): ?array
    {
        $key = fn (string $field) => "formats.{$i}.{$field}";
        $fail = function (string $field, string $message) use ($key) {
            $this->errors[$key($field)] ??= $message;
        };

        $channel = Channel::tryFrom((string) ($row['channel'] ?? ''));
        $unit = $this->units->get((int) ($row['unit_id'] ?? 0));
        $label = trim((string) ($row['label'] ?? ''));
        $byWeight = filter_var($row['is_by_weight'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $channel ?? $fail('channel', __('catalog.rules.channel'));
        $unit ?? $fail('unit_id', __('catalog.rules.unit'));
        if ($label === '' || mb_strlen($label) > 120) {
            $fail('label', __('catalog.rules.label'));
        }

        $price = $this->money($row['price'] ?? '');
        if ($price === null || $price <= 0) {
            $fail('price', __('catalog.rules.price'));
        }

        $step = $this->qty($row['step'] ?? '');
        $min = $this->qty($row['min_qty'] ?? '');
        if ($step === null || $step <= 0) {
            $fail('step', __('catalog.rules.step'));
        }
        if ($min === null || $min <= 0) {
            $fail('min_qty', __('catalog.rules.min'));
        }

        // Contenu (ce que retire une quantité de 1)
        $content = null;
        if ($byWeight) {
            if ($unit && ! ($unit->isMeasure() && $unit->isConvertibleTo($stockUnit))) {
                $fail('is_by_weight', __('catalog.rules.by_weight_unit', ['stock' => $stockUnit->short_label]));
            } elseif ($unit) {
                $content = $unit->convertMilli(1000, $stockUnit);
                $content ?? $fail('is_by_weight', __('catalog.rules.by_weight_unit', ['stock' => $stockUnit->short_label]));
            }
        } else {
            $content = $this->qty($row['content_qty'] ?? '');
            if ($content === null || $content <= 0) {
                $fail('content_qty', __('catalog.rules.content', ['stock' => $stockUnit->short_label]));
            } elseif ($content % 1000 !== 0 && ! $stockUnit->allows_decimals && ! $stockUnit->isMeasure()) {
                $fail('content_qty', __('catalog.rules.content_whole', ['stock' => $stockUnit->short_label]));
            }
            // À l'unité : on commande un nombre entier de formats
            foreach (['step' => $step, 'min_qty' => $min] as $field => $v) {
                if ($v !== null && $v > 0 && $v % 1000 !== 0) {
                    $fail($field, __('catalog.rules.whole_quantity'));
                }
            }
        }

        if ($step && $min && $step > 0 && $min > 0 && ($min < $step || $min % $step !== 0)) {
            $fail('min_qty', __('catalog.rules.min_multiple'));
        }

        // Poids ou volume net, pour le prix comparatif d'un stock compté à la pièce
        $netQty = null;
        $netUnitId = null;
        if (! $stockUnit->isMeasure() && ! $byWeight && filled($row['net_qty'] ?? null)) {
            $netQty = $this->qty($row['net_qty']);
            $netUnit = $this->units->get((int) ($row['net_unit_id'] ?? 0));
            if ($netQty === null || $netQty <= 0 || ! $netUnit || ! $netUnit->isMeasure()) {
                $fail('net_qty', __('catalog.rules.net'));
            } else {
                $netUnitId = $netUnit->id;
            }
        }

        if ($this->hasErrorsFor($i)) {
            return null;
        }

        return [
            'id' => filled($row['id'] ?? null) ? (int) $row['id'] : null,
            'channel' => $channel,
            'unit_id' => $unit->id,
            'label' => $label,
            'is_by_weight' => $byWeight,
            'content_qty' => Quantity::fromMilli($content),
            'net_qty' => $netQty !== null ? Quantity::fromMilli($netQty) : null,
            'net_unit_id' => $netUnitId,
            'price_cents' => $price,
            'min_qty' => Quantity::fromMilli($min),
            'step' => Quantity::fromMilli($step),
            'is_visible' => filter_var($row['is_visible'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * Repère les erreurs de saisie : un format de gros plus cher, à l'unité de stock,
     * que le format de détail le moins cher.
     */
    private function checkWholesalePrices(array $rows): void
    {
        $retail = collect($rows)->where('channel', Channel::Retail);

        // Comparaison exacte des prix par unité de stock, par produit en croix (sans arrondi)
        $dearer = fn (array $a, array $b) => $a['price_cents'] * Quantity::toMilli($b['content_qty'])
            > $b['price_cents'] * Quantity::toMilli($a['content_qty']);

        foreach ($rows as $r) {
            if ($r['channel'] === Channel::Wholesale && $retail->isNotEmpty() && $retail->every(fn ($d) => $dearer($r, $d))) {
                $this->warnings[] = __('catalog.rules.wholesale_dearer', ['label' => $r['label']]);
            }
        }
    }

    private function hasErrorsFor(int $i): bool
    {
        foreach (array_keys($this->errors) as $k) {
            if (str_starts_with($k, "formats.{$i}.")) {
                return true;
            }
        }

        return false;
    }

    private function money(mixed $value): ?int
    {
        try {
            return is_string($value) || is_int($value) ? Format::parseMoney((string) $value) : null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function qty(mixed $value): ?int
    {
        return Quantity::isValid($value) ? Quantity::toMilli($value) : null;
    }
}
