<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminLog extends Model
{
    protected $fillable = ['user_id', 'actor_name', 'action', 'subject', 'details', 'ip'];

    /** One-liner for any controller: AdminLog::record('pass.revoked', $visitorName, 'Pass #0001'); */
    public static function record(string $action, ?string $subject = null, ?string $details = null): void
    {
        try {
            $u = \Illuminate\Support\Facades\Auth::user();
            static::create([
                'user_id' => $u?->id,
                'actor_name' => $u?->name ?? 'System',
                'action' => $action,
                'subject' => $subject,
                'details' => $details,
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);   // logging must never break the action being logged
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}