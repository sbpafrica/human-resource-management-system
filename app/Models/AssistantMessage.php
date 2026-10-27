<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'role',
        'content',
        'tool_calls',
        'tool_call_id',
    ];

    protected $casts = [
        'tool_calls' => 'array',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(AssistantThread::class, 'thread_id');
    }
}
