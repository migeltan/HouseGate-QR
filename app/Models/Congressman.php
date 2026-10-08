<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Congressman extends Model
{
    // Laravel would guess "congressmans" — pin it to the real table name.
    protected $table = 'congressmen';

    protected $fillable = [
        'member_id', 'name', 'rep_type', 'rep_detail',
        'building_id', 'floor', 'room', 'photo_path', 'is_active',
    ];

    protected $casts = [
        'floor' => 'integer',
        'is_active' => 'boolean',
    ];

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /** Public URL of the photo, or null when this member has none yet. */
    public function getPhotoUrlAttribute(): ?string
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        // ?v= makes the browser re-fetch after an admin replaces the photo (same file name).
        return $this->photo_path ? $disk->url($this->photo_path) . '?v=' . ($this->updated_at?->timestamp ?? 0) : null;
    }

    public function scopeActive(Builder $query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Active congressmen whose office is in any of the given building IDs.
     * Single-building pass → one ID; North Gate Access pass → the ticked buildings.
     */
    public function scopeInBuildings(Builder $query, array $buildingIds)
    {
        return $query->active()->whereIn('building_id', $buildingIds);
    }
}