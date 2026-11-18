{{-- Import Excel (18 November 2026): migrasi banyak murid sekaligus. Hanya owner (superadmin memakai Tambah Murid). --}}
@if (! $isAdmin && $branches->isNotEmpty())
    <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#importStudentsModal">
        <i class="ri-file-excel-2-line me-1"></i> Import Excel
    </button>
    <div class="modal fade" id="importStudentsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" action="{{ route($routes.'.students.import') }}" enctype="multipart/form-data" class="modal-content"
                onsubmit="const b = this.querySelector('[type=submit]'); b.disabled = true; b.innerHTML = '<span class=\'spinner-border spinner-border-sm me-1\'></span> Mengimpor...';">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-file-excel-2-line text-success me-1"></i> Import Murid dari Excel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <div class="modal-body">
                    <ol class="small text-muted ps-3 mb-3">
                        <li class="mb-1">
                            Unduh template, lalu isi satu murid per baris.
                            <a href="{{ route($routes.'.students.import.template') }}" class="fw-semibold"><i class="ri-download-2-line"></i> Unduh template</a>
                        </li>
                        <li class="mb-1">Kolom: <strong>Nama</strong> (wajib), WhatsApp, Email, Orang Tua / Wali, Keterangan, Status (Aktif / Tidak Aktif, kosong = Aktif).</li>
                        <li>Murid yang WhatsApp atau emailnya sudah terdaftar dilewati (tidak ditimpa), jadi file yang sama aman diunggah ulang.</li>
                    </ol>

                    @if ($branches->count() > 1)
                        <div class="mb-3">
                            <label for="import_branch" class="form-label">Branch</label>
                            <select id="import_branch" name="branch_id" class="form-select" required>
                                <option value="">-- Pilih branch --</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id') === $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="branch_id" value="{{ $branches->first()->id }}">
                    @endif

                    <label for="import_file" class="form-label">File Excel</label>
                    <input type="file" id="import_file" name="file" class="form-control" required accept=".xlsx,.xls,.csv">
                    <div class="form-text">Format .xlsx, .xls, atau .csv · maksimal 5 MB · maksimal {{ number_format(\App\Services\Schedule\StudentImportService::MAX_ROWS, 0, ',', '.') }} murid sekali impor.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success"><i class="ri-upload-2-line me-1"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
@endif
