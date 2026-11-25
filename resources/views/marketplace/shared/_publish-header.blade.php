{{-- Kepala form upload: pesan error, percobaan terakhir yang gagal, ringkasan produk. Butuh: $product, $lastListing, $store --}}
@php($cover = $product->cover())

@if ($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

@if ($lastListing && ! $lastListing->isSubmitted())
    <div class="alert alert-warning small mb-3"><b>Percobaan terakhir gagal:</b> {{ $lastListing->error }}</div>
@endif

<div class="d-flex gap-3 mb-3">
    @if ($cover)
        <img src="{{ $cover->thumbnailUrl() }}" alt="" width="72" height="72" class="rounded border" style="object-fit: cover;">
    @endif
    <div>
        <h5 class="mb-1">{{ $product->name }}</h5>
        <div class="small text-muted">
            {{ $product->images->where('status', \App\Models\CatalogProductImage::STATUS_ACTIVE)->count() }} foto aktif (maks. 8 dikirim) ·
            nama, deskripsi & foto diambil dari produk katalog.
        </div>
    </div>
</div>
