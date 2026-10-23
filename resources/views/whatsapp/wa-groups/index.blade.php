@extends('layouts.dashboard')

@section('title', 'Group WhatsApp')
@section('page-section', 'Phone Book')

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <p class="text-muted small mb-0">Grup WhatsApp yang diikuti nomor Anda. Anggotanya bisa disalin ke Group kontak untuk blast.</p>
                <div class="d-flex gap-2 align-items-center">
                    @include('whatsapp._branch-filter')
                    @if ($devices->count() > 1)
                        <form method="GET" class="d-flex gap-2 align-items-center">
                            <input type="hidden" name="branch" value="{{ $branch->id }}">
                            <select name="device" class="form-select form-select-sm w-auto" onchange="this.form.submit()">
                                @foreach ($devices as $option)
                                    <option value="{{ $option->id }}" @selected($option->id === $device?->id)>{{ $option->name }}{{ $option->isConnected() ? '' : ' (terputus)' }}</option>
                                @endforeach
                            </select>
                        </form>
                    @endif
                    @if ($device?->isConnected())
                        <a href="{{ route('whatsapp.wa-groups.index', ['branch' => $branch->id, 'device' => $device->id, 'refresh' => 1]) }}" class="btn btn-sm btn-outline-secondary"><i class="ri-refresh-line"></i> Muat ulang</a>
                    @endif
                </div>
            </div>

            @if (! $device)
                <div class="text-center text-muted py-5">Belum ada nomor WhatsApp di branch ini. <a href="{{ route('whatsapp.devices.index', ['branch' => $branch->id]) }}">Hubungkan dulu</a>.</div>
            @elseif (! $device->isConnected())
                <div class="text-center text-muted py-5">Nomor <strong>{{ $device->name }}</strong> sedang tidak terhubung. Sambungkan dulu di <a href="{{ route('whatsapp.devices.index', ['branch' => $branch->id]) }}">Device & Inbox</a>.</div>
            @elseif (! $result['success'])
                <div class="alert alert-danger mb-0">{{ $result['message'] }}</div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Nama Grup</th><th class="text-center">Anggota</th><th>Peran</th><th class="text-end">Aksi</th></tr></thead>
                        <tbody>
                            @forelse ($result['data']['groups'] ?? [] as $group)
                                <tr>
                                    <td class="fw-semibold">{{ $group['name'] ?: '(tanpa nama)' }}</td>
                                    <td class="text-center">{{ $group['participants'] }}</td>
                                    <td>{!! $group['is_admin'] ? '<span class="badge bg-success-subtle text-success">Admin</span>' : '<span class="badge bg-light text-muted">Anggota</span>' !!}</td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-primary" data-import='@json(['jid' => $group['jid'], 'name' => $group['name']])'><i class="ri-download-2-line"></i> Salin anggota</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">Nomor ini belum ikut grup WhatsApp mana pun.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @if ($device)
        <div class="modal fade" id="wa-import-group-modal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form method="POST" action="{{ route('whatsapp.wa-groups.import') }}" class="modal-content">
                    @csrf
                    <input type="hidden" name="device_id" value="{{ $device->id }}">
                    <input type="hidden" name="jid">
                    <div class="modal-header">
                        <h5 class="modal-title">Salin anggota <span data-group-name class="text-muted"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">Anggota disimpan ke Phone Book (Customers WhatsApp) lalu dimasukkan ke group pilihan. Nomor yang disembunyikan WhatsApp akan dilewati.</p>
                        <label class="form-label">Masukkan ke group</label>
                        <select name="group_id" class="form-select mb-2">
                            <option value="">Buat group baru...</option>
                            @foreach ($contactGroups as $option)
                                <option value="{{ $option->id }}">{{ $option->name }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="new_group" maxlength="100" class="form-control" placeholder="Nama group baru">
                        @error('group_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        @error('new_group') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                        <button class="btn btn-primary">Salin</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            var modalEl = document.getElementById('wa-import-group-modal');
            if (!modalEl) return;
            var form = modalEl.querySelector('form');
            var groupSelect = form.elements.namedItem('group_id');
            var newGroup = form.elements.namedItem('new_group');
            var sync = function () { newGroup.disabled = groupSelect.value !== ''; newGroup.classList.toggle('d-none', newGroup.disabled); };
            groupSelect.addEventListener('change', sync);
            document.querySelectorAll('[data-import]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var data = JSON.parse(btn.dataset.import);
                    form.elements.namedItem('jid').value = data.jid;
                    modalEl.querySelector('[data-group-name]').textContent = data.name || '';
                    newGroup.value = data.name || '';
                    sync();
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                });
            });
        })();
    </script>
@endpush
