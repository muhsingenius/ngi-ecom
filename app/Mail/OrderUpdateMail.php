<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** One template for every customer update: payment, proof, production, ready, dispatched. */
class OrderUpdateMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(
        public Order $order,
        public string $subjectLine,
        public string $headline,
        public array $lines = [],
        public ?string $actionText = null,
        public ?string $actionUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->order->number}] {$this->subjectLine}");
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.order-update', with: [
            'actionText' => $this->actionText ?? 'View your order',
            'actionUrl' => $this->actionUrl ?? $this->order->url(),
        ]);
    }
}
