<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PassRegistration extends Model
{
    protected $fillable = [
        'visitor_pass_id', 'visitor_name', 'id_type', 'id_ref',
        'photo_path', 'id_photo_path', 'purpose', 'visitor_email',
        'registered_by', 'pass_class', 'expected_return_date',
        'registered_at', 'unassigned_at', 'unassign_reason',
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
}