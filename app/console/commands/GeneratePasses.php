<?php

namespace App\Console\Commands;

use App\Models\Building;
use App\Services\PassGenerator;
use Illuminate\Console\Command;

class GeneratePasses extends Command
{
    /**
     * php artisan passes:generate            (every building, 1-250)
     * php artisan passes:generate NW --from=1 --to=250
     */
    protected $signature = 'passes:generate {building? : Building code, e.g. NW (omit for all)} {--from=1} {--to=250}';

    protected $description = 'Create visitor passes numbered from..to for a building (existing numbers are skipped).';

    public function handle(PassGenerator $generator): int
    {
        $from = (int) $this->option('from');
        $to = (int) $this->option('to');

        if ($from < 1 || $to < $from || $to > 9999) {
            $this->error('Invalid range.');
            return self::FAILURE;
        }

        $code = $this->argument('building');
        $buildings = $code ? Building::where('code', strtoupper($code))->get() : Building::all();

        if ($buildings->isEmpty()) {
            $this->error('No matching building found.');
            return self::FAILURE;
        }

        foreach ($buildings as $building) {
            $r = $generator->generate($building, $from, $to);
            $this->info("{$building->code}: {$r['created']} created, {$r['skipped']} already existed.");
        }

        return self::SUCCESS;
    }
}