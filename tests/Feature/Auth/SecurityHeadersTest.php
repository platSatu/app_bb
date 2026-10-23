<?php

it('halaman web memakai header keamanan', function () {
    $this->get(route('login'))->assertOk()
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('halaman publik /page/* tetap boleh di-embed', function () {
    $this->get('/page/tidak-ada')->assertHeaderMissing('X-Frame-Options');
});
