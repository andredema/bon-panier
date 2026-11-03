<?php

namespace App\Mail;

use App\Models\Invoice;
use App\Services\Billing\BillingPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Facture PDF en pièce jointe ; le PDF est produit au moment de l'envoi (file vidée par le cron). */
class InvoiceMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('billing.mail.subject', ['number' => $this->invoice->number]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invoice', with: ['invoice' => $this->invoice]);
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => app(BillingPdf::class)->invoice($this->invoice), $this->invoice->number.'.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
