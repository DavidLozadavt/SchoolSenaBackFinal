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
    public $remitente;

    /**
     * Create a new message instance.
     *
     * @param  Company|null  $company  Institución que envía; sin ella se usa el remitente de la plataforma.
     * @return void
     */
    public function __construct($subject, $messageContent, ?Company $company = null)
    {
        $this->subject = $subject;
        $this->messageContent = $messageContent;
        $this->remitente = $company?->razonSocial;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                'notificacionesvirtualt@virtualt.org',
                $this->remitente ?: config('mail.from.name')
            ),
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