<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScanVerificationPhoto extends Model
{
    protected $fillable = [
        'scan_log_id',
        'visitor_pass_id',
        'photo_path',
    ];

    public function scanLog()
    {
        return $this->belongsTo(ScanLog::class);
    }

    public function visitorPass()
    {
        return $this->belongsTo(VisitorPass::class);
    }
}