<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Session;

class MailService extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $messageContent;
    public $senderName;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $messageContent, $senderName = null)
    {
        $this->subject = $subject;
        $this->messageContent = $messageContent;
        $this->senderName = $senderName;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        $nameCompany = Company::findOrFail(1); //Cambia el 1 por el ID correspondiente de tu compañía

        return new Envelope(
            from: new Address(config('mail.from.address'), $this->senderName ?: $nameCompany->razonSocial),
            subject: $this->subject,
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'email-contratacion',
            with: [
                'content' => $this->messageContent,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}