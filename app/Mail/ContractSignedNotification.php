<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContractSignedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $pdfPath;
    public $contractType;

    public function __construct($data, $pdfPath = null, $contractType = 'Employment')
    {
        $this->data = $data;
        $this->pdfPath = $pdfPath;
        $this->contractType = $contractType;
    }

    public function build()
    {
        $name = $this->data['employeename'] ?? '';
        $id   = $this->data['staffid'] ?? '';
        $subject = "Signed {$this->contractType} Contract - {$name} ({$id})";

        $mail = $this->subject($subject)
                     ->markdown('emails.transsion-renewal-signed')
                     ->with('data', $this->data);

        if ($this->pdfPath && file_exists($this->pdfPath)) {
            $filename = 'Signed_Contract_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $id ?: 'doc') . '.pdf';
            $mail->attach($this->pdfPath, [
                'as'   => $filename,
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
