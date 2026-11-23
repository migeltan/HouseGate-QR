<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScanLog extends Model
{
    protected $fillable = [
    'visitor_pass_id', 'qr_token_scanned', 'scanned_building_id',
        'visitor_name_snapshot', 'pass_number_snapshot', 'authorized_building_snapshot', 'contact_person_snapshot',
    'result', 'reason', 'direction',
];

    public function visitorPass(): BelongsTo
    {
        return $this->belongsTo(VisitorPass::class);
    }

    public function scannedBuilding(): BelongsTo
    {
        return $this->belongsTo(Building::class, 'scanned_building_id');
    }

    public function verificationPhoto(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ScanVerificationPhoto::class);
    }
}