<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** Courriel de confirmation ; envoyé par la file « database », vidée par le cron. */
class OrderConfirmation extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('shop.mail.confirmation_subject', ['number' => $this->order->number]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-confirmation', with: [
            'order' => $this->order->loadMissing(['items', 'customer']),
            'interacEmail' => Settings::get('interac.email'),
            'shopName' => Settings::get('shop.name'),
            'shopAddress' => Settings::get('shop.address'),
        ]);
    }
}
