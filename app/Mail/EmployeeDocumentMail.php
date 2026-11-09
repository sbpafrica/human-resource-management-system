<?php

namespace App\Mail;

use App\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmployeeDocumentMail extends Mailable
{
    use Queueable, SerializesModels;

    public EmployeeDocument $document;
    public string $absoluteFilePath;

    public function __construct(EmployeeDocument $document, string $absoluteFilePath)
    {
        $this->document = $document;
        $this->absoluteFilePath = $absoluteFilePath;
    }

    public function build()
    {
        $subject = 'Official Document: ' . $this->document->document_title;
        if ($this->document->client_name) {
            $subject .= ' (' . $this->document->client_name . ')';
        }

        $mail = $this->subject($subject)
                     ->view('emails.employee-document')
                     ->with([
                         'document' => $this->document,
                     ]);

        // Attach the uploaded document if it exists on disk
        if (file_exists($this->absoluteFilePath)) {
            $mail->attach($this->absoluteFilePath, [
                'as'   => $this->document->file_name,
                'mime' => $this->document->file_mime ?: 'application/octet-stream',
            ]);
        }

        return $mail;
    }
}
