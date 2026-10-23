{{-- Tombol per customer: Ubah, Jadikan User, Hapus. Dipakai Customers Pages/Invoice dan Phone Book WhatsApp.
     $customer, $routes (prefix route, mis. 'pages.customers'), $hasAccount, $editData (array untuk modal ubah) --}}
<div class="d-inline-flex flex-nowrap align-items-center gap-1">
    <button type="button" class="btn btn-sm rounded-pill btn-outline-secondary" title="Ubah"
        data-customer-edit="{{ route($routes.'.update', $customer) }}" data-customer="{{ json_encode($editData) }}">
        <i class="ri-pencil-line"></i>
    </button>
    @if ($hasAccount)
        <span class="btn btn-sm rounded-pill btn-outline-success disabled text-nowrap" title="Sudah bisa login"><i class="ri-user-follow-line"></i> Punya akun</span>
    @elseif (! $customer->email)
        {{-- Belum ada email: buka form Ubah untuk mengisi email, akun langsung dibuat setelah disimpan. --}}
        <button type="button" class="btn btn-sm rounded-pill btn-outline-primary text-nowrap" title="Isi email, lalu akun dibuat"
            data-customer-edit="{{ route($routes.'.update', $customer) }}" data-customer="{{ json_encode($editData + ['make_user' => true]) }}">
            <i class="ri-user-add-line"></i> Jadikan User
        </button>
    @else
        <form method="POST" class="m-0" action="{{ route($routes.'.account', $customer) }}"
            onsubmit="return confirm({{ \Illuminate\Support\Js::from('Buatkan akun login untuk '.$customer->email.'? Link untuk membuat password dikirim ke email itu.') }})">
            @csrf
            <button class="btn btn-sm rounded-pill btn-outline-primary text-nowrap" title="Jadikan user agar bisa login"><i class="ri-user-add-line"></i> Jadikan User</button>
        </form>
    @endif
    <form method="POST" class="m-0" action="{{ route($routes.'.destroy', $customer) }}"
        onsubmit="return confirm({{ \Illuminate\Support\Js::from('Hapus '.$customer->displayName().' dari Customers? Riwayat transaksi/invoice tetap tersimpan.') }})">
        @csrf @method('DELETE')
        <button class="btn btn-sm rounded-pill btn-outline-danger" title="Hapus"><i class="ri-delete-bin-line"></i></button>
    </form>
</div>
