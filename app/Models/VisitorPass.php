<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class VisitorPass extends Model
{
    protected $fillable = [
        'building_id', 'pass_number', 'qr_token',
        'visitor_name', 'id_ref', 'id_type', 'purpose', 'status', 'issued_at',
        'is_multi_building', 'current_building_id', 'photo_path',
        'pass_class', 'expected_return_date', 'visitor_email', 'id_photo_path',
        'registered_by', 'checked_in_at', 'last_egress_at',
        'egress_reminder_sent_on', 'expiry_reminder_sent_on',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'is_multi_building' => 'boolean',
        'expected_return_date' => 'date',
        'checked_in_at' => 'datetime',
        'last_egress_at' => 'datetime',
        'egress_reminder_sent_on' => 'date',
        'expiry_reminder_sent_on' => 'date',
    ];

    const MAX_LONG_TERM_WORKING_DAYS = 30;
    const WORKING_WEEKDAYS = [1, 2, 3, 4]; // Mon–Thu, HOR's compressed 4-day week
    const STALE_OCCUPANCY_HOURS = 16;      // e.g. forgot to scan out Friday evening

    public function currentBuilding(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'current_building_id');
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function scanLogs(): HasMany
    {
        return $this->hasMany(ScanLog::class);
    }

    /**
     * Buildings this pass is authorized for when is_multi_building = true.
     * building_id remains the nominal/primary building for legacy display
     * even on multi-building passes; this pivot is the real source of truth.
     */
    public function buildings(): BelongsToMany
    {
        return $this->belongsToMany(Building::class, 'pass_building', 'visitor_pass_id', 'building_id');
    }

    /**
     * True if this pass grants access at the given building, whether it's a
     * legacy single-building pass (building_id match) or a multi-building
     * pass (pivot match).
     */
    public function isAuthorizedFor(int $buildingId): bool
    {
        if ($this->is_multi_building) {
            return $this->buildings->contains('id', $buildingId);
        }

        return $this->building_id === $buildingId;
    }

    /**
     * Human-readable list of authorized building(s) for display/logging.
     */
    public function authorizedBuildingNames(): string
    {
        if ($this->is_multi_building) {
            $names = $this->buildings->pluck('name');
            return $names->isNotEmpty() ? $names->join(', ') : 'None';
        }

        return $this->building?->name ?? 'None';
    }

    public static function addWorkingDays(Carbon $start, int $days): Carbon
    {
        $date = $start->copy();
        $added = 0;
        while ($added < $days) {
            $date->addDay();
            if (in_array($date->dayOfWeekIso, self::WORKING_WEEKDAYS, true)) {
                $added++;
            }
        }
        return $date;
    }

    public function isLongTerm(): bool
    {
        return $this->pass_class === 'long_term';
    }

    public function daysRemaining(): ?int
    {
        if (! $this->isLongTerm() || ! $this->expected_return_date) {
            return null;
        }
        return now()->startOfDay()->diffInDays($this->expected_return_date, false);
    }

    public function hasStaleOccupancy(): bool
    {
        return $this->current_building_id !== null
            && $this->checked_in_at
            && $this->checked_in_at->diffInHours(now()) >= self::STALE_OCCUPANCY_HOURS;
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(PassRegistration::class);
    }

    public function openRegistration(): ?PassRegistration
    {
        return $this->registrations()->whereNull('unassigned_at')->latest('registered_at')->first();
    }
}