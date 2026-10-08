<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TranssionContractUploaded extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject('Your Employment Contract - Action Required')
                    ->markdown('emails.transsion-contract-uploaded')
                    ->with('data', $this->data);
    }
}
