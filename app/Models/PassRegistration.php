<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
class PassRegistration extends Model
{
    protected $fillable = [
    'visitor_pass_id', 'visitor_name', 'first_name', 'middle_name', 'last_name',
    'gender', 'contact_no', 'id_type', 'id_ref',
    'photo_path', 'id_photo_path', 'purpose', 'office_to_visit', 'vehicle', 'visitor_email',
    'registered_by', 'pass_class', 'expected_return_date',
        'registered_at', 'unassigned_at', 'unassign_reason','office_to_visit', 'office_other', 'contact_person', 'buildings_snapshot',
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
}