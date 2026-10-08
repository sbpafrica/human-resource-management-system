<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ITAsset extends Model
{
    protected $table = 'assets';
    protected $primaryKey = 'asset_id';

    protected $fillable = [
        'category_id', 'asset_tag', 'name', 'brand', 'model',
        'serial_number', 'purchase_date', 'warranty_expiry',
        'location', 'allocation_status', 'staff_id', 'staff_name',
        'condition', 'last_maintenance_date', 'notes',
    ];

    protected $casts = [
        'purchase_date'         => 'date',
        'warranty_expiry'       => 'date',
        'last_maintenance_date' => 'date',
    ];

    // Timestamps are custom column names
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'last_updated_date';

    public function category(): BelongsTo
    {
        return $this->belongsTo(ITAssetCategory::class, 'category_id', 'category_id');
    }

    public function specs(): HasMany
    {
        return $this->hasMany(ITAssetSpec::class, 'asset_id', 'asset_id');
    }

    /**
     * Generate the next asset tag for a given prefix.
     */
    public static function generateAssetTag(string $prefix): string
    {
        $last = static::where('asset_tag', 'like', $prefix . '%')
                      ->orderByDesc('asset_tag')
                      ->value('asset_tag');

        if ($last && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $last, $m)) {
            $next = intval($m[1]) + 1;
        } else {
            // Also check archive table to avoid tag reuse
            $lastArchived = ITAssetArchive::where('asset_tag', 'like', $prefix . '%')
                                          ->orderByDesc('asset_tag')
                                          ->value('asset_tag');
            if ($lastArchived && preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $lastArchived, $m)) {
                $next = intval($m[1]) + 1;
            } else {
                $next = 1;
            }
        }

        return $prefix . '-' . str_pad($next, 3, '0', STR_PAD_LEFT);
    }
}
