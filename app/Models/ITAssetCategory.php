<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ITAssetCategory extends Model
{
    protected $table = 'categoriesnew';
    protected $primaryKey = 'category_id';
    public $timestamps = false;

    protected $fillable = ['name', 'requires_serial'];
    protected $casts = ['requires_serial' => 'boolean'];

    public function assets(): HasMany
    {
        return $this->hasMany(ITAsset::class, 'category_id', 'category_id');
    }

    public static function getPrefixes(): array
    {
        return [
            1 => 'LAP',
            2 => 'DESK',
            3 => 'MOB',
            4 => 'PRT',
            5 => 'RT',
            6 => 'OTH',
        ];
    }

    public function getPrefix(): string
    {
        return static::getPrefixes()[$this->category_id] ?? 'OTH';
    }
}
