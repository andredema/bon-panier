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

/** Avis d'annulation au client, avec le motif saisi par l'équipe. */
class OrderCancelled extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('orders.mail.cancelled_subject', ['number' => $this->order->number]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-status', with: [
            'order' => $this->order->loadMissing('customer'),
            'text' => __('orders.mail.cancelled', ['number' => $this->order->number]),
            'reason' => $this->order->cancel_reason,
            'shopName' => Settings::get('shop.name'),
            'shopAddress' => Settings::get('shop.address'),
        ]);
    }
}
