<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use HasFactory;

    protected $table = 'employee_documents';

    protected $fillable = [
        'staff_id',
        'employee_name',
        'client_name',
        'document_type',
        'document_title',
        'file_name',
        'file_path',
        'file_size',
        'file_mime',
        'recipient_email',
        'sender_user_id',
        'sender_email',
        'status',
        'error_message',
        'notes',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'file_size' => 'integer',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $this->file_size > 0 ? floor(log($this->file_size, 1024)) : 0;
        return number_format($this->file_size / pow(1024, $power), 2, '.', '') . ' ' . $units[$power];
    }
}
