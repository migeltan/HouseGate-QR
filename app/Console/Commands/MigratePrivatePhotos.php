<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigratePrivatePhotos extends Command
{
    /**
     * php artisan photos:migrate-private            (dry run, changes nothing)
     * php artisan photos:migrate-private --run      (copy to private disk + update DB; keeps old copies)
     * php artisan photos:migrate-private --cleanup  (after checking the app: delete the old public copies)
     */
    protected $signature = 'photos:migrate-private {--run : Copy files and update the database} {--cleanup : Delete the old public copies listed in the migration map}';

    protected $description = 'Move visitor, ID and verification photos from the public disk to the private disk with random names.';

    private const FOLDERS = ['visitor-photos/', 'id-photos/', 'verification-photos/'];

    private const MAP_FILE = 'photos-migration-map.json';

    /** table => photo columns */
    private const COLUMNS = [
        'visitor_passes' => ['photo_path', 'id_photo_path'],
        'pass_registrations' => ['photo_path', 'id_photo_path'],
        'scan_verification_photos' => ['photo_path'],
    ];

    public function handle(): int
    {
        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $public = Storage::disk('public');
        $private = Storage::disk('photos');

        $paths = [];
        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $col) {
                foreach (DB::table($table)->whereNotNull($col)->distinct()->pluck($col) as $p) {
                    $paths[$p] = true;
                }
            }
        }
        $paths = array_keys($paths);

        $todo = [];
        $missing = 0;
        $already = 0;
        foreach ($paths as $p) {
            if (! $this->safe($p)) {
                $missing++;
            } elseif ($public->exists($p)) {
                $todo[] = $p;
            } elseif ($private->exists($p)) {
                $already++;
            } else {
                $missing++;
            }
        }

        $this->line('Referenced paths: ' . count($paths));
        $this->line('To move (found on public disk): ' . count($todo));
        $this->line('Already private: ' . $already);
        $this->line('Missing everywhere or invalid (skipped, e.g. deleted on card return): ' . $missing);

        if (! $this->option('run')) {
            $this->warn('Dry run only. Back up the database and storage/app/public, then re-run with --run.');

            return self::SUCCESS;
        }

        $map = $this->loadMap();
        $moved = 0;
        $failed = 0;

        foreach ($todo as $old) {
            $bytes = $public->get($old);
            if ($bytes === null || $bytes === '') {
                $failed++;
                $this->error('Could not read a file; skipped.');
                continue;
            }

            $new = explode('/', $old)[0] . '/' . Str::random(40) . '.jpg';
            $private->put($new, $bytes);
            if (! $private->exists($new) || $private->size($new) !== strlen($bytes)) {
                $private->delete($new);
                $failed++;
                $this->error('Copy verification failed; skipped.');
                continue;
            }

            DB::transaction(function () use ($old, $new) {
                foreach (self::COLUMNS as $table => $columns) {
                    foreach ($columns as $col) {
                        DB::table($table)->where($col, $old)->update([$col => $new]);
                    }
                }
            });

            $map[$old] = $new;
            Storage::disk('local')->put(self::MAP_FILE, json_encode($map));
            $moved++;
        }

        $this->info("Moved {$moved}, failed {$failed}. Old public copies are still in place.");
        $this->line('Check the registry, scanner and logs, then run: php artisan photos:migrate-private --cleanup');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function cleanup(): int
    {
        $map = $this->loadMap();
        if (! $map) {
            $this->warn('No migration map found; nothing to clean up.');

            return self::SUCCESS;
        }

        if (! $this->confirm('Delete ' . count($map) . ' old public photo file(s) listed in the migration map?')) {
            return self::SUCCESS;
        }

        $public = Storage::disk('public');
        $private = Storage::disk('photos');
        $deleted = 0;
        foreach ($map as $old => $new) {
            // Never delete the old copy unless the private copy is really there.
            if ($this->safe($old) && $private->exists($new) && $public->exists($old)) {
                $public->delete($old);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} old public file(s).");

        return self::SUCCESS;
    }

    private function safe(string $path): bool
    {
        if (str_contains($path, '..')) {
            return false;
        }
        foreach (self::FOLDERS as $f) {
            if (str_starts_with($path, $f)) {
                return true;
            }
        }

        return false;
    }

    private function loadMap(): array
    {
        $disk = Storage::disk('local');

        return $disk->exists(self::MAP_FILE) ? (json_decode($disk->get(self::MAP_FILE), true) ?: []) : [];
    }
}