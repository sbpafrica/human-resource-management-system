<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchiveRun extends Model
{
    protected $table = 'archive_runs';

    protected $fillable = [
        'payroll_month',
        'payroll_year',
        'formatted_period',
        'archived_count',
        'held_count',
        'new_hire_count',
        'active_count',
        'duplicate_count',
        'error_count',
        'report_filename',
        'run_by',
        'run_by_name',
    ];

    protected $casts = [
        'archived_count'  => 'integer',
        'held_count'      => 'integer',
        'new_hire_count'  => 'integer',
        'active_count'    => 'integer',
        'duplicate_count' => 'integer',
        'error_count'     => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'run_by');
    }
}
