@if ($canManage)
    {{-- Langsung ke bagian pengajar di form Edit: menugaskan pengajar jauh lebih sering dilakukan
         daripada mengubah harga, jadi diberi pintu sendiri dari tabel. --}}
    <a href="{{ route($routes.'.grades.edit', $item->id) }}#pengajar" class="btn btn-sm rounded-pill {{ $item->teachers->isEmpty() ? 'btn-warning' : 'btn-outline-secondary' }}"><i class="ri-user-star-line"></i> Pengajar</a>
    {{-- Bulanan lewat Isi Jam (sekali isi, beberapa slot); paket tetap lewat form biasa karena polanya per tanggal. --}}
    @if ($item->isPackage())
        <a href="{{ route($routes.'.routines.create', ['grade' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-success"><i class="ri-add-line"></i> Jadwal</a>
    @else
        <a href="{{ route($routes.'.routines.slots', ['grade' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-success"><i class="ri-time-line"></i> Isi Jam</a>
        <a href="{{ route($routes.'.routines.fill', ['grade' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-info"><i class="ri-user-add-line"></i> Isi Murid</a>
    @endif
@endif
<a href="{{ route($routes.'.routines.index', ['grade' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-primary"><i class="ri-calendar-2-line"></i> Lihat Jadwal</a>
