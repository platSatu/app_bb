<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label for="name" class="form-label">Nama Role</label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $role->name) }}" placeholder="Contoh: Finance" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="description" class="form-label">Keterangan <span class="text-muted">(opsional)</span></label>
        <input type="text" id="description" name="description" class="form-control @error('description') is-invalid @enderror"
            value="{{ old('description', $role->description) }}" placeholder="Contoh: Mengelola deposit dan transaksi">
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<h6 class="mb-2">Akses Menu</h6>
<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Menu</th>
                <th class="text-center">Tidak Ada</th>
                <th class="text-center">Lihat</th>
                <th class="text-center">Lihat + Kelola</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($menus as $key => $menu)
                @php($current = old("permissions.{$loop->index}.ability", $granted[$key] ?? ''))
                <tr>
                    <td>
                        <input type="hidden" name="permissions[{{ $loop->index }}][key]" value="{{ $key }}">
                        <i class="{{ $menu['icon'] }} me-1 text-muted"></i>
                        <span class="text-muted">{{ $menu['group'] }} ›</span> {{ $menu['label'] }}
                    </td>
                    @foreach (['' => 'Tidak ada akses', 'view' => 'Lihat', 'manage' => 'Lihat dan kelola'] as $ability => $label)
                        <td class="text-center">
                            <input type="radio" class="form-check-input" name="permissions[{{ $loop->parent->index }}][ability]"
                                value="{{ $ability }}" aria-label="{{ $menu['label'] }}: {{ $label }}" @checked($current === $ability)>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
<div class="form-text">Menu Role hanya bisa dibuka superadmin, jadi tidak ada di daftar ini.</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('superadmin.roles.index') }}" class="btn btn-light">Batal</a>
    <button type="submit" class="btn btn-primary">Simpan</button>
</div>
