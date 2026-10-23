{{-- Impor Excel/CSV. $action, $template (URL contoh), $branches (branch tujuan), $groups (opsional, Group WhatsApp) --}}
<div class="modal fade" id="customer-import-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title">Impor Customers dari Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ol class="small text-muted ps-3">
                    <li><a href="{{ $template }}"><i class="ri-download-2-line"></i> Unduh file contoh</a>, isi kolom <b>nama</b>, <b>whatsapp</b>, <b>email</b>.</li>
                    <li>Simpan sebagai .xlsx (CSV juga bisa), lalu pilih di bawah.</li>
                </ol>
                <p class="small text-muted">Maksimal {{ number_format(\App\Services\Contact\ContactBookService::MAX_ROWS) }} baris, file 5 MB. Nomor/email yang sudah ada tidak dibuat dua kali.</p>
                @include('contact.customer._branch-field', ['branches' => $branches])
                <input type="file" name="file" accept=".xlsx,.xls,.csv" required class="form-control mb-3">
                @if (! empty($groups) && $groups->isNotEmpty())
                    <label class="form-label">Masukkan ke group (opsional)</label>
                    <select name="group_id" class="form-select">
                        <option value="">Tanpa group</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button class="btn btn-primary" onclick="this.disabled = true; this.form.submit();">Impor</button>
            </div>
        </form>
    </div>
</div>
