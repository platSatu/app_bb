<span class="fw-semibold">Rp {{ number_format($product->finalPrice(), 0, ',', '.') }}</span>
@if ($product->isDiscounted())
    <small class="text-muted text-decoration-line-through ms-1">Rp {{ number_format($product->price, 0, ',', '.') }}</small>
@endif
