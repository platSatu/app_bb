@extends('layouts.auth')

@php
    $isStudent = $link->type === \App\Models\SchedulePortalLink::TYPE_STUDENT;
    $branchName = $link->branch->name;
    $for = old('for', 'self');
@endphp

@section('title', $isStudent ? 'Daftar Murid' : 'Daftar Pengajar')

@section('content')
    <div class="mb-5">
        <span class="badge bg-primary-subtle text-primary mb-2"><i class="ri-store-2-line"></i> {{ $branchName }}</span>
        <h5 class="mb-2">{{ $isStudent ? 'Daftar sebagai murid' : 'Daftar sebagai pengajar' }}</h5>
        <p class="text-muted mb-0">
            {{ $isStudent ? 'Setelah disetujui admin, Anda bisa melihat jadwal les, riwayat kehadiran, dan tagihan di satu tempat.' : 'Setelah disetujui admin, Anda bisa melihat jadwal mengajar dan mengisi absen murid dari HP.' }}
        </p>
    </div>

    @foreach (['error' => 'danger', 'info' => 'info'] as $key => $color)
        @if (session($key))
            <div class="alert alert-{{ $color }}">{{ session($key) }}</div>
        @endif
    @endforeach

    @if (! $link->is_active)
        <div class="alert alert-warning d-flex gap-2">
            <i class="ri-lock-line fs-5"></i>
            <div>Pendaftaran di {{ $branchName }} sedang ditutup. Silakan hubungi admin untuk link yang baru.</div>
        </div>
    @elseif ($user?->isPlatformTeam())
        <div class="alert alert-info mb-0">Akun tim {{ config('app.name') }} tidak bisa mendaftar lewat link ini.</div>
    @else
        <form method="POST" action="{{ route('portal.join.store', $link->token) }}">
            @csrf

            @if ($user)
                <div class="d-flex align-items-center gap-3 p-3 border rounded-3 mb-4 bg-light">
                    <span class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-semibold" style="width:40px;height:40px">
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($user->name, 0, 1)) }}
                    </span>
                    <div class="small">
                        <div class="fw-semibold">{{ $user->name }}</div>
                        <div class="text-muted">{{ $user->email }}</div>
                    </div>
                </div>
            @endif

            @if ($isStudent)
                <label class="form-label fw-semibold">Siapa yang mau didaftarkan?</label>
                <div class="row g-2 mb-3">
                    @foreach (['self' => ['Saya sendiri', 'ri-user-smile-line'], 'child' => ['Anak saya', 'ri-parent-line']] as $value => [$label, $icon])
                        <div class="col-6">
                            <input type="radio" class="btn-check" name="for" id="for-{{ $value }}" value="{{ $value }}" @checked($for === $value) data-for>
                            <label class="btn btn-outline-primary w-100 py-2" for="for-{{ $value }}"><i class="{{ $icon }} d-block fs-4"></i>{{ $label }}</label>
                        </div>
                    @endforeach
                </div>
                <div class="mb-4 {{ $for === 'child' ? '' : 'd-none' }}" id="student-name-field">
                    <input type="text" class="form-control @error('student_name') is-invalid @enderror" name="student_name" value="{{ old('student_name') }}" maxlength="150" placeholder="Nama lengkap anak">
                    <div class="form-text">Punya lebih dari satu anak? Daftarkan satu per satu lewat link yang sama.</div>
                    @error('student_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
            @endif

            @unless ($user)
                <label class="form-label fw-semibold" id="account-label">{{ $isStudent && $for === 'child' ? 'Data orang tua / wali (untuk masuk)' : 'Data Anda (untuk masuk)' }}</label>
                <div class="mb-3">
                    <input type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" placeholder="Nama lengkap" required autocomplete="name">
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <input type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" placeholder="Email" required autocomplete="username">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text">+62</span>
                        <input type="text" inputmode="numeric" class="form-control @error('handphone') is-invalid @enderror" name="handphone"
                            value="{{ preg_replace('/^62/', '', (string) old('handphone')) }}" placeholder="81234567890" required maxlength="16" autocomplete="tel-national">
                    </div>
                    <div class="form-text">Nomor WhatsApp aktif, untuk info jadwal dari admin.</div>
                    @error('handphone') <div class="text-danger fs-13 mt-1">{{ $message }}</div> @enderror
                </div>
                @include('auth.partials.password-input', ['name' => 'password', 'placeholder' => 'Buat password', 'autocomplete' => 'new-password'])
                @include('auth.partials.password-input', ['name' => 'password_confirmation', 'placeholder' => 'Ulangi password', 'autocomplete' => 'new-password'])
                @include('auth.partials.turnstile')
            @endunless

            <button type="submit" class="btn btn-primary w-100">{{ $user ? 'Ajukan Pendaftaran' : 'Daftar Sekarang' }}</button>
        </form>

        @unless ($user)
            <p class="text-center text-muted fs-14 mt-5 mb-0">
                Sudah punya akun portal? <a href="{{ route('login') }}" class="link link-primary">Masuk dulu</a>, nanti kembali ke halaman ini.
            </p>
        @endunless

        @if ($isStudent)
            <script>
                document.querySelectorAll('[data-for]').forEach(function (radio) {
                    radio.addEventListener('change', function () {
                        var child = radio.value === 'child';
                        document.getElementById('student-name-field').classList.toggle('d-none', !child);
                        var label = document.getElementById('account-label');
                        if (label) label.textContent = child ? 'Data orang tua / wali (untuk masuk)' : 'Data Anda (untuk masuk)';
                    });
                });
            </script>
        @endif
    @endif
@endsection
