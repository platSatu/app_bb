@extends('layouts.dashboard')

@section('title', 'Buat Pesan')
@section('page-section', 'WhatsApp')

@php
    $oldType = old('type', $type);
    $oldSteps = old('steps', [['delay_days' => 0], ['delay_days' => 3]]);
    $typeCards = [
        'once' => ['ri-send-plane-line', 'Broadcast langsung atau terjadwal, satu kali di satu tanggal & jam.'],
        'recurring' => ['ri-repeat-line', 'Terkirim otomatis setiap hari, minggu, atau bulan dalam rentang tanggal yang dipilih.'],
        'drip' => ['ri-flow-chart', 'Beberapa pesan berjarak hari (drip), sama untuk semua tujuan.'],
    ];
    $templateOptions = $templates->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'text' => $t->toText(), 'file' => $t->file_name]);
@endphp

@push('styles')
    <style>
        /* Warna utama tema (--pe-primary-rgb), dengan cadangan bila variabelnya tidak ada. */
        .wa-type-card { --wa-rgb: var(--pe-primary-rgb, 223, 91, 79); cursor: pointer; border: 1px solid rgba(var(--wa-rgb), .45); border-radius: .5rem; padding: 1rem; height: 100%; transition: .15s; }
        .wa-type-card:hover { border-color: rgb(var(--wa-rgb)); }
        .wa-type-input:checked + .wa-type-card { background: rgb(var(--wa-rgb)); border-color: rgb(var(--wa-rgb)); color: #fff; }
        .wa-type-input:checked + .wa-type-card .text-muted, .wa-type-input:checked + .wa-type-card .text-primary { color: rgba(255, 255, 255, .9) !important; }
        .wa-pick-list { max-height: 260px; overflow-y: auto; }
        .wa-step + .wa-step { margin-top: .75rem; }
    </style>
@endpush

@section('content')
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h5 class="mb-0">Tambah Jadwal Pesan</h5>
            <div class="small text-muted">Jadwalkan pesan WhatsApp: sekali kirim, berulang, atau bertahap, ke satu atau banyak tujuan sekaligus.</div>
        </div>
        <div class="d-flex gap-2 align-items-center">
            @include('whatsapp._branch-filter')
            <a href="{{ route('whatsapp.messages.index', ['branch' => $branch->id]) }}" class="btn btn-light btn-sm"><i class="ri-arrow-left-line"></i> Kembali</a>
        </div>
    </div>

    @if ($devices->isEmpty())
        <div class="card"><div class="card-body text-center text-muted py-5">Belum ada nomor WhatsApp di branch ini. <a href="{{ route('whatsapp.devices.index', ['branch' => $branch->id]) }}">Hubungkan dulu</a>.</div></div>
    @else
        @if ($errors->any())
            <div class="alert alert-danger small">Periksa lagi isian yang ditandai merah di bawah.</div>
        @endif

        <form method="POST" action="{{ route('whatsapp.messages.store') }}" enctype="multipart/form-data" id="wa-message-form"
              data-groups-url="{{ route('whatsapp.messages.wa-groups', '__ID__') }}" data-old-groups='@json(array_values((array) old('wa_groups', [])))'>
            @csrf

            {{-- Jenis --}}
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Jenis Pengiriman</h6>
                    <div class="row g-2">
                        @foreach (\App\Models\WaBroadcast::TYPES as $key => $label)
                            <div class="col-md-4">
                                <input type="radio" class="btn-check wa-type-input" name="type" id="type-{{ $key }}" value="{{ $key }}" @checked($oldType === $key)>
                                <label class="wa-type-card d-block" for="type-{{ $key }}">
                                    <div class="fw-semibold text-primary mb-1"><i class="{{ $typeCards[$key][0] }} me-1"></i> {{ $label }}</div>
                                    <div class="small text-muted">{{ $typeCards[$key][1] }}</div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Device & nama --}}
            <div class="card">
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Kirim dari</label>
                        <select name="device_id" class="form-select" required data-device>
                            @foreach ($devices as $device)
                                <option value="{{ $device->id }}" @selected(old('device_id') === $device->id)>{{ $device->name }}{{ $device->phone ? ' · +'.$device->phone : '' }}{{ $device->status === 'connected' ? '' : ' (terputus)' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama / Judul</label>
                        <input type="text" name="name" maxlength="150" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="Contoh: Reminder Tagihan Bulanan">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            {{-- Isi pesan: sekali kirim & berulang --}}
            <div class="card" data-section="single">
                <div class="card-body" data-content>
                    <div class="form-check form-switch mb-1">
                        <input class="form-check-input" type="checkbox" role="switch" name="use_template" value="1" id="use-template" data-use-template @checked(old('use_template'))>
                        <label class="form-check-label fw-semibold" for="use-template"><i class="ri-file-list-3-line"></i> Gunakan Template WA</label>
                    </div>
                    <div class="small text-muted mb-3">Aktifkan untuk memilih dari <a href="{{ route('whatsapp.templates.index', ['branch' => $branch->id]) }}">WA Template</a> yang sudah dibuat, atau matikan untuk menulis pesan manual.</div>

                    <div data-template-pane>
                        <label class="form-label">Template</label>
                        <select name="wa_template_id" class="form-select @error('wa_template_id') is-invalid @enderror" data-template-select>
                            <option value="">-- Pilih Template --</option>
                            @foreach ($templates as $template)
                                <option value="{{ $template->id }}" @selected(old('wa_template_id') === $template->id)>{{ $template->name }}</option>
                            @endforeach
                        </select>
                        @error('wa_template_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="bg-light rounded p-3 mt-2 small d-none" style="white-space: pre-wrap" data-template-preview></div>
                    </div>

                    <div data-manual-pane>
                        <label class="form-label">Isi Pesan</label>
                        <textarea name="body" rows="6" maxlength="4000" class="form-control @error('body') is-invalid @enderror" placeholder="Halo {nama}, ...">{{ old('body') }}</textarea>
                        @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text mb-3">Tulis <code>{nama}</code> untuk memanggil nama kontak (otomatis "Kak" bila namanya belum ada).</div>
                        <label class="form-label">Lampiran (opsional)</label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror">
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Foto, video, audio, atau dokumen sampai 64 MB. Isi pesan dikirim sebagai keterangan.</div>
                    </div>
                </div>
            </div>

            {{-- Isi pesan: bertahap --}}
            <div class="card" data-section="drip">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0">Urutan Pesan</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-add-step><i class="ri-add-line"></i> Tambah Pesan</button>
                    </div>
                    <div class="small text-muted mb-3">Setiap pesan dikirim ke semua tujuan pada <strong>tanggal mulai + H hari</strong>, di jam kirim yang sama. Maksimal {{ \App\Models\WaBroadcast::MAX_STEPS }} pesan.</div>
                    @error('steps') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                    <div data-steps>
                        @foreach ($oldSteps as $i => $step)
                            @include('whatsapp.messages._step', ['i' => $i, 'step' => $step])
                        @endforeach
                    </div>
                    <template data-step-template>
                        @include('whatsapp.messages._step', ['i' => '__I__', 'step' => ['delay_days' => '']])
                    </template>
                </div>
            </div>

            {{-- Jadwal --}}
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Jadwal</h6>

                    <div data-section="once">
                        <div class="d-flex flex-wrap gap-3 mb-2">
                            @foreach (['now' => 'Kirim sekarang', 'at' => 'Pada waktu tertentu'] as $key => $label)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="schedule" value="{{ $key }}" id="sch-{{ $key }}" data-once-schedule @checked(old('schedule', 'now') === $key)>
                                    <label class="form-check-label" for="sch-{{ $key }}">{{ $label }}</label>
                                </div>
                            @endforeach
                        </div>
                        <div class="col-md-4" data-send-at>
                            <label class="form-label small">Tanggal & jam kirim</label>
                            <input type="datetime-local" name="send_at" class="form-control @error('send_at') is-invalid @enderror" value="{{ old('send_at') }}">
                            @error('send_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="row g-3" data-section="timed">
                        <div class="col-md-3" data-section="recurring">
                            <label class="form-label">Ulangi</label>
                            <select name="schedule" class="form-select">
                                @foreach (['daily', 'weekly', 'monthly'] as $key)
                                    <option value="{{ $key }}" @selected(old('schedule') === $key)>{{ \App\Models\WaBroadcast::SCHEDULES[$key] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <input type="hidden" name="schedule" value="drip" data-section="drip">
                        <div class="col-md-3">
                            <label class="form-label">Tanggal Mulai</label>
                            <input type="date" name="start_date" min="{{ now()->toDateString() }}" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', now()->toDateString()) }}">
                            @error('start_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-3" data-section="recurring">
                            <label class="form-label">Tanggal Berakhir</label>
                            <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date') }}">
                            @error('end_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            <div class="form-text">Kosongkan kalau berulang terus sampai dijeda/dibatalkan.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Jam Kirim</label>
                            <input type="time" name="send_time" class="form-control @error('send_time') is-invalid @enderror" value="{{ old('send_time', '09:00') }}">
                            @error('send_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="form-text mt-2">Pengiriman ke banyak tujuan dijeda acak, tidak dikirim pukul 21.00–07.00, dan mengikuti batas harian nomor supaya aman dari pembatasan WhatsApp.</div>
                </div>
            </div>

            {{-- Tujuan --}}
            <div class="card">
                <div class="card-body">
                    <h6 class="mb-3">Tujuan Pengiriman</h6>
                    @error('recipients') <div class="alert alert-danger small py-2">{{ $message }}</div> @enderror
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item"><button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#to-contacts"><i class="ri-contacts-book-line"></i> Kontak <span class="badge rounded-pill bg-primary-subtle text-primary ms-1" data-count="contacts">0</span></button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#to-phones"><i class="ri-smartphone-line"></i> Nomor Tujuan <span class="badge rounded-pill bg-primary-subtle text-primary ms-1" data-count="phones">0</span></button></li>
                        <li class="nav-item"><button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#to-groups"><i class="ri-group-line"></i> Grup WhatsApp <span class="badge rounded-pill bg-primary-subtle text-primary ms-1" data-count="groups">0</span></button></li>
                    </ul>
                    <div class="tab-content border border-top-0 rounded-bottom p-3">
                        <div class="tab-pane fade show active" id="to-contacts">
                            <div class="row g-3">
                                <div class="col-lg-7">
                                    <div class="small fw-semibold text-muted mb-1">Kontak</div>
                                    <input type="search" class="form-control form-control-sm mb-2" placeholder="Cari nama atau nomor" data-filter="contacts">
                                    <div class="border rounded p-2 wa-pick-list" data-list="contacts">
                                        @forelse ($customers as $customer)
                                            @if ($loop->first || $customers[$loop->index - 1]->app_key !== $customer->app_key)
                                                <div class="small fw-semibold text-muted {{ $loop->first ? '' : 'mt-2' }}" data-row data-text="">Customers {{ $customer->appLabel() }}</div>
                                            @endif
                                            <div class="form-check" data-row data-text="{{ strtolower($customer->name.' '.$customer->phone) }}">
                                                <input class="form-check-input" type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" id="c-{{ $customer->id }}" data-pick="contacts" @checked(in_array($customer->id, (array) old('customer_ids', [])))>
                                                <label class="form-check-label" for="c-{{ $customer->id }}">{{ $customer->displayName() }} <span class="text-muted small">+{{ $customer->phone }}</span></label>
                                            </div>
                                        @empty
                                            <div class="text-muted small">Belum ada kontak dengan nomor WhatsApp.</div>
                                        @endforelse
                                    </div>
                                </div>
                                <div class="col-lg-5">
                                    <div class="small fw-semibold text-muted mb-1">Group kontak</div>
                                    <div class="border rounded p-2 mb-3 wa-pick-list" style="max-height: 150px">
                                        @forelse ($groups as $group)
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="group_ids[]" value="{{ $group->id }}" id="grp-{{ $group->id }}" data-pick="contacts" @checked(in_array($group->id, (array) old('group_ids', [])))>
                                                <label class="form-check-label" for="grp-{{ $group->id }}">{{ $group->name }} <span class="text-muted small">({{ $group->customers_count }} nomor)</span></label>
                                            </div>
                                        @empty
                                            <div class="text-muted small">Belum ada Group kontak. <a href="{{ route('whatsapp.groups.index', ['branch' => $branch->id]) }}">Buat Group</a>.</div>
                                        @endforelse
                                    </div>
                                    <div class="small fw-semibold text-muted mb-1">Semua customer aplikasi</div>
                                    @foreach (\App\Models\Customer::APPS as $app => $label)
                                        @continue($app === 'whatsapp')
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="apps[]" value="{{ $app }}" id="app-{{ $app }}" data-pick="contacts" @checked(in_array($app, (array) old('apps', []))) @disabled(! ($appCounts[$app] ?? 0))>
                                            <label class="form-check-label" for="app-{{ $app }}">Semua customer {{ $label }} <span class="text-muted small">({{ $appCounts[$app] ?? 0 }} nomor)</span></label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="form-text mt-2">Kontak yang terpilih lebih dari sekali hanya dikirimi sekali. Nomor yang membalas STOP otomatis dilewati.</div>
                        </div>

                        <div class="tab-pane fade" id="to-phones">
                            <label class="form-label">Nomor WhatsApp Tujuan</label>
                            <textarea name="phones" rows="5" class="form-control" placeholder="6281234567890; 6281298765432&#10;atau satu nomor per baris" data-phones>{{ is_array(old('phones')) ? implode("\n", old('phones')) : old('phones') }}</textarea>
                            <div class="form-text">Pisahkan tiap nomor dengan titik-koma (;), koma, atau baris baru.</div>
                        </div>

                        <div class="tab-pane fade" id="to-groups">
                            <div class="d-flex gap-2 mb-2">
                                <input type="search" class="form-control form-control-sm" placeholder="Cari grup" data-filter="groups">
                                <button type="button" class="btn btn-sm btn-light text-nowrap" data-refresh-groups><i class="ri-refresh-line"></i> Muat ulang</button>
                            </div>
                            <div class="border rounded p-2 wa-pick-list" data-list="groups">
                                <div class="text-muted small" data-groups-status>Memuat grup dari device...</div>
                            </div>
                            <div class="form-text">Grup yang hanya admin boleh mengirim pesan tidak bisa dipilih bila nomor ini bukan admin.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mb-4">
                <button class="btn btn-primary" data-submit><i class="ri-send-plane-2-line"></i> Simpan Jadwal</button>
                <a href="{{ route('whatsapp.messages.index', ['branch' => $branch->id]) }}" class="btn btn-light">Batal</a>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('wa-message-form');
            if (!form) return;
            var templates = @json($templateOptions);
            var maxSteps = {{ \App\Models\WaBroadcast::MAX_STEPS }};
            var qs = function (s, root) { return (root || form).querySelector(s); };
            var qsa = function (s, root) { return Array.prototype.slice.call((root || form).querySelectorAll(s)); };

            // ---- Jenis: tampilkan bagian yang relevan; input di bagian tersembunyi dimatikan supaya tidak ikut terkirim.
            function setSection(el, on) {
                el.classList.toggle('d-none', !on);
                (el.matches('input, select, textarea') ? [el] : qsa('input, select, textarea', el)).forEach(function (i) {
                    if (!i.closest('template')) i.disabled = !on || !!i.closest('.d-none');
                });
            }
            function syncType() {
                var type = qs('[name="type"]:checked').value;
                qsa('[data-section="single"]').forEach(function (el) { setSection(el, type !== 'drip'); });
                qsa('[data-section="drip"]').forEach(function (el) { setSection(el, type === 'drip'); });
                qsa('[data-section="once"]').forEach(function (el) { setSection(el, type === 'once'); });
                qsa('[data-section="timed"]').forEach(function (el) { setSection(el, type !== 'once'); });
                qsa('[data-section="recurring"]').forEach(function (el) { setSection(el, type === 'recurring'); });
                qsa('[data-section="drip"]').forEach(function (el) { setSection(el, type === 'drip'); });
                syncOnce();
                qsa('[data-content]').forEach(syncTemplate);
            }
            function syncOnce() {
                var at = qs('[data-once-schedule][value="at"]');
                var box = qs('[data-send-at]');
                var on = !at.disabled && at.checked;
                box.classList.toggle('d-none', !on);
                qs('input', box).disabled = !on;
            }
            qsa('[name="type"]').forEach(function (r) { r.addEventListener('change', syncType); });
            qsa('[data-once-schedule]').forEach(function (r) { r.addEventListener('change', syncOnce); });

            // ---- Template vs tulis manual (berlaku untuk pesan utama & tiap pesan bertahap).
            function syncTemplate(box) {
                var toggle = qs('[data-use-template]', box);
                if (!toggle || toggle.disabled) return;
                var on = toggle.checked;
                var tpl = qs('[data-template-pane]', box), manual = qs('[data-manual-pane]', box);
                tpl.classList.toggle('d-none', !on);
                manual.classList.toggle('d-none', on);
                qsa('input, select, textarea', tpl).forEach(function (i) { i.disabled = !on; });
                qsa('input, select, textarea', manual).forEach(function (i) { i.disabled = on; });
                var select = qs('[data-template-select]', box), preview = qs('[data-template-preview]', box);
                var t = templates.find(function (x) { return x.id === select.value; });
                preview.classList.toggle('d-none', !on || !t);
                preview.textContent = t ? t.text + (t.file ? '\n\n📎 ' + t.file : '') : '';
            }
            form.addEventListener('change', function (e) {
                var box = e.target.closest('[data-content]');
                if (box && (e.target.matches('[data-use-template]') || e.target.matches('[data-template-select]'))) syncTemplate(box);
            });

            // ---- Pesan bertahap: tambah / hapus.
            var stepsBox = qs('[data-steps]');
            var counter = qsa('[data-step]', stepsBox).length + 100;
            function renumber() {
                qsa('[data-step]', stepsBox).forEach(function (step, i) {
                    qs('[data-step-no]', step).textContent = i + 1;
                    qs('[data-remove-step]', step).classList.toggle('d-none', qsa('[data-step]', stepsBox).length < 2);
                });
                qs('[data-add-step]').disabled = qsa('[data-step]', stepsBox).length >= maxSteps;
            }
            qs('[data-add-step]').addEventListener('click', function () {
                var html = qs('[data-step-template]').innerHTML.replace(/__I__/g, counter++);
                stepsBox.insertAdjacentHTML('beforeend', html);
                var last = qsa('[data-step]', stepsBox).pop();
                var prev = qsa('[data-step] [name$="[delay_days]"]', stepsBox).map(function (i) { return parseInt(i.value, 10) || 0; });
                qs('[name$="[delay_days]"]', last).value = Math.max.apply(null, prev.slice(0, -1).concat([0])) + 1;
                syncTemplate(qs('[data-content]', last));
                renumber();
            });
            stepsBox.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-remove-step]');
                if (btn) { btn.closest('[data-step]').remove(); renumber(); }
            });

            // ---- Hitungan tujuan per tab.
            function count() {
                qs('[data-count="contacts"]').textContent = qsa('[data-pick="contacts"]:checked').length;
                qs('[data-count="groups"]').textContent = qsa('[name="wa_groups[]"]:checked').length;
                var phones = qs('[data-phones]').value.split(/[\s,;]+/).filter(function (p) { return p.replace(/\D/g, '').length >= 9; });
                qs('[data-count="phones"]').textContent = phones.length;
            }
            form.addEventListener('change', count);
            qs('[data-phones]').addEventListener('input', count);

            // ---- Pencarian di daftar kontak & grup.
            qsa('[data-filter]').forEach(function (input) {
                input.addEventListener('input', function () {
                    var q = input.value.toLowerCase();
                    qsa('[data-row]', qs('[data-list="' + input.dataset.filter + '"]')).forEach(function (row) {
                        row.style.display = !q || (row.dataset.text && row.dataset.text.indexOf(q) > -1) ? '' : 'none';
                    });
                });
            });

            // ---- Grup WhatsApp: dimuat dari device yang dipilih.
            var groupsBox = qs('[data-list="groups"]');
            var picked = JSON.parse(form.dataset.oldGroups || '[]');
            function loadGroups(refresh) {
                var device = qs('[data-device]').value;
                groupsBox.innerHTML = '<div class="text-muted small">Memuat grup dari device...</div>';
                fetch(form.dataset.groupsUrl.replace('__ID__', device) + (refresh ? '?refresh=1' : ''), { headers: { Accept: 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        groupsBox.innerHTML = '';
                        if (!res.groups || !res.groups.length) {
                            groupsBox.innerHTML = '<div class="text-muted small"></div>';
                            groupsBox.firstChild.textContent = res.message || 'Nomor ini belum ikut grup WhatsApp mana pun.';
                            return count();
                        }
                        res.groups.forEach(function (g, i) {
                            var locked = g.announce && !g.is_admin;
                            var row = document.createElement('div');
                            row.className = 'form-check';
                            row.dataset.row = '';
                            row.dataset.text = (g.name || '').toLowerCase();
                            var input = document.createElement('input');
                            input.type = 'checkbox'; input.className = 'form-check-input'; input.name = 'wa_groups[]'; input.value = g.jid; input.id = 'wg-' + i;
                            input.disabled = locked;
                            input.checked = !locked && picked.indexOf(g.jid) > -1;
                            var label = document.createElement('label');
                            label.className = 'form-check-label'; label.htmlFor = input.id;
                            label.textContent = g.name + (g.size ? ' (' + g.size + ' anggota)' : '') + (locked ? ' · hanya admin' : '');
                            row.appendChild(input); row.appendChild(label);
                            groupsBox.appendChild(row);
                        });
                        count();
                    })
                    .catch(function () { groupsBox.innerHTML = '<div class="text-danger small">Gagal memuat grup. Coba Muat ulang.</div>'; });
            }
            qs('[data-device]').addEventListener('change', function () { picked = []; loadGroups(false); });
            qs('[data-refresh-groups]').addEventListener('click', function () {
                picked = qsa('[name="wa_groups[]"]:checked').map(function (i) { return i.value; });
                loadGroups(true);
            });

            form.addEventListener('submit', function () { qs('[data-submit]').disabled = true; });

            syncType();
            renumber();
            count();
            loadGroups(false);
        })();
    </script>
@endpush
