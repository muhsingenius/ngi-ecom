<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Route;

/** Tells the print shop a job is waiting for them. */
class NewJobMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "New print job {$this->order->number}");
    }

    public function content(): Content
    {
        $url = Route::has('shop.jobs.show') ? route('shop.jobs.show', $this->order) : route('shop.jobs');

        return new Content(markdown: 'emails.new-job', with: ['url' => $url]);
    }
}
