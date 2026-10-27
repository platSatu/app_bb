/*
 * Kirim otomatis tanpa tekan Enter (halaman scan registrasi ulang, kiosk, absensi).
 * - Alat scan "mengetik" sangat cepat (jarak antar karakter < 50 ms): dikirim begitu alat berhenti mengetik (120 ms).
 * - Petugas mengetik kode booking 6 angka dengan tangan: dikirim setelah berhenti mengetik 0,7 detik.
 * - Alat yang tetap mengirim Enter: dipakai Enter-nya, timer dibatalkan supaya tidak terkirim dua kali.
 * Butuh form#scan-form dan input#scan-input; pengiriman tetap lewat handler submit milik halaman.
 */
(function () {
    var form = document.getElementById('scan-form');
    var input = document.getElementById('scan-input');
    if (!form || !input) return;

    var timer = null, last = 0, burst = 0;
    var submit = function () {
        if (!input.value.trim() || input.disabled) return;
        if (form.requestSubmit) form.requestSubmit();
        else form.dispatchEvent(new Event('submit', { cancelable: true }));
    };

    input.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') { clearTimeout(timer); burst = 0; }
    });

    input.addEventListener('input', function () {
        var now = Date.now();
        burst = now - last < 50 ? burst + 1 : 0;
        last = now;
        clearTimeout(timer);

        var value = input.value.trim();
        var delay = burst >= 4 ? 120 : (/^\d{6}$/.test(value) ? 700 : 0);
        if (delay) timer = setTimeout(submit, delay);
    });
})();
