<?php

namespace App\Services\Billing;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Quantity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Note de crédit NC-AAAA-NNNN (cahier 5.4) : seule correction possible d'une facture émise.
 *
 * Montants crédités par ligne (au plus ce qui reste créditable), frais de livraison
 * en option. Taxes calculées avec les taux de la facture, arrondies au cent, et
 * jamais plus que ce qui reste à créditer : une note qui solde la facture rend
 * exactement les taxes facturées.
 */
final class CreditNoteIssuer
{
    public function __construct(private DocumentNumbering $numbering) {}

    /**
     * @param  array<int, int>  $amounts  cents crédités par id de ligne de facture
     */
    public function issue(Invoice $invoice, array $amounts, bool $creditDelivery, string $reason, ?User $user): CreditNote
    {
        $reason = trim($reason);
        $invoice->load(['lines.creditLines', 'creditNotes']);
        $errors = [];
        $rows = [];

        foreach ($invoice->lines as $line) {
            $cents = (int) ($amounts[$line->id] ?? 0);
            if ($cents === 0) {
                continue;
            }
            if ($cents < 0 || $cents > $line->creditableCents()) {
                $errors["amounts.{$line->id}"] = __('billing.errors.credit_line', ['product' => $line->product_name]);

                continue;
            }
            $rows[] = ['line' => $line, 'cents' => $cents];
        }

        $deliveryLeft = $invoice->delivery_fee_cents - (int) $invoice->creditNotes->sum('delivery_cents');
        $delivery = $creditDelivery ? max(0, $deliveryLeft) : 0;

        if ($reason === '') {
            $errors['reason'] = __('billing.errors.credit_reason');
        }
        if (! $errors && ! $rows && $delivery === 0) {
            $errors['amounts'] = __('billing.errors.credit_empty');
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Cette note solde-t-elle tout ce qui reste (toutes les lignes et la livraison) ?
        $byLine = collect($rows)->mapWithKeys(fn ($r) => [$r['line']->id => $r['cents']]);
        $final = $delivery === max(0, $deliveryLeft)
            && $invoice->lines->every(fn ($l) => $l->creditableCents() === (int) $byLine->get($l->id, 0));

        return DB::transaction(function () use ($invoice, $rows, $delivery, $reason, $user, $final) {
            $subtotal = array_sum(array_column($rows, 'cents')) + $delivery;
            $taxable = array_sum(array_map(fn ($r) => $r['line']->is_taxable ? $r['cents'] : 0, $rows))
                + ($invoice->delivery_taxable ? $delivery : 0);

            $tpsLeft = $invoice->tax_tps_cents - (int) $invoice->creditNotes->sum('tax_tps_cents');
            $tvqLeft = $invoice->tax_tvq_cents - (int) $invoice->creditNotes->sum('tax_tvq_cents');
            $tps = $final ? $tpsLeft : min(Quantity::divRound($taxable * $invoice->tps_rate, 100_000), $tpsLeft);
            $tvq = $final ? $tvqLeft : min(Quantity::divRound($taxable * $invoice->tvq_rate, 100_000), $tvqLeft);

            $note = CreditNote::create([
                'number' => $this->numbering->next(DocumentNumbering::CREDIT_NOTE, (int) now()->format('Y')),
                'invoice_id' => $invoice->id,
                'issued_at' => now(),
                'reason' => mb_substr($reason, 0, 255),
                'delivery_cents' => $delivery,
                'subtotal_cents' => $subtotal,
                'taxable_cents' => $taxable,
                'tax_tps_cents' => max(0, $tps),
                'tax_tvq_cents' => max(0, $tvq),
                'total_cents' => $subtotal + max(0, $tps) + max(0, $tvq),
                'user_id' => $user?->id,
            ]);

            foreach ($rows as $r) {
                $note->lines()->create([
                    'invoice_line_id' => $r['line']->id,
                    'label' => $r['line']->product_name.' — '.$r['line']->format_label,
                    'amount_cents' => $r['cents'],
                    'is_taxable' => $r['line']->is_taxable,
                ]);
            }

            return $note;
        });
    }
}
