<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_present(): void
    {
        $resp = $this->get('/admin/login');
        $resp->assertOk();

        $resp->assertHeader('X-Content-Type-Options', 'nosniff');
        $resp->assertHeader('X-Frame-Options', 'DENY');
        $resp->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $csp = $resp->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, 'CSP header must be set');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringNotContainsString("unsafe-eval", $csp, 'CSP must not allow eval');
    }
}
