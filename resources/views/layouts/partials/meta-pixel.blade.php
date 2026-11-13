{{-- Meta Pixel. Halaman auth (login/daftar/verifikasi): $pageView = true. Dashboard: hanya dimuat sekali saat ada event
     tertunda (mis. CompleteRegistration setelah daftar), jadi isi dashboard pelanggan tidak ikut terkirim ke Meta. --}}
@php
    $metaPixelId = preg_match('/^\d{5,20}$/', (string) config('services.meta.pixel_id')) ? config('services.meta.pixel_id') : null;
    $metaEvent = $metaPixelId ? session()->pull('meta_pixel_event') : null;
@endphp
@if ($metaPixelId && (($pageView ?? false) || $metaEvent))
    <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @json($metaPixelId));
        @if ($pageView ?? false) fbq('track', 'PageView'); @endif
        @if ($metaEvent) fbq('track', @json($metaEvent)); @endif
    </script>
@endif
