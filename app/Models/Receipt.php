<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $table = 'receipts';

    public $timestamps = false;

    protected $fillable = [
        'receipt_number',
        'applicant_name',
        'address',
        'contact',
        'position',
        'client',
        'descriptions',
        'amount_paid',
        'receiving_officer',
        'mode_of_payment',
        'receipt_date',
        'staffid',
        'status',
    ];

    protected $casts = [
        'amount_paid'  => 'decimal:2',
        'receipt_date' => 'date:Y-m-d',
    ];

    /**
     * Generate a unique receipt number like REC1749119195.
     */
    public static function generateUniqueReceiptNumber(): string
    {
        do {
            $number = 'REC' . time() . mt_rand(10, 99);
            // Limit to 20 chars
            $number = substr($number, 0, 20);
        } while (self::where('receipt_number', $number)->exists());

        return $number;
    }
}
