<div class="row g-3">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="mb-1">Informasi Akun</h5>
                <p class="text-muted mb-4">Perbarui nama dan nomor WhatsApp Anda.</p>

                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    @method('PATCH')

                    <div class="mb-3">
                        <label for="name" class="form-label">Nama</label>
                        <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $user->name) }}" required autocomplete="name">
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" id="email" class="form-control" value="{{ $user->email }}" disabled>
                        <div class="form-text">Email tidak bisa diubah. Hubungi admin kalau perlu diganti.</div>
                    </div>

                    <div class="mb-4">
                        <label for="handphone" class="form-label">Nomor WhatsApp</label>
                        <div class="input-group">
                            <span class="input-group-text">+62</span>
                            <input type="text" inputmode="numeric" id="handphone" name="handphone"
                                class="form-control @error('handphone') is-invalid @enderror"
                                value="{{ preg_replace('/^62/', '', (string) old('handphone', $user->handphone)) }}"
                                placeholder="81234567890" required autocomplete="tel-national">
                        </div>
                        @error('handphone') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Profil</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="mb-1">Ganti Password</h5>
                <p class="text-muted mb-4">Gunakan password yang kuat dan belum pernah Anda pakai di tempat lain.</p>

                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    @method('PUT')

                    @foreach ([
                        'current_password' => ['Password Saat Ini', 'current-password'],
                        'password' => ['Password Baru', 'new-password'],
                        'password_confirmation' => ['Ulangi Password Baru', 'new-password'],
                    ] as $field => [$label, $autocomplete])
                        <div class="mb-3">
                            <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                            <div class="position-relative">
                                <input type="password" id="{{ $field }}" name="{{ $field }}" required autocomplete="{{ $autocomplete }}"
                                    class="form-control {{ $errors->updatePassword->has($field) ? 'is-invalid' : '' }}">
                                <button type="button" class="btn btn-link position-absolute end-0 top-0 text-decoration-none text-muted toggle-password"
                                    data-target="{{ $field }}" aria-label="Tampilkan password">
                                    <i class="ri-eye-off-line align-middle"></i>
                                </button>
                            </div>
                            @if ($errors->updatePassword->has($field))
                                <div class="invalid-feedback d-block">{{ $errors->updatePassword->first($field) }}</div>
                            @endif
                        </div>
                    @endforeach

                    <button type="submit" class="btn btn-primary mt-1">Ganti Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
