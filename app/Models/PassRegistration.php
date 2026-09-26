<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
class PassRegistration extends Model
{
    protected $fillable = [
    'visitor_pass_id', 'visitor_name', 'first_name', 'middle_name', 'last_name',
    'gender', 'contact_no', 'id_type', 'id_ref',
    'photo_path', 'id_photo_path', 'purpose', 'office_to_visit', 'vehicle', 'visitor_email',
    'registered_by', 'pass_class', 'expected_return_date',
        'registered_at', 'unassigned_at', 'unassign_reason','office_to_visit', 'office_other', 'contact_person', 'buildings_snapshot',
    'transferred_from_registration_id',
];

    protected $casts = [
        'expected_return_date' => 'date',
        'registered_at' => 'datetime',
        'unassigned_at' => 'datetime',
    ];

    public function visitorPass(): BelongsTo
    {
        return $this->belongsTo(VisitorPass::class);
    }

        public function congressmen(): BelongsToMany
    {
        return $this->belongsToMany(Congressman::class, 'pass_registration_congressman')->withTimestamps();
    }

    /** The earlier registration this one was transferred from (null for a normal registration). */
    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transferred_from_registration_id');
    }

    /** The later registration this one was transferred into (null if it was never transferred). */
    public function transferredTo(): HasOne
    {
        return $this->hasOne(self::class, 'transferred_from_registration_id');
    }
}