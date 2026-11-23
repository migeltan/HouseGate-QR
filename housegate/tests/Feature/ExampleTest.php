<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // Builds the schema in the in-memory SQLite test database (the login page lists buildings).
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // Don't require a built Vite manifest (npm run build) just to render the login page.
        $this->withoutVite();

        $response = $this->get('/login');

        $response->assertStatus(200);
    }
}