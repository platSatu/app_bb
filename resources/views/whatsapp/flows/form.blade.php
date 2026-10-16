@extends('layouts.dashboard')

@section('title', $flow->exists ? 'Ubah Chatbot Flow' : 'Buat Chatbot Flow')
@section('page-section', 'WhatsApp')

@section('content')
    <div class="mb-3">
        <a href="{{ route('whatsapp.flows.index', ['branch' => $branchId]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali ke daftar flow</a>
    </div>

    <form method="POST" action="{{ $flow->exists ? route('whatsapp.flows.update', $flow) : route('whatsapp.flows.store') }}" id="wa-flow-form" class="row g-3">
        @csrf
        @if ($flow->exists) @method('PUT') @endif
        <input type="hidden" name="branch_id" value="{{ $branchId }}">
        <input type="hidden" name="nodes" value="{{ old('nodes', json_encode($flow->nodes)) }}" data-nodes-input>

        <div class="col-lg-4">
            <div class="card mb-0">
                <div class="card-body">
                    <label class="form-label">Nama flow</label>
                    <input type="text" name="name" maxlength="100" required class="form-control mb-2 @error('name') is-invalid @enderror" value="{{ old('name', $flow->name) }}" placeholder="Contoh: Menu utama">
                    <label class="form-label">Berlaku untuk</label>
                    <select name="device_id" class="form-select mb-2">
                        <option value="">Semua device</option>
                        @foreach ($devices as $device)
                            <option value="{{ $device->id }}" @selected(old('device_id', $flow->device_id) === $device->id)>{{ $device->name }}</option>
                        @endforeach
                    </select>
                    <label class="form-label">Kata kunci pembuka</label>
                    <input type="text" name="keywords" maxlength="1000" required class="form-control mb-1 @error('keywords') is-invalid @enderror" value="{{ implode(', ', (array) old('keywords', $flow->keywords ?? [])) }}" placeholder="menu, halo, info">
                    @error('keywords') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    <select name="match_type" class="form-select form-select-sm mb-2">
                        @foreach (\App\Models\WaChatbotFlow::MATCH_TYPES as $key => $label)
                            <option value="{{ $key }}" @selected(old('match_type', $flow->match_type) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <label class="form-label">Sesi berakhir bila tidak dibalas (menit)</label>
                    <input type="number" name="timeout_minutes" min="5" max="1440" required class="form-control mb-2" value="{{ old('timeout_minutes', $flow->timeout_minutes) }}">
                    <label class="form-label">Pesan saat flow selesai (opsional)</label>
                    <textarea name="end_message" rows="2" maxlength="1000" class="form-control mb-2" placeholder="Terima kasih {nama}!">{{ old('end_message', $flow->end_message) }}</textarea>
                    <label class="form-label">Pesan saat diteruskan ke admin</label>
                    <textarea name="handover_message" rows="2" maxlength="1000" class="form-control mb-1" placeholder="Baik {nama}, admin kami akan segera membalas ya 🙏">{{ old('handover_message', $flow->handover_message) }}</textarea>
                    <div class="form-text mb-2">Setelah diteruskan, bot diam 2 jam untuk chat itu.</div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="flow-active" @checked(old('is_active', $flow->is_active))>
                        <label class="form-check-label" for="flow-active">Aktif</label>
                    </div>
                    <button class="btn btn-primary w-100">Simpan Flow</button>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            @error('nodes') <div class="alert alert-danger py-2">{{ $message }}</div> @enderror
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="small text-muted">Langkah <code>start</code> dikirim pertama. Pilihan dibalas pelanggan dengan nomornya.</div>
                <button type="button" class="btn btn-sm btn-outline-primary" data-add-node><i class="ri-add-line"></i> Tambah Langkah</button>
            </div>
            <div data-nodes></div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/wa-flow.js') }}"></script>
@endpush
