<?php

namespace App\Services\Billing;

use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Documents PDF (dompdf, PHP pur : fonctionne sur l'hébergement mutualisé).
 * Police DejaVu Sans, fournie avec dompdf : tous les accents du français.
 */
final class BillingPdf
{
    public function invoice(Invoice $invoice): string
    {
        $invoice->loadMissing(['lines', 'creditNotes', 'order']);

        return $this->render('pdf.invoice', ['invoice' => $invoice]);
    }

    public function creditNote(CreditNote $note): string
    {
        $note->loadMissing(['lines', 'invoice']);

        return $this->render('pdf.credit-note', ['note' => $note, 'invoice' => $note->invoice]);
    }

    public function receipt(Payment $payment): string
    {
        $payment->loadMissing(['order.customer', 'order.invoice']);

        return $this->render('pdf.receipt', ['payment' => $payment, 'order' => $payment->order]);
    }

    private function render(string $view, array $data): string
    {
        return Pdf::loadView($view, $data + ['logo' => $this->logo()])
            ->setPaper('letter')
            // Sous-ensemble de police : seuls les caractères utilisés sont intégrés (PDF léger)
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->output();
    }

    /** Logo en data URI (dompdf ne charge pas de ressources distantes). */
    private function logo(): ?string
    {
        foreach (['png' => 'image/png', 'svg' => 'image/svg+xml'] as $ext => $mime) {
            $path = public_path("images/logo-porteuse.{$ext}");
            if (is_file($path)) {
                return "data:{$mime};base64,".base64_encode((string) file_get_contents($path));
            }
        }

        return null;
    }
}
