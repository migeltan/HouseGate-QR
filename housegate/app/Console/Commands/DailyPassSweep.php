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

        // 1+2. Auto-expire due day / long_term passes. Same rule as the scan-time check
        // (VisitorPass::expireIfDue); safe to re-run. Runs first so expired passes never get reminders.
        VisitorPass::expireDue();

        // 3. Missing-egress reminders — checked in, never scanned out, still active
        VisitorPass::whereNotNull('current_building_id')
            ->whereNotNull('visitor_email')
            ->where(fn ($q) => $q->whereNull('egress_reminder_sent_on')->orWhereDate('egress_reminder_sent_on', '<', $today))
            ->get()
            ->each(function (VisitorPass $pass) use ($today) {
                Mail::to($pass->visitor_email)->queue(new MissingEgressReminder($pass));
                $pass->update(['egress_reminder_sent_on' => $today]);
            });

        // 4. Expiring-soon reminder for long_term passes: ONCE per pass per return date, sent when the
        // return date is within 3 days. A reminder already sent inside this window is never repeated;
        // an older one (from a previous use of the card) doesn't block it.
        VisitorPass::where('pass_class', 'long_term')
            ->where('status', 'active')
            ->whereNotNull('visitor_email')
            ->whereDate('expected_return_date', '>=', $today)
            ->whereDate('expected_return_date', '<=', now()->addDays(3)->toDateString())
            ->get()
            ->filter(fn (VisitorPass $pass) => ! $pass->expiry_reminder_sent_on
                || $pass->expiry_reminder_sent_on->lt($pass->expected_return_date->copy()->subDays(3)))
            ->each(function (VisitorPass $pass) use ($today) {
                Mail::to($pass->visitor_email)->queue(new PassExpiringSoon($pass));
                $pass->update(['expiry_reminder_sent_on' => $today]);
            });

        $this->info('Daily pass sweep complete.');
        return self::SUCCESS;
    }
}