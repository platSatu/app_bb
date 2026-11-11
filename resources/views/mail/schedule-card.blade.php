<x-mail::message>
# Kartu absen {{ $student->name }}

Halo{{ $student->guardian_name ? ' '.$student->guardian_name : '' }},

mulai sekarang absen les di **{{ $student->branch?->name }}** bisa pakai QR. Cukup tunjukkan QR kartu absen ke pengajar saat les dimulai, kehadiran {{ $student->name }} langsung tercatat.

Gambar QR sudah kami lampirkan di email ini. Kartu juga bisa dibuka kapan saja lewat tombol di bawah.

<x-mail::button :url="$student->cardUrl()">
Buka Kartu Absen
</x-mail::button>

Simpan gambarnya di HP atau cetak, supaya mudah ditunjukkan. Kalau QR tidak terbaca, pengajar tetap bisa mengisi absen manual.

Salam,<br>
{{ $student->branch?->name }}
</x-mail::message>
