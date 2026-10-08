<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public $employee;
    public $pdf;
    public $formattedMonth;

    public function __construct($employee, $pdf, $formattedMonth)
    {
        $this->employee = $employee;
        $this->pdf = $pdf;
        $this->formattedMonth = $formattedMonth;
    }

    public function build()
    {
        return $this->subject("Your Payslip for {$this->formattedMonth}")
                    ->view('emails.payslip')
                    ->attachData($this->pdf, "payslip_{$this->employee->staffida}_{$this->formattedMonth}.pdf", [
                        'mime' => 'application/pdf',
                    ]);
    }
}
