<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Attachment;
class MailBillService extends Mailable
{
    use Queueable, SerializesModels;

    public $subject;
    public $messageContent;
    public $company;
    public $pdfPath;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $messageContent, $company, $pdfPath)
    {
        $this->subject = $subject;
        $this->messageContent = $messageContent;
        $this->company = $company;
        $this->pdfPath = $pdfPath;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        $company = Company::findOrFail($this->company->id);
        $nameCompany = $company->razonSocial;

        return new Envelope(
            from: new Address('', $nameCompany),
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
            view: 'mails/mail-basic',
            with: [
                'content' => $this->messageContent,
                'company' => $this->company,
            ],
        );
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mails/mail-basic')
                    ->with([
                        'content' => $this->messageContent,
                        'company' => $this->company,
                    ])
                    ->subject($this->subject)
                    ->attach($this->pdfPath, [
                        'as' => 'factura.pdf',
                        'mime' => 'application/pdf',
                    ]);
    }
}