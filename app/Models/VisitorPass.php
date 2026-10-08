<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class VisitorPass extends Model
{
    protected $fillable = [
    'building_id', 'pass_number', 'qr_token',
    'visitor_name', 'first_name', 'middle_name', 'last_name', 'gender', 'contact_no',
        'id_ref', 'id_type', 'purpose', 'office_to_visit', 'contact_person', 'vehicle', 'status', 'issued_at',
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
    const DAY_PASS_CUTOFF = '19:00';       // day passes expire at the first 19:00 on/after issue

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

    /** Moment this pass stops being valid (app timezone). Derived from existing fields. */
    public function expiresAt(): ?Carbon
    {
        if ($this->isLongTerm()) {
            return $this->expected_return_date?->copy()->endOfDay();   // valid through the whole return date
        }

        if (! $this->issued_at) {
            return null;
        }

        $cutoff = $this->issued_at->copy()->setTimeFromTimeString(self::DAY_PASS_CUTOFF);

        return $cutoff->lte($this->issued_at) ? $cutoff->addDay() : $cutoff;
    }

    /** True for an 'active' pass whose expiry moment has passed. Pure date check, no DB write. */
    public function isPastExpiry(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        $at = $this->expiresAt();

        return $at !== null && now()->gte($at);
    }

    /**
     * Marks this pass expired if it is due. Used by the scanner (authoritative) and the scheduler.
     * Re-reads the row under lock and only touches a still-active, still-due pass, so it is
     * idempotent and cannot overwrite a concurrent revoke/unassign/reassignment.
     */
    public function expireIfDue(): bool
    {
        if (! $this->isPastExpiry()) {
            return false;
        }

        $expired = DB::transaction(function () {
            $locked = static::whereKey($this->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isPastExpiry()) {
                return false;
            }

            $locked->openRegistration()?->update([
                'unassigned_at' => now(),
                'unassign_reason' => 'auto_expired',
            ]);

            $update = ['status' => 'expired'];
            if (! $locked->isLongTerm()) {   // same as the old sweep: day passes also clear occupancy
                $update += [
                    'current_building_id' => null,
                    'checked_in_at' => null,
                    'last_egress_at' => $locked->current_building_id ? now() : $locked->last_egress_at,
                ];
            }
            $locked->update($update);

            return true;
        });

        $this->refresh();

        return $expired;
    }

    /** Expires every due active pass. Safe to re-run. Returns how many were expired. */
    public static function expireDue(): int
    {
        $n = 0;
        static::where('status', 'active')->chunkById(200, function ($chunk) use (&$n) {
            foreach ($chunk as $pass) {
                if ($pass->expireIfDue()) {
                    $n++;
                }
            }
        });

        return $n;
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