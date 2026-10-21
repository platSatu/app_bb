{{-- Pill tab Semua · Anak/Branch. Disembunyikan kalau hanya satu, jadi dropdown kalau lebih dari 4. --}}
@if (count($tabs) > 1)
    @php($url = fn ($id) => request()->fullUrlWithQuery([$param => $id, 'page' => null]))
    @if (count($tabs) > 4)
        <select class="form-select mb-3" style="max-width: 320px" onchange="window.location = this.value" aria-label="Pilih tampilan">
            <option value="{{ $url(null) }}">Semua</option>
            @foreach ($tabs as $id => $label)
                <option value="{{ $url($id) }}" @selected($active === $id)>{{ $label }}</option>
            @endforeach
        </select>
    @else
        <div class="pill-tabs d-flex flex-wrap gap-2 mb-3">
            <a href="{{ $url(null) }}" class="btn btn-sm {{ $active ? 'btn-light' : 'btn-primary' }}">Semua</a>
            @foreach ($tabs as $id => $label)
                <a href="{{ $url($id) }}" class="btn btn-sm {{ $active === $id ? 'btn-primary' : 'btn-light' }}">{{ $label }}</a>
            @endforeach
        </div>
    @endif
@endif
