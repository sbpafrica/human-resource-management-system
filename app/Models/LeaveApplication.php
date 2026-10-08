<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveApplication extends Model
{
    use HasFactory;

    protected $table = 'leaveapplications';

    protected $fillable = [
        'ida',
        'name',
        'type',
        'date_start',
        'date_end',
        'reason',
        'managername',
        'phone',
        'status',
    ];

    public function reviewTokens()
    {
        return $this->hasMany(LeaveReviewToken::class);
    }
}
