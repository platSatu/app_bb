@if ($product->isSoldOut())
    <button type="button" class="btn btn-sm btn-secondary w-100" disabled>Habis</button>
@else
    <button type="button" class="btn btn-sm btn-primary w-100" data-add-to-cart
        data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-price="{{ $product->finalPrice() }}"
        data-thumb="{{ $product->cover()?->thumbnailUrl() }}" data-stock="{{ $product->stock ?? '' }}">
        <i class="ri-shopping-cart-2-line me-1"></i>Tambah
    </button>
@endif
