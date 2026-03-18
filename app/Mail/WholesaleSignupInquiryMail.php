<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the admin when a dealer submits the wholesale signup form.
 * No account is created — this is just a contact/inquiry notification.
 */
class WholesaleSignupInquiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly array $inquiryData
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Wholesale Account Inquiry — ' . ($this->inquiryData['business_name'] ?? 'Unknown Business'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wholesale-signup-inquiry',
        );
    }
}
