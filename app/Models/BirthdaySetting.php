<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BirthdaySetting extends Model
{
    protected $table = 'birthday_settings';

    protected $fillable = [
        'message_template',
        'send_time',
        'is_active',
        'alert_email',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Always retrieve the single settings row.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'message_template' => "Happy Birthday!\nWe celebrate you and appreciate your hard work, dedication, and value. Wishing you health, happiness, growth, and success. Enjoy your special day!",
            'send_time'        => '08:00:00',
            'is_active'        => true,
            'alert_email'      => 'kadisi290@gmail.com',
        ]);
    }
}
