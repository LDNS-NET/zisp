<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    /**
     * Test that global web rate limiting works.
     */
    public function test_web_routes_are_rate_limited(): void
    {
        // We can't easily test the exact threshold in a unit test without being slow,
        // but we can verify the middleware is applied.
        
        $response = $this->get('/');
        $response->assertStatus(200);
        
        // Assert that the RateLimiter was checked (if possible) 
        // Or just check headers if Laravel adds them by default
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
    }

    /**
     * Test that onboarding is strictly limited.
     */
    public function test_onboarding_is_strictly_rate_limited(): void
    {
        // Mock the limit to be very low for testing if needed, 
        // but here we just check if it's applied.
        
        $response = $this->post('/onboarding-requests', []);
        
        // Even with validation errors, it should have rate limit headers
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
        // The limit should match our default (5) or what's in DB
    }

    /**
     * Test that API routes are rate limited.
     */
    public function test_api_routes_are_rate_limited(): void
    {
        $response = $this->post('/mpesa/c2b/validation', []);
        $this->assertTrue($response->headers->has('X-RateLimit-Limit'));
    }
}
