<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    protected $fillable = ['code', 'name', 'color_name', 'color_hex', 'template_image', 'qr_color_hex'];

    public function passes(): HasMany
    {
        return $this->hasMany(VisitorPass::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class, 'scanned_building_id');
    }
    public function scopeSelectable($query)
    {
        return $query->where('code', '!=', 'NG');
    }

    /**
     * Multi-building passes that include this building in their pivot.
     */
    public function multiAccessPasses(): BelongsToMany
    {
        return $this->belongsToMany(VisitorPass::class, 'pass_building', 'building_id', 'visitor_pass_id');
    }
}