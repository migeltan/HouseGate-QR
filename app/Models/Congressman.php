<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Congressman extends Model
{
    // Laravel would guess "congressmans" — pin it to the real table name.
    protected $table = 'congressmen';

    protected $fillable = [
        'member_id', 'name', 'rep_type', 'rep_detail',
        'building_id', 'floor', 'room', 'is_active',
    ];

    protected $casts = [
        'floor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Active congressmen whose office is in any of the given building IDs.
     * Single-building pass → one ID; North Gate Access pass → the ticked buildings.
     */
    public function scopeInBuildings($query, array $buildingIds)
    {
        return $query->active()->whereIn('building_id', $buildingIds);
    }
}