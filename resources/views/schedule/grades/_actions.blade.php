@if ($canManage)
    {{-- Pengajar & jam buka diatur di form Edit grade; tombol ini langsung turun ke bagiannya.
         Kuning kalau belum ada pengajar: grade itu belum siap dipakai. --}}
    <a href="{{ route($routes.'.grades.edit', $item->id) }}#pengajar" class="btn btn-sm rounded-pill {{ $item->teachers->isEmpty() ? 'btn-warning' : 'btn-outline-secondary' }}"><i class="ri-user-star-line"></i> Pengajar & Jam</a>
    {{-- Paket memakai pola tanggal, bukan jam buka, jadi jadwalnya tetap lewat form lama. --}}
    @if ($item->isPackage())
        <a href="{{ route($routes.'.routines.create', ['grade' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-success"><i class="ri-add-line"></i> Jadwal</a>
    @else
        <a href="{{ route($routes.'.routines.fill', ['grade' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-info"><i class="ri-user-add-line"></i> Isi Murid</a>
    @endif
@endif
<a href="{{ route($routes.'.routines.index', ['grade' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-primary"><i class="ri-calendar-2-line"></i> Lihat Jadwal</a>
