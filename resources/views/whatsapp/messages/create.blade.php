@extends('layouts.dashboard')

@section('title', $type === 'blast' ? 'Buat Blast' : 'Sekali Kirim')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <a href="{{ route('whatsapp.messages.index', ['branch' => $branch->id]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali ke daftar Message</a>
        @include('whatsapp._branch-filter')
    </div>

    @if ($devices->isEmpty())
        <div class="card"><div class="card-body text-center text-muted py-5">Belum ada nomor WhatsApp di branch ini. <a href="{{ route('whatsapp.devices.index', ['branch' => $branch->id]) }}">Hubungkan dulu</a>.</div></div>
    @else
        <form method="POST" action="{{ route('whatsapp.messages.store') }}" enctype="multipart/form-data" class="row g-3" id="wa-message-form">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">

            <div class="col-lg-7">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <ul class="nav nav-pills mb-3">
                            @foreach (\App\Models\WaBroadcast::TYPES as $key => $label)
                                <li class="nav-item"><a class="nav-link {{ $type === $key ? 'active' : '' }}" href="{{ route('whatsapp.messages.create', ['branch' => $branch->id, 'type' => $key]) }}">{{ $label }}</a></li>
                            @endforeach
                        </ul>

                        <div class="row g-2 mb-3">
                            <div class="col-md-7">
                                <label class="form-label">Nama pesan</label>
                                <input type="text" name="name" maxlength="150" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" placeholder="{{ $type === 'blast' ? 'Contoh: Promo Akhir Bulan' : 'Contoh: Info jadwal kelas' }}">
                                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Kirim dari</label>
                                <select name="device_id" class="form-select" required>
                                    @foreach ($devices as $device)
                                        <option value="{{ $device->id }}" @selected(old('device_id') === $device->id)>{{ $device->name }}{{ $device->status === 'connected' ? '' : ' (terputus)' }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <label class="form-label">Isi pesan</label>
                        <textarea name="body" rows="7" maxlength="4000" class="form-control @error('body') is-invalid @enderror" placeholder="Halo {nama}, ...">{{ old('body') }}</textarea>
                        @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text mb-3">Tulis <code>{nama}</code> untuk memanggil nama kontak (otomatis "Kak" bila namanya belum ada).</div>

                        <label class="form-label">Lampiran (opsional)</label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror">
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        <div class="form-text">Foto, video, audio, atau dokumen sampai 64 MB. Isi pesan dikirim sebagai keterangan.</div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3">Penerima</h6>
                        @if ($type === 'blast')
                            @error('group_ids') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                            <div class="small fw-semibold text-muted mb-1">Group WhatsApp</div>
                            @forelse ($groups as $group)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="group_ids[]" value="{{ $group->id }}" id="grp-{{ $group->id }}" @checked(in_array($group->id, (array) old('group_ids', [])))>
                                    <label class="form-check-label" for="grp-{{ $group->id }}">{{ $group->name }} <span class="text-muted small">({{ $group->customers_count }} nomor)</span></label>
                                </div>
                            @empty
                                <div class="text-muted small">Belum ada Group kontak. <a href="{{ route('whatsapp.groups.index', ['branch' => $branch->id]) }}">Buat Group</a> dulu.</div>
                            @endforelse
                            <div class="small fw-semibold text-muted mt-3 mb-1">Customers aplikasi lain</div>
                            @foreach (\App\Models\Customer::APPS as $app => $label)
                                @continue($app === 'whatsapp')
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="apps[]" value="{{ $app }}" id="app-{{ $app }}" @checked(in_array($app, (array) old('apps', []))) @disabled(! ($appCounts[$app] ?? 0))>
                                    <label class="form-check-label" for="app-{{ $app }}">Semua customer {{ $label }} <span class="text-muted small">({{ $appCounts[$app] ?? 0 }} nomor)</span></label>
                                </div>
                            @endforeach
                            <div class="form-text mt-2">Kontak yang ada di beberapa group hanya dikirimi sekali. Nomor yang membalas STOP otomatis dilewati.</div>
                        @else
                            @error('customer_ids') <div class="text-danger small mb-2">{{ $message }}</div> @enderror
                            <input type="search" class="form-control form-control-sm mb-2" placeholder="Cari kontak" data-picker-search>
                            <div class="border rounded p-2 mb-3" style="max-height: 220px; overflow-y: auto">
                                @forelse ($customers as $customer)
                                    @if ($loop->first || $customers[$loop->index - 1]->app_key !== $customer->app_key)
                                        <div class="small fw-semibold text-muted {{ $loop->first ? '' : 'mt-2' }}" data-picker-row data-text="">Customers {{ $customer->appLabel() }}</div>
                                    @endif
                                    <div class="form-check" data-picker-row data-text="{{ strtolower($customer->name.' '.$customer->phone) }}">
                                        <input class="form-check-input" type="checkbox" name="customer_ids[]" value="{{ $customer->id }}" id="c-{{ $customer->id }}" @checked(in_array($customer->id, (array) old('customer_ids', [])))>
                                        <label class="form-check-label" for="c-{{ $customer->id }}">{{ $customer->displayName() }} <span class="text-muted small">+{{ $customer->phone }}</span></label>
                                    </div>
                                @empty
                                    <div class="text-muted small">Belum ada kontak dengan nomor WhatsApp.</div>
                                @endforelse
                            </div>
                            <label class="form-label small">Atau tulis nomor (pisahkan dengan baris baru / koma)</label>
                            <textarea name="phones" rows="3" class="form-control" placeholder="081234567890&#10;6289876543210">{{ is_array(old('phones')) ? implode("\n", old('phones')) : old('phones') }}</textarea>
                        @endif
                    </div>
                </div>

                <div class="card mb-0">
                    <div class="card-body">
                        <h6 class="mb-3">Jadwal</h6>
                        <select name="schedule" class="form-select mb-2" data-schedule>
                            @foreach (\App\Models\WaBroadcast::SCHEDULES as $key => $label)
                                <option value="{{ $key }}" @selected(old('schedule', 'now') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div data-send-at>
                            <label class="form-label small">Waktu kirim (pertama)</label>
                            <input type="datetime-local" name="send_at" class="form-control @error('send_at') is-invalid @enderror" value="{{ old('send_at') }}">
                            @error('send_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="form-text">Blast dikirim bertahap dengan jeda acak, tidak dikirim pukul 21.00–07.00, dan mengikuti batas harian nomor supaya aman dari pembatasan WhatsApp.</div>
                        <button class="btn btn-primary w-100 mt-3"><i class="ri-send-plane-2-line"></i> Simpan & Kirim</button>
                    </div>
                </div>
            </div>
        </form>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('wa-message-form');
            if (!form) return;
            var schedule = form.querySelector('[data-schedule]');
            var sendAt = form.querySelector('[data-send-at]');
            var sync = function () { sendAt.classList.toggle('d-none', schedule.value === 'now'); };
            schedule.addEventListener('change', sync);
            sync();

            var search = form.querySelector('[data-picker-search]');
            if (search) {
                search.addEventListener('input', function () {
                    var q = search.value.toLowerCase();
                    form.querySelectorAll('[data-picker-row]').forEach(function (row) { row.style.display = row.dataset.text.indexOf(q) > -1 ? '' : 'none'; });
                });
            }
            form.addEventListener('submit', function () { form.querySelector('button:not([type])').disabled = true; });
        })();
    </script>
@endpush
