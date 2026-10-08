<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class TranssionRenewalUploaded extends Mailable
{
    use Queueable, SerializesModels;

    public $data;
    public $pdfPath; // Temporary path to the generated PDF

    public function __construct($data, $pdfPath = null)
    {
        $this->data = $data;
        $this->pdfPath = $pdfPath;
    }

    public function build()
    {
        $mail = $this->subject('Contract Renewal Addendum - Action Required')
                     ->markdown('emails.transsion-contract-renewal-uploaded')
                     ->with('data', $this->data);

        // Attach PDF if provided
        if ($this->pdfPath && file_exists($this->pdfPath)) {
            $mail->attach($this->pdfPath, [
                'as' => 'Renewal_Addendum_' . $this->data['staffid'] . '.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $mail;
    }
}
