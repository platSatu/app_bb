@extends('layouts.dashboard')

@section('title', $template->exists ? 'Ubah Template' : 'Buat Template')
@section('page-section', 'WhatsApp')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/wa-template.css') }}">
@endpush

@section('content')
    <div class="mb-3">
        <a href="{{ route('whatsapp.templates.index', ['branch' => $branchId]) }}" class="small"><i class="ri-arrow-left-line"></i> Kembali ke daftar template</a>
    </div>

    <form method="POST" action="{{ $template->exists ? route('whatsapp.templates.update', $template) : route('whatsapp.templates.store') }}" enctype="multipart/form-data" id="wa-template-form" class="row g-3"
        data-file-url="{{ $template->file_path ? route('whatsapp.templates.file', $template) : '' }}" data-file-kind="{{ $template->fileKind() }}" data-file-name="{{ $template->file_name }}">
        @csrf
        @if ($template->exists) @method('PUT') @endif
        <input type="hidden" name="branch_id" value="{{ $branchId }}">

        <div class="col-lg-7">
            <div class="card mb-0">
                <div class="card-body">
                    <label class="form-label">Nama template <span class="text-danger">*</span></label>
                    <div class="mb-3">
                        <input type="text" name="name" maxlength="100" required class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $template->name) }}" placeholder="Contoh: Promo Akhir Bulan">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex justify-content-between"><label class="form-label">Header <span class="text-muted">(opsional)</span></label><small class="text-muted" data-count="header">0/60</small></div>
                    <input type="text" name="header" maxlength="60" class="form-control mb-3 @error('header') is-invalid @enderror" value="{{ old('header', $template->header) }}" placeholder="Judul singkat di atas pesan" data-field="header">

                    <div class="d-flex justify-content-between"><label class="form-label">Isi pesan <span class="text-danger">*</span></label><small class="text-muted" data-count="body">0/1024</small></div>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-wrap="*" title="Tebal"><i class="ri-bold"></i> Bold</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-wrap="_" title="Miring"><i class="ri-italic"></i> Italic</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-wrap="~" title="Coret"><i class="ri-strikethrough"></i> Coret</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-wrap="```" title="Huruf mono"><i class="ri-code-line"></i> Mono</button>
                        <button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-insert="{nama}" title="Sisipkan nama kontak">{ } Nama</button>
                    </div>
                    <textarea name="body" rows="7" maxlength="1024" required class="form-control @error('body') is-invalid @enderror" placeholder="Halo {nama}, terima kasih sudah bergabung..." data-field="body">{{ old('body', $template->body) }}</textarea>
                    @error('body') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text mb-3">Pilih teks lalu klik tombol format. <code>{nama}</code> diganti nama kontak saat dikirim.</div>

                    <div class="d-flex justify-content-between"><label class="form-label">Footer <span class="text-muted">(opsional)</span></label><small class="text-muted" data-count="footer">0/60</small></div>
                    <input type="text" name="footer" maxlength="60" class="form-control mb-3 @error('footer') is-invalid @enderror" value="{{ old('footer', $template->footer) }}" placeholder="Contoh: Balas STOP untuk berhenti" data-field="footer">

                    <label class="form-label">Link <span class="text-muted">(opsional)</span></label>
                    <input type="url" name="link" maxlength="500" class="form-control @error('link') is-invalid @enderror" value="{{ old('link', $template->link) }}" placeholder="https://..." data-field="link">
                    @error('link') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="mb-3"></div>

                    <label class="form-label">Lampiran <span class="text-muted">(opsional)</span></label>
                    <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" data-field="file">
                    @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text">Gambar, video, audio, PDF, atau dokumen sampai 16 MB.</div>
                    @if ($template->file_path)
                        <div class="d-flex align-items-center gap-2 mt-2 small" data-current-file>
                            <i class="ri-attachment-2"></i>
                            <a href="{{ route('whatsapp.templates.file', $template) }}" target="_blank" rel="noopener">{{ $template->file_name }}</a>
                            <span class="text-muted">({{ number_format($template->file_size / 1024, 0, ',', '.') }} KB)</span>
                            <div class="form-check ms-auto mb-0">
                                <input class="form-check-input" type="checkbox" name="remove_file" value="1" id="remove-file" data-field="remove_file">
                                <label class="form-check-label" for="remove-file">Hapus lampiran</label>
                            </div>
                        </div>
                    @endif

                    <div class="form-check form-switch mt-3 mb-3">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="template-active" @checked(old('is_active', $template->is_active))>
                        <label class="form-check-label" for="template-active">Aktif</label>
                    </div>
                    <button class="btn btn-primary w-100"><i class="ri-save-line"></i> Simpan Template</button>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="wa-phone">
                <div class="wa-phone-top">
                    <i class="ri-arrow-left-line"></i>
                    <span class="wa-phone-avatar"><i class="ri-building-line"></i></span>
                    <div class="flex-grow-1"><div class="fw-semibold">Nama Perusahaan</div><small>online</small></div>
                </div>
                <div class="wa-phone-chat">
                    <div class="wa-bubble">
                        <div class="wa-bubble-media" data-preview="file" hidden></div>
                        <div class="fw-bold" data-preview="header" hidden></div>
                        <div class="wa-bubble-body" data-preview="body">Isi pesan akan muncul di sini...</div>
                        <div class="wa-bubble-footer" data-preview="footer" hidden></div>
                        <a class="wa-bubble-link" data-preview="link" hidden target="_blank" rel="noopener"></a>
                        <div class="wa-bubble-time">12:00 <i class="ri-check-double-line"></i></div>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script src="{{ asset('js/wa-template.js') }}"></script>
@endpush
