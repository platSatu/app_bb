<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Nama Branch</label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $branch->name) }}" placeholder="Contoh: Cabang Bandung" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
        @if ($branch->slug)
            <div class="form-text">Alamat singkat: <code>{{ $branch->slug }}</code></div>
        @endif
    </div>

    <div class="col-md-6">
        <label for="city" class="form-label">Kota</label>
        <input type="text" id="city" name="city" class="form-control @error('city') is-invalid @enderror"
            value="{{ old('city', $branch->city) }}">
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="phone" class="form-label">Telepon</label>
        <input type="text" id="phone" name="phone" class="form-control @error('phone') is-invalid @enderror"
            value="{{ old('phone', $branch->phone) }}" placeholder="022-1234567">
        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="handphone" class="form-label">WhatsApp</label>
        <div class="input-group">
            <span class="input-group-text">+62</span>
            <input type="text" inputmode="numeric" id="handphone" name="handphone"
                class="form-control @error('handphone') is-invalid @enderror"
                value="{{ preg_replace('/^62/', '', (string) old('handphone', $branch->handphone)) }}" placeholder="81234567890">
        </div>
        @error('handphone') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="address" class="form-label">Alamat</label>
        <textarea id="address" name="address" rows="2" class="form-control @error('address') is-invalid @enderror">{{ old('address', $branch->address) }}</textarea>
        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-12">
        <label for="description" class="form-label">Deskripsi</label>
        <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror">{{ old('description', $branch->description) }}</textarea>
        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="status" class="form-label">Status</label>
        <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
            <option value="{{ \App\Models\UserBranch::STATUS_ACTIVE }}" @selected(old('status', $branch->status) === \App\Models\UserBranch::STATUS_ACTIVE)>Aktif</option>
            <option value="{{ \App\Models\UserBranch::STATUS_INACTIVE }}" @selected(old('status', $branch->status) === \App\Models\UserBranch::STATUS_INACTIVE)>Tidak Aktif</option>
        </select>
        @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>
