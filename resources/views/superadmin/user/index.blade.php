@extends('layouts.dashboard')

@section('title', 'Data User')
@section('page-section', 'Superadmin')

@section('content')
    @include('superadmin.user._tabs', ['active' => 'superadmin.users'])

    @error('password')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="row g-3 mb-3">
        @foreach ([
            ['Total User', $stats->total, 'ri-group-line', 'primary'],
            ['Aktif', $stats->active, 'ri-user-follow-line', 'success'],
            ['Tidak Aktif', $stats->inactive, 'ri-user-unfollow-line', 'danger'],
            ['Staff', $stats->staff, 'ri-shield-star-line', 'warning'],
        ] as [$label, $value, $icon, $color])
            <div class="col-6 col-xl-3">
                <div class="card border-0 shadow-sm mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="avatar-item avatar-lg rounded-circle bg-{{ $color }}-subtle text-{{ $color }} flex-shrink-0">
                            <i class="{{ $icon }} fs-4"></i>
                        </div>
                        <div>
                            <div class="text-muted small">{{ $label }}</div>
                            <h4 class="mb-0">{{ number_format((int) $value, 0, ',', '.') }}</h4>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <form method="GET" class="d-flex flex-wrap gap-2">
                    <select name="type" class="form-select" style="width: auto;" onchange="this.form.submit()">
                        <option value="">Semua tipe</option>
                        @foreach (\App\Models\User::TYPE_LABELS as $value => $label)
                            <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="input-group" style="max-width: 320px;">
                        <input type="text" name="search" class="form-control" placeholder="Cari nama, email, atau nomor HP" value="{{ request('search') }}">
                        <button type="submit" class="btn btn-outline-secondary" aria-label="Cari"><i class="ri-search-line"></i></button>
                    </div>
                </form>
                @can('create', \App\Models\User::class)
                    <a href="{{ route('superadmin.users.create') }}" class="btn btn-primary">
                        <i class="ri-add-line"></i> Tambah User
                    </a>
                @endcan
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>WhatsApp</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($users as $user)
                            <tr>
                                <td class="fw-semibold">{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->handphone ? '+'.$user->handphone : '-' }}</td>
                                <td>
                                    <span class="badge {{ $user->isPlatformTeam() ? 'bg-warning-subtle text-warning' : 'bg-secondary-subtle text-secondary' }}">
                                        {{ $user->isStaff() ? 'Staff' : (\App\Models\User::TYPE_LABELS[$user->user_type] ?? $user->user_type) }}
                                    </span>
                                    @if ($user->isStaff())
                                        <div class="text-muted fs-12 mt-1">{{ $user->roles->pluck('name')->join(', ') ?: 'Belum punya role' }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $user->isActive() ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }}">
                                        {{ $user->isActive() ? 'Aktif' : 'Tidak Aktif' }}
                                    </span>
                                </td>
                                <td class="text-end text-nowrap">
                                    @can('update', $user)
                                        <a href="{{ route('superadmin.users.edit', $user) }}" class="btn btn-sm rounded-pill btn-outline-secondary">
                                            <i class="ri-edit-line"></i> Edit
                                        </a>
                                    @endcan
                                    @can('delete', $user)
                                        <button type="button" class="btn btn-sm rounded-pill btn-outline-danger" data-bs-toggle="modal" data-bs-target="#user-delete-modal"
                                            data-action="{{ route('superadmin.users.destroy', $user) }}" data-name="{{ $user->name }}" data-email="{{ $user->email }}">
                                            <i class="ri-delete-bin-line"></i> Hapus
                                        </button>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    {{ request('search') ? 'Tidak ada user yang cocok dengan pencarian ini.' : 'Belum ada user.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $users->links() }}</div>
        </div>
    </div>

    {{-- Hapus permanen (reset total): ketik ulang email user + password login Anda. --}}
    <div class="modal fade" id="user-delete-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form method="POST" class="modal-content" autocomplete="off" data-user-delete-form onsubmit="this.querySelector('[type=submit]').disabled = true">
                @csrf
                @method('DELETE')
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="ri-error-warning-line me-1"></i>Hapus Permanen <span data-user-name></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger small">
                        User ini beserta <b>SEMUA</b> datanya (perusahaan, branch, pages, tiket, invoice, WhatsApp, jadwal, customer, saldo &amp; riwayat) akan dihapus dan <b>tidak bisa dikembalikan</b>.
                    </div>
                    <label class="form-label">Ketik email user: <code data-user-email></code></label>
                    <input type="text" name="confirm_email" class="form-control mb-3" required autocomplete="off">
                    <label class="form-label">Password login Anda</label>
                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="current-password">
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger">Hapus Permanen</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.getElementById('user-delete-modal').addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget, form = this.querySelector('[data-user-delete-form]');
            form.action = button.dataset.action;
            form.reset();
            form.querySelector('[type=submit]').disabled = false;
            this.querySelector('[data-user-name]').textContent = button.dataset.name;
            this.querySelector('[data-user-email]').textContent = button.dataset.email;
        });
    </script>
@endpush
