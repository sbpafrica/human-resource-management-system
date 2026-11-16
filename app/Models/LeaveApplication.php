<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LeaveApplication extends Model
{
    use HasFactory;

    protected $table = 'leaveapplications';

    protected $fillable = [
        'ida',
        'staffid',
        'name',
        'company',
        'type',
        'leave_type',
        'date_start',
        'start_date',
        'date_end',
        'end_date',
        'reason',
        'managername',
        'phone',
        'status',
    ];

    protected static function booted()
    {
        static::saving(function ($model) {
            // Keep dual columns in sync
            $staffId = trim($model->staffid ?: ($model->ida ?: ''));
            if ($staffId !== '') {
                $model->staffid = $staffId;
                $model->ida = $staffId;
            }

            $leaveType = $model->leave_type ?: ($model->type ?: '');
            if ($leaveType !== '') {
                $model->leave_type = $leaveType;
                $model->type = $leaveType;
            }

            $startDate = $model->start_date ?: ($model->date_start ?: '');
            if ($startDate !== '') {
                $model->start_date = $startDate;
                $model->date_start = $startDate;
            }

            $endDate = $model->end_date ?: ($model->date_end ?: '');
            if ($endDate !== '') {
                $model->end_date = $endDate;
                $model->date_end = $endDate;
            }

            // Normalize status to enum strings
            if ($model->status === 0 || $model->status === '0') {
                $model->status = 'pending';
            } elseif ($model->status === 1 || $model->status === '1') {
                $model->status = 'approved';
            } elseif ($model->status === 2 || $model->status === '2') {
                $model->status = 'denied';
            } elseif ($model->status === 3 || $model->status === '3') {
                $model->status = 'cancelled';
            } elseif (empty($model->status)) {
                $model->status = 'pending';
            }

            // Auto-detect company if empty
            if (empty($model->company) && $staffId !== '') {
                $empData = DB::table('newemployeedata')
                    ->whereRaw('TRIM(staffid) = ?', [$staffId])
                    ->first();
                if ($empData && !empty($empData->company)) {
                    $model->company = $empData->company;
                } else {
                    $contact = DB::table('employeecontact')
                        ->whereRaw('TRIM(staffid) = ?', [$staffId])
                        ->first();
                    if ($contact && !empty($contact->shop)) {
                        $model->company = $contact->shop;
                    }
                }
            }

            // Auto-detect name if empty
            if (empty($model->name) && $staffId !== '') {
                $empData = DB::table('newemployeedata')
                    ->whereRaw('TRIM(staffid) = ?', [$staffId])
                    ->first();
                if ($empData && !empty($empData->employeename)) {
                    $model->name = $empData->employeename;
                } else {
                    $contact = DB::table('employeecontact')
                        ->whereRaw('TRIM(staffid) = ?', [$staffId])
                        ->first();
                    if ($contact && !empty($contact->name)) {
                        $model->name = $contact->name;
                    }
                }
            }
        });
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending', 0, '0'], true);
    }

    public function isApproved(): bool
    {
        return in_array($this->status, ['approved', 1, '1'], true);
    }

    public function isDenied(): bool
    {
        return in_array($this->status, ['denied', 2, '2'], true);
    }

    public function isCancelled(): bool
    {
        return in_array($this->status, ['cancelled', 3, '3'], true);
    }

    public function scopePending($query)
    {
        return $query->whereIn('leaveapplications.status', ['pending', 0, '0']);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('leaveapplications.status', ['approved', 1, '1']);
    }

    public function scopeDenied($query)
    {
        return $query->whereIn('leaveapplications.status', ['denied', 2, '2']);
    }

    public function reviewTokens()
    {
        return $this->hasMany(LeaveReviewToken::class);
    }
}
