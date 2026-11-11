<?php

namespace App\Console\Commands;

use App\Models\VisitorPass;
use Illuminate\Console\Command;

class ExpirePasses extends Command
{
    protected $signature = 'passes:expire';

    protected $description = 'Mark due day/long-term passes as expired (idempotent; the scanner re-checks at scan time anyway).';

    public function handle(): int
    {
        $this->info(VisitorPass::expireDue() . ' pass(es) expired.');

        return self::SUCCESS;
    }
}