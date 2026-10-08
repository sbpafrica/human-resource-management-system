<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TranssionRenewalSigned extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $pdfPath;

    public function __construct($data, $pdfPath = null)
    {
        $this->data = $data;
        $this->pdfPath = $pdfPath;
    }

    public function build()
    {
        $subject = 'Signed Contract Renewal Addendum - ' . ($this->data['employeename'] ?? '') . ' (' . ($this->data['staffid'] ?? '') . ')';

        $mail = $this->subject($subject)
                     ->markdown('emails.transsion-renewal-signed')
                     ->with('data', $this->data);

        if ($this->pdfPath && file_exists($this->pdfPath)) {
            $mail->attach($this->pdfPath, [
                'as' => 'Signed_Renewal_Addendum_' . ($this->data['staffid'] ?? 'doc') . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
