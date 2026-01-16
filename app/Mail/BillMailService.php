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

class BillMailService extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $plan;
    public $vinculacion;


    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $plan, $vinculacion)
    {

        $this->subject = $subject;
        $this->plan = $plan;
        $this->vinculacion = $vinculacion;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        $nameCompany = Company::findOrFail(1);

        return new Envelope(
            from: new Address('notificacionesvirtualt@virtualt.org', $nameCompany->razonSocial),
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
            view: 'email-bill',
            with: [
                'plan' => $this->plan,
                'vinculacion' => $this->vinculacion,
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
