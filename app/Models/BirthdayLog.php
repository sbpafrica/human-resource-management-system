<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirthdayLog extends Model
{
    protected $table = 'birthday_logs';

    protected $fillable = [
        'staffid',
        'name',
        'company',
        'shop',
        'phone',
        'message',
        'status',
        'api_response',
        'failure_reason',
        'birthday_date',
        'sent_date',
        'retried',
    ];

    protected $casts = [
        'birthday_date' => 'date',
        'sent_date'     => 'date',
        'retried'       => 'boolean',
    ];

    /**
     * Scope: only genuinely failed records that haven't been retried yet.
     * Excludes records whose api_response already shows a successful send
     * (guards against false-failed logs caused by wrong success detection).
     */
    public function scopePendingRetry($query)
    {
        return $query
            ->where('status', 'failed')
            ->where('retried', false)
            ->where(function ($q) {
                // Exclude any record whose stored API response shows success
                $q->whereNull('api_response')
                  ->orWhere(function ($q2) {
                      $q2->whereRaw("api_response NOT LIKE '%\"code\":\"2000\"%'")
                         ->whereRaw("api_response NOT LIKE '%\"status\":\"success\"%'");
                  });
            });
    }

    /**
     * Scope: sent on a specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('sent_date', $date);
    }
}
