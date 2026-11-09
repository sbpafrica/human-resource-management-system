<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    use HasFactory;

    protected $table = 'complaints';

    protected $fillable = [
        'ticket_number',
        'client_name',
        'staff_id',
        'employee_name',
        'shop_name',
        'phone',
        'email',
        'month_period',
        'category',
        'complaint_text',
        'attachment_path',
        'status',
        'priority',
        'resolved_by',
        'resolution_notes',
        'resolved_at',
        'submission_source',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by')->withDefault([
            'id' => null,
            'name' => 'Unassigned',
            'email' => null,
        ]);
    }

    /**
     * Generate unique ticket number (e.g. CMP-202611-0001)
     */
    public static function generateTicketNumber(): string
    {
        $prefix = 'CMP-' . date('Ym') . '-';
        $latest = static::where('ticket_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($latest && preg_match('/-(\d+)$/', $latest->ticket_number, $matches)) {
            $seq = intval($matches[1]) + 1;
        }

        return $prefix . str_pad((string)$seq, 4, '0', STR_PAD_LEFT);
    }
}
