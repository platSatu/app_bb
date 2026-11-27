@extends('layouts.dashboard')

@section('title', 'Ganti Hari')
@section('page-section', 'Schedules')

@use('App\Models\ScheduleRescheduleRequest')
@use('App\Support\ScheduleBotText')

@php($isPending = $status === ScheduleRescheduleRequest::STATUS_PENDING)

@section('content')
    @include('schedule._tabs')

    <div class="alert alert-light border d-flex gap-3 align-items-start mb-3">
        <i class="ri-chat-smile-2-line fs-3 text-success"></i>
        <div class="small">
            <div class="fw-semibold text-body mb-1">Pengajuan ganti hari dari orang tua lewat WhatsApp.</div>
            Orang tua memilih jadwal pengajar yang masih kosong, lalu Anda menyetujui atau menolaknya di sini atau langsung dari WhatsApp.
            Kelas privat langsung dipindah; kelas grup dibuatkan sesi pengganti otomatis setelah sesi lamanya ditutup.
            Orang tua selalu dikabari hasilnya.
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <ul class="nav nav-pills gap-2 mb-3">
                @foreach (ScheduleRescheduleRequest::STATUS_LABELS as $value => $label)
                    @continue(! $loop->first && ($counts[$value] ?? 0) === 0 && $status !== $value)
                    <li class="nav-item">
                        <a href="{{ request()->fullUrlWithQuery(['status' => $value, 'page' => null]) }}" class="nav-link py-1 px-3 rounded-pill {{ $status === $value ? 'active' : 'bg-light text-body' }}">
                            {{ $label }} <span class="ms-1 opacity-75">{{ $counts[$value] ?? 0 }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Murid</th>
                            <th>Jadwal Lama</th>
                            <th>Minta Pindah Ke</th>
                            <th>Alasan</th>
                            @if ($showBranch) <th>Branch</th> @endif
                            <th class="text-end" style="min-width: {{ $isPending ? '260px' : '0' }}">{{ $isPending ? 'Tindakan' : 'Keterangan' }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->student?->name }}</div>
                                    @if ($item->student?->guardian_name)
                                        <div class="text-muted small">Orang tua: {{ $item->student->guardian_name }}</div>
                                    @endif
                                    <div class="small"><span class="badge {{ $item->mode === ScheduleRescheduleRequest::MODE_MOVE ? 'bg-primary-subtle text-primary' : 'bg-info-subtle text-info' }}">{{ $item->mode === ScheduleRescheduleRequest::MODE_MOVE ? 'Privat' : 'Grup' }}</span></div>
                                </td>
                                <td class="small">
                                    <div>{{ $item->session?->grade?->label() }}</div>
                                    @if ($item->session)
                                        <div class="text-muted text-nowrap">{{ ScheduleBotText::when($item->session) }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    <div class="fw-semibold text-nowrap">{{ $item->slotLabel() }}</div>
                                    <div class="text-muted">{{ $item->slotTeacher?->name }} · Ruang {{ $item->slotRoom?->name ?? '-' }}</div>
                                </td>
                                <td class="small" style="max-width: 220px">{{ $item->reason ?: '-' }}</td>
                                @if ($showBranch) <td class="small">{{ $item->branch?->name }}</td> @endif
                                <td class="text-end">
                                    @if ($isPending)
                                        <div class="d-flex justify-content-end gap-2">
                                            <form method="POST" action="{{ route('schedules.reschedules.approve', $item) }}"
                                                onsubmit="return confirm('Setujui ganti hari {{ addslashes((string) $item->student?->name) }} ke {{ addslashes($item->slotLabel()) }}? Orang tua akan dikabari lewat WhatsApp.')">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success text-nowrap"><i class="ri-check-line"></i> Setujui</button>
                                            </form>
                                            <form method="POST" action="{{ route('schedules.reschedules.reject', $item) }}"
                                                onsubmit="var r = prompt('Alasan ditolak (akan disampaikan ke orang tua):'); if (r === null || r.trim().length < 3) return false; this.reason.value = r;">
                                                @csrf
                                                <input type="hidden" name="reason">
                                                <button type="submit" class="btn btn-sm btn-outline-danger text-nowrap"><i class="ri-close-line"></i> Tolak</button>
                                            </form>
                                        </div>
                                        <div class="small text-muted mt-1">Masuk {{ $item->created_at->translatedFormat('d M H:i') }}</div>
                                    @else
                                        <span class="badge {{ $item->statusBadge() }}">{{ $item->statusLabel() }}</span>
                                        @if ($item->reviewed_at)
                                            <div class="small text-muted mt-1">
                                                {{ $item->reviewer?->name ?? 'Admin' }} · {{ $item->reviewed_via === 'whatsapp' ? 'lewat WhatsApp' : 'lewat website' }} · {{ $item->reviewed_at->translatedFormat('d M H:i') }}
                                            </div>
                                        @endif
                                        @if ($item->reject_reason)
                                            <div class="small text-muted">Alasan: {{ $item->reject_reason }}</div>
                                        @endif
                                        @if ($item->failure_reason)
                                            <div class="small text-danger">{{ $item->failure_reason }}</div>
                                        @endif
                                        @if ($item->makeupSession)
                                            <a href="{{ route('schedules.sessions.show', $item->makeupSession) }}" class="small">Lihat sesi pengganti</a>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 5 + ($showBranch ? 1 : 0) }}" class="text-center text-muted py-5">
                                    <i class="ri-inbox-line fs-2 d-block mb-2"></i>
                                    {{ $isPending ? 'Belum ada pengajuan yang menunggu. Pengajuan muncul di sini begitu orang tua membalas pengingat les.' : 'Belum ada data.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $items->links() }}</div>
        </div>
    </div>
@endsection
