<?php

namespace App\Console\Commands;

use App\Mail\MissingEgressReminder;
use App\Mail\PassExpiringSoon;
use App\Models\VisitorPass;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class DailyPassSweep extends Command
{
    protected $signature = 'passes:daily-sweep';
    protected $description = 'Auto-expire due passes and queue reminder emails.';

    public function handle(): int
    {
        $today = now()->toDateString();

        // 1. Auto-expire day passes past 7:00 PM cutoff
        VisitorPass::where('pass_class', 'day')
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // 2. Auto-expire long_term passes past their expected_return_date
        VisitorPass::where('pass_class', 'long_term')
            ->where('status', 'active')
            ->whereDate('expected_return_date', '<', $today)
            ->get()
            ->each(function (VisitorPass $pass) {
                $pass->openRegistration()?->update([
                    'unassigned_at' => now(),
                    'unassign_reason' => 'auto_expired',
                ]);
                $pass->update(['status' => 'expired']);
            });

        // 3. Missing-egress reminders — checked in, never scanned out, still active
        VisitorPass::whereNotNull('current_building_id')
            ->whereNotNull('visitor_email')
            ->where(fn ($q) => $q->whereNull('egress_reminder_sent_on')->orWhereDate('egress_reminder_sent_on', '<', $today))
            ->get()
            ->each(function (VisitorPass $pass) use ($today) {
                Mail::to($pass->visitor_email)->queue(new MissingEgressReminder($pass));
                $pass->update(['egress_reminder_sent_on' => $today]);
            });

        // 4. Expiring-soon reminders for long_term passes (e.g. within 3 days)
        VisitorPass::where('pass_class', 'long_term')
            ->where('status', 'active')
            ->whereNotNull('visitor_email')
            ->whereDate('expected_return_date', '<=', now()->addDays(3))
            ->where(fn ($q) => $q->whereNull('expiry_reminder_sent_on')->orWhereDate('expiry_reminder_sent_on', '<', $today))
            ->get()
            ->each(function (VisitorPass $pass) use ($today) {
                Mail::to($pass->visitor_email)->queue(new PassExpiringSoon($pass));
                $pass->update(['expiry_reminder_sent_on' => $today]);
            });

        $this->info('Daily pass sweep complete.');
        return self::SUCCESS;
    }
}