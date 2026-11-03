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

/** « Votre commande est prête » (cahier 3.5). */
class OrderReady extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('orders.mail.ready_subject', ['number' => $this->order->number]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.order-status', with: [
            'order' => $this->order->loadMissing('customer'),
            'text' => __($this->order->fulfilment === Order::DELIVERY ? 'orders.mail.ready_delivery' : 'orders.mail.ready_pickup', ['number' => $this->order->number]),
            'reason' => null,
            'shopName' => Settings::get('shop.name'),
            'shopAddress' => Settings::get('shop.address'),
        ]);
    }
}
