<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ITAssetArchive extends Model
{
    protected $table = 'assets_archive';
    protected $primaryKey = 'archive_id';

    // Custom timestamp columns
    const CREATED_AT = 'created_date';
    const UPDATED_AT = 'last_updated_date';

    protected $fillable = [
        'asset_id', 'category_id', 'asset_tag', 'name', 'brand', 'model',
        'serial_number', 'purchase_date', 'warranty_expiry',
        'location', 'allocation_status', 'staff_id', 'condition', 'notes',
        'created_by', 'last_updated_by', 'archived_by', 'archived_date',
    ];

    protected $casts = [
        'purchase_date'   => 'date',
        'warranty_expiry' => 'date',
        'archived_date'   => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ITAssetCategory::class, 'category_id', 'category_id');
    }
}
