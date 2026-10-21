@extends('layouts.portal')

@section('title', 'Profil')

@section('content')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card portal-card mb-0">
                <div class="card-body">
                    <h6 class="mb-3">Data Diri</h6>
                    <form method="POST" action="{{ route('portal.profile.update') }}">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label" for="name">Nama lengkap</label>
                            <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" maxlength="150" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control bg-light" value="{{ $user->email }}" disabled>
                            <div class="form-text">Email dipakai untuk masuk dan tidak bisa diubah di sini.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="handphone">Nomor WhatsApp</label>
                            <div class="input-group">
                                <span class="input-group-text">+62</span>
                                <input type="text" inputmode="numeric" id="handphone" name="handphone" class="form-control @error('handphone') is-invalid @enderror"
                                    value="{{ preg_replace('/^62/', '', (string) old('handphone', $user->handphone)) }}" maxlength="16" required>
                            </div>
                            @error('handphone') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
                        </div>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card portal-card mb-3">
                <div class="card-body">
                    <h6 class="mb-3">Terhubung Dengan</h6>
                    @forelse ([...$students->map(fn ($s) => [$s, 'Murid', 'ri-graduation-cap-line']), ...$teachers->map(fn ($t) => [$t, 'Pengajar', 'ri-user-star-line'])] as [$member, $role, $icon])
                        <div class="d-flex align-items-center gap-3 py-2 {{ $loop->last ? '' : 'border-bottom' }}">
                            <i class="{{ $icon }} fs-4 text-primary"></i>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $member->name }}</div>
                                <div class="small text-muted">{{ $role }} · {{ $member->branch?->name }}</div>
                            </div>
                            @unless ($member->isActive()) <span class="badge bg-secondary-subtle text-secondary">Tidak aktif</span> @endunless
                        </div>
                    @empty
                        <p class="text-muted small mb-0">Belum terhubung. Buka link pendaftaran dari admin tempat les.</p>
                    @endforelse
                </div>
            </div>

            <div class="card portal-card mb-0">
                <div class="card-body">
                    <h6 class="mb-3">Ganti Password</h6>
                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf
                        @method('PUT')
                        @foreach (['current_password' => 'Password sekarang', 'password' => 'Password baru', 'password_confirmation' => 'Ulangi password baru'] as $field => $label)
                            <div class="mb-3">
                                <input type="password" name="{{ $field }}" class="form-control {{ $errors->updatePassword->has($field) ? 'is-invalid' : '' }}" placeholder="{{ $label }}" autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}" required>
                                @foreach ($errors->updatePassword->get($field) as $message) <div class="invalid-feedback">{{ $message }}</div> @endforeach
                            </div>
                        @endforeach
                        <button type="submit" class="btn btn-outline-primary">Ganti Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
