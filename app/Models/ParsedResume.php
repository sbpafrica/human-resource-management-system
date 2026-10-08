<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParsedResume extends Model
{
    use HasFactory;

    protected $fillable = [
        'employeeuser_id',
        'parsed_data',
        'sensitive_fields',
        'parse_status',
        'error_message',
    ];

    protected $casts = [
        'parsed_data' => 'array',
    'sensitive_fields' => 'array',
    'raw_parsed' => 'array',
    ];
}
