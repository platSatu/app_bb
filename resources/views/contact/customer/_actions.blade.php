{{-- Tombol per customer: Ubah, Jadikan User, Hapus. Dipakai Customers Pages/Invoice dan Phone Book WhatsApp.
     $customer, $routes (prefix route, mis. 'pages.customers'), $hasAccount, $editData (array untuk modal ubah) --}}
<div class="d-inline-flex gap-1 flex-wrap justify-content-end">
    <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" title="Ubah"
        data-customer-edit="{{ route($routes.'.update', $customer) }}" data-customer="{{ json_encode($editData) }}">
        <i class="ri-pencil-line"></i>
    </button>
    @if ($hasAccount)
        <span class="btn btn-sm rounded-pill btn-success-subtle text-success disabled" title="Sudah bisa login"><i class="ri-user-follow-line"></i> Punya akun</span>
    @elseif (! $customer->email)
        <span class="d-inline-block" tabindex="0" title="Isi email dulu lewat tombol Ubah">
            <button type="button" class="btn btn-sm rounded-pill btn-outline-primary" disabled><i class="ri-user-add-line"></i> Jadikan User</button>
        </span>
    @else
        <form method="POST" action="{{ route($routes.'.account', $customer) }}"
            onsubmit="return confirm({{ \Illuminate\Support\Js::from('Buatkan akun login untuk '.$customer->email.'? Link untuk membuat password dikirim ke email itu.') }})">
            @csrf
            <button class="btn btn-sm rounded-pill btn-outline-primary" title="Jadikan user agar bisa login"><i class="ri-user-add-line"></i> Jadikan User</button>
        </form>
    @endif
    <form method="POST" action="{{ route($routes.'.destroy', $customer) }}"
        onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus '.$customer->displayName().' dari Customers? Riwayat transaksi/invoice tetap tersimpan.') }})">
        @csrf @method('DELETE')
        <button class="btn btn-sm rounded-pill btn-outline-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
    </form>
</div>
