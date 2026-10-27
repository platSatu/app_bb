{{-- Kotak ringkasan. $cards = [[label, nilai, ikon, warna, keterangan], ...] --}}
<div class="row g-3 mb-3">
    @foreach ($cards as [$label, $value, $icon, $color, $note])
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100 mb-0">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between mb-2">
                        <span class="text-muted">{{ $label }}</span>
                        <span class="rounded-circle bg-{{ $color }}-subtle text-{{ $color }} d-flex align-items-center justify-content-center" style="width:36px;height:36px"><i class="{{ $icon }} fs-5"></i></span>
                    </div>
                    <h4 class="mb-1">{{ $value }}</h4>
                    <div class="small text-muted">{{ $note }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>
