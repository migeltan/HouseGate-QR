<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    public function test_console_folders_use_exact_namespace_casing(): void
    {
        // scandir() returns the real on-disk names, so this catches a case mismatch even on Windows.
        $this->assertContains('Console', scandir(app_path()));
        $this->assertContains('Commands', scandir(app_path('Console')));
    }

    public function test_scheduled_and_migration_commands_are_registered(): void
    {
        $registered = array_keys(Artisan::all());

        foreach (['passes:expire', 'passes:daily-sweep', 'photos:migrate-private'] as $name) {
            $this->assertContains($name, $registered, "Command {$name} is not registered; check app/Console/Commands casing.");
        }
    }
}