@if ($session->status === 'scheduled' && $session->start_at->isPast())
    <a href="{{ route('portal.absen.show', $session) }}" class="btn btn-sm btn-primary rounded-pill text-nowrap"><i class="ri-edit-2-line"></i> Isi Absen</a>
@elseif ($session->status !== 'cancelled')
    <a href="{{ route('portal.absen.show', $session) }}" class="btn btn-sm btn-light rounded-pill text-nowrap">Lihat</a>
@endif
