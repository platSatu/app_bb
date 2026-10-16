@if ($canManage)
    <a href="{{ route($routes.'.routines.create', ['grade' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-success"><i class="ri-add-line"></i> Jadwal</a>
@endif
<a href="{{ route($routes.'.routines.index', ['grade' => $item->id]) }}" class="btn btn-sm rounded-pill btn-outline-primary"><i class="ri-calendar-2-line"></i> Lihat Jadwal</a>
