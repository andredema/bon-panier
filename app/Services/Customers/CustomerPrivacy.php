<?php

namespace App\Services\Customers;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loi 25 : droit d'accès (export de tout ce qu'on détient sur un client)
 * et droit à l'effacement. Les factures émises restent intactes : les lois
 * fiscales exigent de les conserver (6 ans) et elles ne se modifient jamais.
 */
class CustomerPrivacy
{
    /** @return array<string, mixed> */
    public function export(Customer $customer): array
    {
        $customer->load(['orders.items', 'orders.payments', 'invoices.creditNotes']);

        return [
            'exporte_le' => now()->toIso8601String(),
            'commerce' => config('app.name'),
            'client' => [
                'nom' => $customer->name,
                'telephone' => $customer->phone,
                'courriel' => $customer->email,
                'adresse' => $customer->address,
                'type' => $customer->type,
                'gros_approuve' => $customer->approved_wholesale,
                'consentement_le' => $customer->consent_at?->toIso8601String(),
                'cree_le' => $customer->created_at?->toIso8601String(),
            ],
            'commandes' => $customer->orders->map(fn (Order $o) => [
                'numero' => $o->number,
                'date' => $o->created_at->toIso8601String(),
                'statut' => $o->status->value,
                'reception' => $o->fulfilment,
                'adresse_livraison' => $o->delivery_address,
                'notes' => $o->notes,
                'consentement_le' => $o->consent_at?->toIso8601String(),
                'total_cents' => $o->total_cents,
                'lignes' => $o->items->map(fn ($i) => [
                    'produit' => $i->product_name,
                    'format' => $i->format_label,
                    'quantite' => $i->qty.' '.$i->qty_unit,
                    'total_cents' => $i->line_total_cents,
                ])->all(),
                'paiements' => $o->payments->map(fn ($p) => [
                    'date' => $p->paid_at?->toIso8601String(),
                    'mode' => $p->method,
                    'montant_cents' => $p->amount_cents,
                ])->all(),
            ])->all(),
            'factures' => $customer->invoices->map(fn ($i) => [
                'numero' => $i->number,
                'date' => $i->issued_at->toIso8601String(),
                'total_cents' => $i->total_cents,
                'client_sur_la_facture' => $i->customer_snapshot,
                'notes_de_credit' => $i->creditNotes->pluck('number')->all(),
            ])->all(),
        ];
    }

    /** Raison pour laquelle l'effacement est refusé, ou null s'il est permis. */
    public function blocker(Customer $customer): ?string
    {
        return match (true) {
            $customer->isErased() => __('customers.erase.already'),
            $customer->hasActiveOrders() => __('customers.erase.active_orders'),
            $customer->balanceCents() > 0 => __('customers.erase.balance'),
            default => null,
        };
    }

    public function erase(Customer $customer, ?User $by): void
    {
        if ($reason = $this->blocker($customer)) {
            throw new RuntimeException($reason);
        }

        DB::transaction(function () use ($customer, $by) {
            $customer->forceFill([
                'name' => __('customers.erase.placeholder', ['id' => $customer->id]),
                'phone' => null,
                'email' => null,
                'address' => null,
                'erased_at' => now(),
            ])->save();

            Order::where('customer_id', $customer->id)->update(['delivery_address' => null, 'notes' => null]);

            // Journal sans aucune donnée personnelle
            AuditLog::create([
                'user_id' => $by?->id,
                'action' => 'erased',
                'subject_type' => $customer->getMorphClass(),
                'subject_id' => $customer->id,
                'changes' => null,
            ]);
        });
    }
}
