<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TranssionRenewalNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function build()
    {
        return $this->subject('Contract Renewal Addendum - Action Required')
                    ->markdown('emails.transsion-renewal-notification')
                    ->with('data', $this->data);
    }
}
