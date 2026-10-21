<?php

/*
| Isi page per template. Kategori page (page_types) menunjuk salah satu key di sini.
| 'tabs'   : tab tambahan di Edit Page => [label, icon, route relatif ke prefix pages/superadmin.pages]
| 'quick'  : tombol cepat di List Page => [label, icon, route, kolom withCount]
| 'public' : view isi halaman publik /page/{link}
| Template tanpa entri di sini hanya punya tab Informasi.
*/

return [
    'catalog' => [
        'tabs' => [
            'catalog-categories' => ['Kategori', 'ri-price-tag-3-line', 'catalog.categories.index'],
            'catalog-products' => ['Produk', 'ri-shopping-bag-3-line', 'catalog.products.index'],
            'catalog-orders' => ['Pesanan', 'ri-file-list-3-line', 'catalog.orders.index'],
        ],
        'quick' => ['Pesanan', 'ri-file-list-3-line', 'catalog.orders.index', 'catalog_orders_count'],
        'public' => 'page.public.catalog',
    ],

    'landing' => [
        'tabs' => [
            'landing-sections' => ['Konten', 'ri-layout-4-line', 'landing.sections.index'],
            'landing-settings' => ['Menu & Tampilan', 'ri-palette-line', 'landing.settings.edit'],
        ],
        'public' => 'page.public.landing',
    ],

    'ticket' => [
        'tabs' => [
            'ticket-categories' => ['Kategori Tiket', 'ri-coupon-3-line', 'ticket.categories.index'],
            'ticket-manage' => ['Atur Kategori Tiket', 'ri-equalizer-line', 'ticket.categories.manage'],
            'builder' => ['Form Pembeli', 'ri-list-settings-line', 'builder'],
            'ticket-orders' => ['Pesanan', 'ri-file-list-3-line', 'ticket.orders.index'],
            'ticket-design' => ['Desain E-ticket', 'ri-palette-line', 'ticket.design.edit'],
            'ticket-scanners' => ['Registrasi Ulang', 'ri-qr-scan-2-line', 'ticket.scanners.index'],
        ],
        'quick' => ['Pesanan', 'ri-coupon-3-line', 'ticket.orders.index', 'ticket_orders_count'],
        'public' => 'page.public.ticket',
    ],

    'biolink' => [
        'tabs' => [
            'biolink-links' => ['Link', 'ri-links-line', 'biolink.edit'],
        ],
        'public' => 'page.public.biolink',
    ],

    'quiz' => [
        'tabs' => [
            'quiz-questions' => ['Soal', 'ri-question-answer-line', 'quiz.questions.index'],
            'quiz-settings' => ['Pengaturan', 'ri-settings-3-line', 'quiz.settings.edit'],
            'quiz-attempts' => ['Hasil', 'ri-bar-chart-box-line', 'quiz.attempts.index'],
        ],
        'quick' => ['Hasil', 'ri-bar-chart-box-line', 'quiz.attempts.index', 'quiz_attempts_count'],
        'public' => 'page.public.quiz',
    ],
];
