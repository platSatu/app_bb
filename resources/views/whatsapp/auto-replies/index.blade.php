@extends('layouts.dashboard')

@section('title', 'Auto Reply')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card mb-0">
                <div class="card-body">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <p class="text-muted small mb-0">Balasan otomatis bila pesan cocok kata kunci. Dicek setelah Chatbot Flow, sebelum AI Bot. Tidak membalas chat yang sedang dipegang admin.</p>
                        @include('whatsapp._branch-filter')
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Nama</th><th>Kata kunci</th><th>Device</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
                            <tbody>
                                @forelse ($rules as $rule)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $rule->name }}</div>
                                            <div class="small text-muted text-truncate" style="max-width: 260px">{{ $rule->body }}</div>
                                        </td>
                                        <td class="small">
                                            <div class="text-muted">{{ \App\Models\WaAutoReply::MATCH_TYPES[$rule->match_type] }}</div>
                                            {{ implode(', ', $rule->keywords ?? []) }}
                                        </td>
                                        <td class="small">{{ $rule->device?->name ?? 'Semua device' }}</td>
                                        <td><span class="badge {{ $rule->is_active ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary' }}">{{ $rule->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                        <td class="text-end text-nowrap">
                                            <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" data-edit="{{ route('whatsapp.auto-replies.update', $rule) }}"
                                                data-values='@json(['name' => $rule->name, 'device_id' => $rule->device_id, 'match_type' => $rule->match_type, 'keywords' => implode(', ', $rule->keywords ?? []), 'body' => $rule->body, 'cooldown_minutes' => $rule->cooldown_minutes, 'is_active' => $rule->is_active])'><i class="ri-pencil-line"></i> Ubah</button>
                                            <form method="POST" action="{{ route('whatsapp.auto-replies.destroy', $rule) }}" class="d-inline" onsubmit="return confirm('Hapus auto reply ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm rounded-pill btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada auto reply.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-0">
                <div class="card-body">
                    <h6 class="mb-3" data-form-title>Buat Auto Reply</h6>
                    <form method="POST" action="{{ route('whatsapp.auto-replies.store') }}" data-form data-store="{{ route('whatsapp.auto-replies.store') }}">
                        @csrf
                        <input type="hidden" name="_method" value="POST">
                        <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                        <label class="form-label">Nama</label>
                        <input type="text" name="name" maxlength="100" required class="form-control mb-2" value="{{ old('name') }}" placeholder="Contoh: Info harga">
                        <label class="form-label">Berlaku untuk</label>
                        <select name="device_id" class="form-select mb-2">
                            <option value="">Semua device</option>
                            @foreach ($devices as $device)
                                <option value="{{ $device->id }}" @selected(old('device_id') === $device->id)>{{ $device->name }}</option>
                            @endforeach
                        </select>
                        <label class="form-label">Cocok bila pesan</label>
                        <select name="match_type" class="form-select mb-2">
                            @foreach (\App\Models\WaAutoReply::MATCH_TYPES as $key => $label)
                                <option value="{{ $key }}" @selected(old('match_type', 'contains') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <label class="form-label">Kata kunci</label>
                        <input type="text" name="keywords" maxlength="2000" class="form-control mb-1 @error('keywords') is-invalid @enderror" value="{{ is_array(old('keywords')) ? implode(', ', old('keywords')) : old('keywords') }}" placeholder="harga, price list, berapa">
                        @error('keywords') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                        <div class="form-text mb-2">Pisahkan dengan koma. Huruf besar/kecil tidak dibedakan.</div>
                        <label class="form-label">Balasan</label>
                        <textarea name="body" rows="5" maxlength="4000" required class="form-control mb-1" placeholder="Halo {nama}, ...">{{ old('body') }}</textarea>
                        <div class="form-text mb-2"><code>{nama}</code> diganti nama kontak.</div>
                        <label class="form-label">Jeda ulang (menit)</label>
                        <input type="number" name="cooldown_minutes" min="1" max="10080" required class="form-control mb-1" value="{{ old('cooldown_minutes', 60) }}">
                        <div class="form-text mb-2">Kontak yang sama tidak dibalas aturan ini lagi selama jeda ini.</div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="ar-active" @checked(old('is_active', true))>
                            <label class="form-check-label" for="ar-active">Aktif</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary flex-grow-1">Simpan</button>
                            <button type="button" class="btn btn-light d-none" data-cancel>Batal</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @include('whatsapp._edit-form-script', ['createTitle' => 'Buat Auto Reply', 'editTitle' => 'Ubah Auto Reply'])
@endpush
