<?php

namespace App\Mail\Orders;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

abstract class OrderMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    /** @var array<int, int> */
    public array $backoff = [5, 30, 120];

    public function __construct(public readonly Order $order)
    {
        $this->onQueue('mails');
        $this->afterCommit();
    }

    abstract protected function subjectText(): string;

    abstract protected function heading(): string;

    abstract protected function messageText(): string;

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->subjectText().' · '.$this->order->code);
    }

    public function content(): Content
    {
        $this->order->loadMissing(['game:id,name', 'server:id,name'])->loadCount('recipients');

        return new Content(
            markdown: 'emails.orders.status',
            with: [
                'heading' => $this->heading(),
                'messageText' => $this->messageText(),
                'recipientCount' => $this->order->recipients_count,
                'orderUrl' => URL::temporarySignedRoute('orders.show', now()->addDays(30), ['order' => $this->order->code]),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
