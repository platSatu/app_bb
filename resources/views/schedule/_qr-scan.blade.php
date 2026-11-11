{{--
    Absen pakai QR, dua cara:
    - $scanUrl diisi (halaman Scan Absen pengajar): setiap scan langsung dikirim & dicek server. QR tempat = absen pengajar
      (halaman dimuat ulang), kartu murid = absen murid sekali per sesi. Penghitung [data-scan-count="<id sesi>"] bertambah.
    - tanpa $scanUrl (detail sesi admin): pilihan Hadir murid langsung tercentang, tersimpan setelah tombol simpan ditekan.
      Butuh input radio Hadir dengan data-qr (isi QR murid) & data-name, dan baris pembungkus data-qr-row.
--}}
<div class="border rounded-3 p-3 mb-3" id="qr-scan" @isset($scanUrl) data-scan-url="{{ $scanUrl }}" @endisset>
    <div class="d-flex flex-wrap align-items-center gap-3">
        <div class="flex-grow-1">
            <div class="fw-semibold"><i class="ri-qr-scan-2-line text-primary me-1"></i> Absen pakai QR</div>
            <div class="small text-muted">{{ isset($scanUrl) ? 'Buka kamera sekali: scan QR tempat les untuk absen Anda, lalu kartu murid satu per satu.' : 'Scan kartu absen murid, status Hadir terisi sendiri. Bisa juga pilih manual di bawah.' }}</div>
        </div>
        <button type="button" class="btn btn-outline-primary" data-qr-toggle><i class="ri-camera-line me-1"></i><span>Buka kamera</span></button>
    </div>
    <div class="mt-3 d-none" data-qr-panel>
        <div id="qr-reader" class="rounded-3 overflow-hidden mx-auto" style="max-width: 320px"></div>
        <div class="small text-center text-muted mt-2" data-qr-message role="status" aria-live="polite"></div>
    </div>
</div>

@push('scripts')
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" integrity="sha384-c9d8RFSL+u3exBOJ4Yp3HUJXS4znl9f+z66d1y54ig+ea249SpqR+w1wyvXz/lk+" crossorigin="anonymous"></script>
    <script>
        (function () {
            var box = document.getElementById('qr-scan');
            if (!box) return;
            var toggle = box.querySelector('[data-qr-toggle]'), panel = box.querySelector('[data-qr-panel]'), message = box.querySelector('[data-qr-message]');
            var scanner = null, last = '', lastAt = 0, url = box.dataset.scanUrl;

            function say(text, tone) {
                message.textContent = text;
                message.className = 'small text-center mt-2 ' + (tone === 'muted' ? 'text-muted' : 'fw-semibold text-' + tone);
            }

            function close() {
                if (scanner) scanner.stop().catch(function () {});
                scanner = null;
                panel.classList.add('d-none');
                toggle.querySelector('span').textContent = 'Buka kamera';
            }

            function found(code) {
                // QR yang sama terbaca berkali-kali selama masih di depan kamera: cukup sekali.
                if (code === last && Date.now() - lastAt < 3000) return;
                last = code; lastAt = Date.now();

                if (url) { send(code); return; }

                var input = Array.prototype.find.call(document.querySelectorAll('input[data-qr]'), function (el) { return el.dataset.qr === code; });
                if (!input) { say('QR ini bukan milik murid di sesi ini. Coba kartu yang lain ya.', 'danger'); return; }

                input.checked = true;
                var row = input.closest('[data-qr-row]');
                if (row) {
                    row.classList.add('bg-success-subtle');
                    setTimeout(function () { row.classList.remove('bg-success-subtle'); }, 1500);
                }
                if (navigator.vibrate) navigator.vibrate(80);
                var left = Array.prototype.filter.call(document.querySelectorAll('input[data-qr]'), function (el) { return !el.checked; }).length;
                say(input.dataset.name + ' hadir. ' + (left ? 'Lanjut scan murid berikutnya.' : 'Semua murid sudah tercatat hadir, tinggal simpan.'), 'success');
            }

            // Mode halaman scan: server yang memutuskan (QR sah, murid di sesi pengajar ini, belum pernah discan).
            function send(code) {
                say('Memeriksa...', 'muted');
                fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ code: code })
                }).then(function (res) {
                    return res.json().catch(function () { return {}; }).then(function (data) {
                        if (!res.ok) {
                            say(data.message || (res.status === 429 ? 'Scan terlalu cepat, tunggu sebentar ya.' : res.status === 419 ? 'Halaman sudah lama terbuka. Muat ulang dulu ya.' : 'Scan belum berhasil, coba lagi.'), 'danger');
                            return;
                        }
                        if (data.reload) {
                            say(data.message, 'success');
                            setTimeout(function () { window.location.reload(); }, 1500);
                            return;
                        }
                        var count = document.querySelector('[data-scan-count="' + data.session + '"]');
                        if (count) count.textContent = Number(count.textContent) + 1;
                        if (navigator.vibrate) navigator.vibrate(80);
                        say(data.message + ' Lanjut scan murid berikutnya.', 'success');
                    });
                }).catch(function () { say('Koneksi terputus. Cek internet lalu scan lagi.', 'danger'); });
            }

            toggle.addEventListener('click', function () {
                if (scanner) { close(); return; }
                if (!window.Html5Qrcode) { say('Pemindai belum siap. Muat ulang halaman, atau pilih kehadiran manual di bawah.', 'danger'); panel.classList.remove('d-none'); return; }

                panel.classList.remove('d-none');
                toggle.querySelector('span').textContent = 'Tutup kamera';
                say('Menyalakan kamera...', 'muted');
                scanner = new Html5Qrcode('qr-reader');
                scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 220 }, found)
                    .then(function () { say('Arahkan kamera ke QR di kartu absen murid.', 'muted'); })
                    .catch(function () {
                        scanner = null;
                        toggle.querySelector('span').textContent = 'Buka kamera';
                        say('Kamera belum bisa dibuka. Izinkan akses kamera di browser, atau pilih kehadiran manual di bawah.', 'danger');
                    });
            });

            window.addEventListener('pagehide', close);
        })();
    </script>
@endpush
