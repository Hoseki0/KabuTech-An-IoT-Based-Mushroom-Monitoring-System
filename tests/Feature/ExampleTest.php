<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_unauthenticated_user_redirected(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
    }

    public function test_login_page_returns_successful_response(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }
}
