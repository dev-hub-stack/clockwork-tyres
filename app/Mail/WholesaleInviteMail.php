<?php

namespace App\Mail;

use App\Modules\Customers\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the dealer when admin clicks "Send Wholesale Invite" in the CRM.
 * Contains a time-limited link for the dealer to set their password.
 */
class WholesaleInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $setPasswordUrl;

    public function __construct(
        public readonly Customer $customer,
        string $token
    ) {
        $frontendBase = rtrim(config('services.wholesale.frontend_url', env('WHOLESALE_FRONTEND_URL', 'https://wholesale.tunerstop.com')), '/');
        $this->setPasswordUrl = $frontendBase . '/set-password?email=' . urlencode($customer->email) . '&token=' . $token;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Wholesale Account is Ready — Set Your Password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.wholesale-invite',
        );
    }
}
