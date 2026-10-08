<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ITAssetSpec extends Model
{
    protected $table = 'asset_specs';
    protected $primaryKey = 'spec_id';
    public $timestamps = false;

    protected $fillable = ['asset_id', 'category_id', 'spec_key', 'spec_value'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(ITAsset::class, 'asset_id', 'asset_id');
    }
}
