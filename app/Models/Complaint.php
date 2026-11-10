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

    protected $attributes = [
        'status'   => 'pending',
        'priority' => 'medium',
    ];

    protected static function booted()
    {
        static::creating(function ($complaint) {
            if (empty($complaint->ticket_number)) {
                $complaint->ticket_number = static::generateTicketNumber();
            }

            // All launched complaints always start under 'pending' status
            if (empty($complaint->status) || $complaint->submission_source === 'portal') {
                $complaint->status = 'pending';
            }

            // October 2026 and later cycles have not been dealt with yet
            if (in_array($complaint->month_period, ['October 2026', 'November 2026']) && $complaint->submission_source !== 'manual_review') {
                $complaint->status = 'pending';
            }
        });
    }

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
