<?php

/*
| Satu-satunya daftar menu + kunci izin. Sidebar, middleware 'permission',
| dan halaman Role semuanya membaca file ini. Menu baru = tambah 1 item.
| Struktur: section > group (menu induk di sidebar) > items (submenu).
| Group juga boleh berisi 'submenus' (menu tingkat 3): group > submenu > items.
| 'superadmin_only' => true artinya tidak bisa diberikan ke role staff.
| 'sidebar' => false artinya tidak tampil di sidebar (mis. dipakai sebagai tab).
*/

return [
    // Aplikasi untuk user (owner). Nanti dibatasi sesuai package yang aktif.
    'apps' => [
        'label' => 'Aplikasi',
        'owner_app' => true,
        'groups' => [
            'pages' => [
                'label' => 'Pages',
                'app' => 'pages',
                'icon' => 'ri-pages-line',
                'items' => [
                    'pages.list' => [
                        'label' => 'List Page',
                        'route' => 'pages.index',
                        'active' => ['pages.*', '!pages.customers.*'],
                    ],
                    // Customer khusus Pages (pembeli tiket, katalog, peserta kuis).
                    'pages.customers' => [
                        'label' => 'Customers',
                        'route' => 'pages.customers.index',
                        'active' => 'pages.customers.*',
                    ],
                ],
            ],
            // Jadwal rutin & sesi, master data Ruangan > Bidang > Kategori > Grade, plus Pengajar & Murid.
            'schedules' => [
                'label' => 'Schedules',
                'app' => 'schedules',
                'icon' => 'ri-calendar-2-line',
                'items' => [
                    'schedules.routines' => [
                        'label' => 'Jadwal Rutin',
                        'route' => 'schedules.routines.index',
                        'active' => 'schedules.routines.*',
                    ],
                    'schedules.sessions' => [
                        'label' => 'Sesi & Absensi',
                        'route' => 'schedules.sessions.index',
                        'active' => 'schedules.sessions.*',
                    ],
                    'schedules.rooms' => [
                        'label' => 'Ruangan',
                        'route' => 'schedules.rooms.index',
                        'active' => 'schedules.rooms.*',
                    ],
                    'schedules.subjects' => [
                        'label' => 'Bidang',
                        'route' => 'schedules.subjects.index',
                        'active' => 'schedules.subjects.*',
                    ],
                    'schedules.categories' => [
                        'label' => 'Kategori',
                        'route' => 'schedules.categories.index',
                        'active' => 'schedules.categories.*',
                    ],
                    'schedules.grades' => [
                        'label' => 'Grade',
                        'route' => 'schedules.grades.index',
                        'active' => 'schedules.grades.*',
                    ],
                    'schedules.teachers' => [
                        'label' => 'Pengajar',
                        'route' => 'schedules.teachers.index',
                        'active' => 'schedules.teachers.*',
                    ],
                    'schedules.students' => [
                        'label' => 'Murid',
                        'route' => 'schedules.students.index',
                        'active' => 'schedules.students.*',
                    ],
                    'schedules.portal' => [
                        'label' => 'Portal Murid & Pengajar',
                        'route' => 'schedules.portal.requests',
                        'active' => 'schedules.portal.*',
                    ],
                ],
            ],
            // Tagihan ke customer: dari dashboard (kategori > periode) atau lewat API website.
            'invoices' => [
                'label' => 'Invoice',
                'app' => 'invoices',
                'icon' => 'ri-file-list-3-line',
                'items' => [
                    'invoices.list' => ['label' => 'Semua Invoice', 'route' => 'invoices.index', 'active' => ['invoices.index', 'invoices.show']],
                    'invoices.batches' => ['label' => 'Invoice Periode', 'route' => 'invoices.batches.index', 'active' => 'invoices.batches.*'],
                    'invoices.categories' => ['label' => 'Kategori', 'route' => 'invoices.categories.index', 'active' => 'invoices.categories.*'],
                    // Customer khusus Invoice (orang yang ditagih).
                    'invoices.customers' => ['label' => 'Customers', 'route' => 'invoices.customers.index', 'active' => 'invoices.customers.*'],
                    'invoices.api' => ['label' => 'API & Integrasi', 'route' => 'invoices.api.index', 'active' => ['invoices.api.*', 'invoices.docs']],
                    // Kirim dana dari saldo ke banyak rekening (dashboard / API). Tab di dalam halaman.
                    'invoices.disbursements' => ['label' => 'Disbursement', 'route' => 'invoices.disbursements.index', 'active' => 'invoices.disbursements.*'],
                ],
            ],
            // Nomor WhatsApp per branch, inbox realtime, dan Phone Book (Buku Kontak + Group).
            'whatsapp' => [
                'label' => 'WhatsApp',
                'app' => 'whatsapp',
                'icon' => 'ri-whatsapp-line',
                'items' => [
                    'whatsapp.devices' => ['label' => 'Device & Inbox', 'route' => 'whatsapp.devices.index', 'active' => ['whatsapp.devices.*', 'whatsapp.inbox.*']],
                ],
                'submenus' => [
                    'settings' => [
                        'label' => 'Settings',
                        'items' => [
                            'whatsapp.messages' => ['label' => 'Message', 'route' => 'whatsapp.messages.index', 'active' => 'whatsapp.messages.*'],
                            'whatsapp.flows' => ['label' => 'Chatbot Flow', 'route' => 'whatsapp.flows.index', 'active' => 'whatsapp.flows.*'],
                            'whatsapp.auto-replies' => ['label' => 'Auto Reply', 'route' => 'whatsapp.auto-replies.index', 'active' => 'whatsapp.auto-replies.*'],
                            'whatsapp.quick-replies' => ['label' => 'Quick Reply', 'route' => 'whatsapp.quick-replies.index', 'active' => 'whatsapp.quick-replies.*'],
                            'whatsapp.ai-bots' => ['label' => 'AI Bot', 'route' => 'whatsapp.ai-bots.index', 'active' => 'whatsapp.ai-bots.*'],
                            'whatsapp.templates' => ['label' => 'Template', 'route' => 'whatsapp.templates.index', 'active' => 'whatsapp.templates.*'],
                            'whatsapp.labels' => ['label' => 'Label', 'route' => 'whatsapp.labels.index', 'active' => 'whatsapp.labels.*'],
                        ],
                    ],
                    'phone-book' => [
                        'label' => 'Phone Book',
                        'items' => [
                            // Customer khusus WhatsApp.
                            'whatsapp.contacts' => ['label' => 'Customers', 'route' => 'whatsapp.contacts.index', 'active' => 'whatsapp.contacts.*'],
                            'whatsapp.groups' => ['label' => 'Group', 'route' => 'whatsapp.groups.index', 'active' => 'whatsapp.groups.*'],
                            'whatsapp.wa-groups' => ['label' => 'Group WhatsApp', 'route' => 'whatsapp.wa-groups.index', 'active' => 'whatsapp.wa-groups.*'],
                        ],
                    ],
                    // Aplikasi lain yang memakai WhatsApp bizbos: Integrasi Web (API Key + dokumentasi) dan Google Form.
                    'third-party' => [
                        'label' => 'Third Party',
                        'items' => [
                            'whatsapp.third-party' => ['label' => 'Integrasi Web', 'route' => 'whatsapp.third-party.index', 'active' => ['whatsapp.third-party.*', 'whatsapp.api-key.*']],
                            'whatsapp.google-forms' => ['label' => 'Google Form', 'route' => 'whatsapp.google-forms.index', 'active' => 'whatsapp.google-forms.*'],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'superadmin' => [
        'label' => 'Superadmin',
        'groups' => [
            'data-user' => [
                'label' => 'Data User',
                'icon' => 'ri-group-line',
                'items' => [
                    'superadmin.users' => [
                        'label' => 'User',
                        'route' => 'superadmin.users.index',
                        'active' => ['superadmin.users.*', 'superadmin.companies.*', 'superadmin.branches.*', 'superadmin.referral.*'],
                    ],
                    // Semua customer milik owner (semua aplikasi), lengkap dengan export/import Excel.
                    'superadmin.customers' => [
                        'label' => 'Customer',
                        'route' => 'superadmin.contacts.index',
                        'active' => 'superadmin.contacts.*',
                    ],
                    // Tiga item ini tampil sebagai tab di halaman User, bukan di sidebar.
                    'superadmin.companies' => [
                        'label' => 'Company',
                        'route' => 'superadmin.companies.index',
                        'active' => 'superadmin.companies.*',
                        'sidebar' => false,
                    ],
                    'superadmin.branches' => [
                        'label' => 'Branch',
                        'route' => 'superadmin.branches.index',
                        'active' => 'superadmin.branches.*',
                        'sidebar' => false,
                    ],
                    'superadmin.referral' => [
                        'label' => 'Referral',
                        'route' => 'superadmin.referral.index',
                        'active' => 'superadmin.referral.*',
                        'sidebar' => false,
                    ],
                ],
            ],
            // Aplikasi yang dijual, sama seperti menu user, tapi superadmin mengelola semua datanya.
            'applications' => [
                'label' => 'Applications',
                'icon' => 'ri-apps-2-line',
                'submenus' => [
                    'pages' => [
                        'label' => 'Pages',
                        'items' => [
                            // Kategori yang dipilih user saat membuat page.
                            'superadmin.page-types' => [
                                'label' => 'Category Pages',
                                'route' => 'superadmin.page-types.index',
                                'active' => 'superadmin.page-types.*',
                            ],
                            'superadmin.pages' => [
                                'label' => 'List Pages',
                                'route' => 'superadmin.pages.index',
                                'active' => 'superadmin.pages.*',
                            ],
                        ],
                    ],
                    'schedules' => [
                        'label' => 'Schedules',
                        'items' => [
                            'superadmin.schedules' => [
                                'label' => 'Data Schedules',
                                'route' => 'superadmin.schedules.rooms.index',
                                'active' => 'superadmin.schedules.*',
                            ],
                        ],
                    ],
                    'invoices' => [
                        'label' => 'Invoice',
                        'items' => [
                            'superadmin.invoices' => [
                                'label' => 'List Invoice',
                                'route' => 'superadmin.invoices.index',
                                'active' => ['superadmin.invoices.index', 'superadmin.invoices.show'],
                            ],
                            // Biaya layanan bizbos yang dipotong dari setiap pembayaran invoice.
                            'superadmin.invoice-settings' => [
                                'label' => 'Biaya Invoice',
                                'route' => 'superadmin.invoices.settings',
                                'active' => 'superadmin.invoices.settings*',
                                'superadmin_only' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'packages' => [
                'label' => 'Packages',
                'icon' => 'ri-box-3-line',
                'items' => [
                    'superadmin.category-applications' => [
                        'label' => 'Kategori Aplikasi',
                        'route' => 'superadmin.category-applications.index',
                        'active' => 'superadmin.category-applications.*',
                    ],
                    'superadmin.packages' => [
                        'label' => 'Package',
                        'route' => 'superadmin.packages.index',
                        'active' => 'superadmin.packages.*',
                    ],
                    'superadmin.vouchers' => [
                        'label' => 'Voucher',
                        'route' => 'superadmin.vouchers.index',
                        'active' => 'superadmin.vouchers.*',
                    ],
                ],
            ],
            'deposit' => [
                'label' => 'Deposit',
                'icon' => 'ri-wallet-3-line',
                'items' => [
                    // Tab Data Deposit & History Saldo User.
                    'superadmin.deposits' => [
                        'label' => 'Data Deposit',
                        'route' => 'superadmin.deposits.index',
                        'active' => ['superadmin.deposits.*', 'superadmin.wallet-adjustment.*'],
                    ],
                    // Lihat = lihat pengaturan, Kelola = ubah kredensial Duitku.
                    'superadmin.payment-gateway' => [
                        'label' => 'Payment Gateway',
                        'route' => 'superadmin.payment-gateway.setting',
                        'active' => 'superadmin.payment-gateway.*',
                    ],
                    // Pengajuan, verifikasi rekening, dan kredensial Duitku Disbursement (tab).
                    'superadmin.withdrawals' => [
                        'label' => 'Penarikan Saldo',
                        'route' => 'superadmin.withdrawals.index',
                        'active' => ['superadmin.withdrawals.*', 'superadmin.bank-accounts.*', 'superadmin.disbursement.*', 'superadmin.payouts.*'],
                        'superadmin_only' => true,
                    ],
                    'superadmin.wallet-adjustment' => [
                        'label' => 'Koreksi Saldo',
                        'route' => 'superadmin.wallet-adjustment.create',
                        'active' => 'superadmin.wallet-adjustment.*',
                        'superadmin_only' => true,
                        'sidebar' => false,
                    ],
                ],
            ],
            'settings' => [
                'label' => 'Settings',
                'icon' => 'ri-settings-3-line',
                'items' => [
                    'superadmin.roles' => [
                        'label' => 'Role',
                        'route' => 'superadmin.roles.index',
                        'active' => 'superadmin.roles.*',
                        'superadmin_only' => true,
                    ],
                    // Berisi API key penyedia AI, jadi khusus superadmin.
                    'superadmin.ai' => [
                        'label' => 'AI',
                        'route' => 'superadmin.ai.index',
                        'active' => 'superadmin.ai.*',
                        'superadmin_only' => true,
                    ],
                    // Widget tanya-jawab di header bizbos.id (jawaban dari AI + konten Website).
                    'superadmin.web.live-chat' => [
                        'label' => 'Live Chat',
                        'route' => 'superadmin.web.live-chat.edit',
                        'active' => 'superadmin.web.live-chat.*',
                    ],
                    // Antrean email/job, job gagal, pembayaran & transfer yang tertahan.
                    'superadmin.monitor' => [
                        'label' => 'Antrean & Tugas',
                        'route' => 'superadmin.monitor.index',
                        'active' => 'superadmin.monitor.*',
                        'superadmin_only' => true,
                    ],
                    // Semua email aplikasi + status kirim & kirim ulang. Berisi alamat email user, jadi khusus superadmin.
                    'superadmin.emails' => [
                        'label' => 'Riwayat Email',
                        'route' => 'superadmin.emails.index',
                        'active' => 'superadmin.emails.*',
                        'superadmin_only' => true,
                    ],
                    // Tab Pengunjung Website (bizbos.id) & Aktivitas Pengguna (app.bizbos). Berisi IP, jadi khusus superadmin.
                    'superadmin.activity' => [
                        'label' => 'Aktivitas & Pengunjung',
                        'route' => 'superadmin.activity.visitors',
                        'active' => 'superadmin.activity.*',
                        'superadmin_only' => true,
                    ],
                    // Berisi seluruh data, jadi khusus superadmin.
                    'superadmin.backups' => [
                        'label' => 'Backup Database',
                        'route' => 'superadmin.backups.index',
                        'active' => 'superadmin.backups.*',
                        'superadmin_only' => true,
                    ],
                ],
                // Konten website bizbos yang dibaca fe-konexa lewat API.
                'submenus' => [
                    'website' => [
                        'label' => 'Website',
                        'items' => [
                            'superadmin.web.meta-tags' => ['label' => 'Meta Tags', 'route' => 'superadmin.web.meta-tags.index', 'active' => 'superadmin.web.meta-tags.*'],
                            'superadmin.web.category-articles' => ['label' => 'Kategori Artikel', 'route' => 'superadmin.web.category-articles.index', 'active' => 'superadmin.web.category-articles.*'],
                            'superadmin.web.articles' => ['label' => 'Artikel', 'route' => 'superadmin.web.articles.index', 'active' => 'superadmin.web.articles.*'],
                            'superadmin.web.faqs' => ['label' => 'FAQ', 'route' => 'superadmin.web.faqs.index', 'active' => 'superadmin.web.faqs.*'],
                            'superadmin.web.term-conditions' => ['label' => 'Syarat & Ketentuan', 'route' => 'superadmin.web.term-conditions.index', 'active' => 'superadmin.web.term-conditions.*'],
                            'superadmin.web.category-videos' => ['label' => 'Kategori Video', 'route' => 'superadmin.web.category-videos.index', 'active' => 'superadmin.web.category-videos.*'],
                            'superadmin.web.videos' => ['label' => 'Video', 'route' => 'superadmin.web.videos.index', 'active' => 'superadmin.web.videos.*'],
                            'superadmin.web.headers' => ['label' => 'Header', 'route' => 'superadmin.web.headers.index', 'active' => 'superadmin.web.headers.*'],
                            'superadmin.web.home-sections' => ['label' => 'Susunan Beranda', 'route' => 'superadmin.web.home-sections.index', 'active' => ['superadmin.web.home-sections.*', 'superadmin.web.home-section-items.*']],
                            'superadmin.web.pages' => ['label' => 'Halaman', 'route' => 'superadmin.web.pages.index', 'active' => 'superadmin.web.pages.*'],
                            'superadmin.web.contact-messages' => ['label' => 'Pesan Masuk', 'route' => 'superadmin.web.contact-messages.index', 'active' => 'superadmin.web.contact-messages.*'],
                            'superadmin.web.features' => ['label' => 'Fitur', 'route' => 'superadmin.web.features.index', 'active' => 'superadmin.web.features.*'],
                            'superadmin.web.footers' => ['label' => 'Footer', 'route' => 'superadmin.web.footers.index', 'active' => 'superadmin.web.footers.*'],
                            'superadmin.web.setting' => ['label' => 'Pengaturan Web', 'route' => 'superadmin.web.setting.edit', 'active' => 'superadmin.web.setting.*'],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
