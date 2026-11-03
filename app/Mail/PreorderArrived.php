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

/** « Votre précommande est arrivée » : envoyé par la file, vidée par le cron. */
class PreorderArrived extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  list<int>  $itemIds  lignes arrivées */
    public function __construct(public Order $order, public array $itemIds) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('preorders.mail.subject', ['number' => $this->order->number]));
    }

    public function content(): Content
    {
        $this->order->loadMissing(['items', 'customer']);

        return new Content(view: 'mail.preorder-arrived', with: [
            'order' => $this->order,
            'arrived' => $this->order->items->whereIn('id', $this->itemIds),
            'stillWaiting' => $this->order->items->filter(fn ($i) => $i->isPendingPreorder()),
            'shopName' => Settings::get('shop.name'),
            'shopAddress' => Settings::get('shop.address'),
        ]);
    }
}
