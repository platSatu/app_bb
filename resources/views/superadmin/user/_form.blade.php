<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Nama</label>
        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
            value="{{ old('name', $user->name) }}" required>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
            value="{{ old('email', $user->email) }}" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="handphone" class="form-label">Nomor WhatsApp</label>
        <div class="input-group">
            <span class="input-group-text">+62</span>
            <input type="text" inputmode="numeric" id="handphone" name="handphone"
                class="form-control @error('handphone') is-invalid @enderror"
                value="{{ preg_replace('/^62/', '', (string) old('handphone', $user->handphone)) }}" placeholder="81234567890" required>
        </div>
        @error('handphone') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
    </div>

    @if (auth()->user()->isSuperadmin())
        <div class="col-md-6">
            <label for="user_type" class="form-label">Tipe</label>
            <select id="user_type" name="user_type" class="form-select @error('user_type') is-invalid @enderror" required>
                @foreach (\App\Models\User::TYPE_LABELS as $value => $label)
                    <option value="{{ $value }}" @selected(old('user_type', $user->user_type ?? \App\Models\User::TYPE_USER) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('user_type') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-6" id="role-field">
            <label for="role_id" class="form-label">Role Staff</label>
            <select id="role_id" name="role_id" class="form-select @error('role_id') is-invalid @enderror">
                <option value="">-- Pilih role --</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->id }}" @selected(old('role_id', $user->exists ? $user->roles->first()?->id : null) === $role->id)>{{ $role->name }}</option>
                @endforeach
            </select>
            @if ($roles->isEmpty())
                <div class="form-text">Belum ada role. Buat dulu di menu <a href="{{ route('superadmin.roles.create') }}">Role</a>.</div>
            @endif
            @error('role_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <script>
            (function () {
                var type = document.getElementById('user_type');
                var roleField = document.getElementById('role-field');
                var toggle = function () { roleField.classList.toggle('d-none', type.value !== @js(\App\Models\User::TYPE_STAFF)); };
                type.addEventListener('change', toggle);
                toggle();
            })();
        </script>
    @endif

    @if ($user->exists)
        <div class="col-md-6">
            <label for="status" class="form-label">Status</label>
            <select id="status" name="status" class="form-select @error('status') is-invalid @enderror" required>
                <option value="{{ \App\Models\User::STATUS_ACTIVE }}" @selected(old('status', $user->status) === \App\Models\User::STATUS_ACTIVE)>Aktif</option>
                <option value="{{ \App\Models\User::STATUS_INACTIVE }}" @selected(old('status', $user->status) === \App\Models\User::STATUS_INACTIVE)>Tidak Aktif</option>
            </select>
            <div class="form-text">User yang tidak aktif langsung keluar dan tidak bisa masuk lagi.</div>
            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    @endif

    <div class="col-md-6">
        <label for="password" class="form-label">Password</label>
        <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
            autocomplete="new-password" @required(! $user->exists)>
        @if ($user->exists)
            <div class="form-text">Kosongkan kalau password tidak diganti.</div>
        @endif
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>

    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Ulangi Password</label>
        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
            autocomplete="new-password" @required(! $user->exists)>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('superadmin.users.index') }}" class="btn btn-light">Batal</a>
    <button type="submit" class="btn btn-primary">Simpan</button>
</div>
