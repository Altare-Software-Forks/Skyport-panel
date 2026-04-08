<?php

test('web responses include secure headers', function () {
    $this->get('/login')
        ->assertOk()
        ->assertHeader(
            'Content-Security-Policy',
            "base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'",
        )
        ->assertHeader(
            'Permissions-Policy',
            'camera=(), geolocation=(), microphone=()',
        )
        ->assertHeader(
            'Referrer-Policy',
            'strict-origin-when-cross-origin',
        )
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY');
});
