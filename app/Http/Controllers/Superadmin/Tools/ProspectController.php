<?php

namespace App\Http\Controllers\Superadmin\Tools;

use App\Http\Controllers\Controller;
use App\Models\EmailBlastSetting;
use App\Models\Prospect;
use App\Models\ProspectAgentSetting;
use App\Services\Contact\ContactBookService;
use App\Services\Marketing\ProspectAgent;
use App\Support\XlsxExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Superadmin › Tools › Prospek: calon pelanggan yang disimpan dari Tools › Open Places, untuk follow-up. */
class ProspectController extends Controller
{
    /** Kolom Excel, mengikuti Google Maps + follow-up. */
    private const COLUMNS = [
        'name' => 'Nama', 'category' => 'Kategori', 'segment' => 'Segmen', 'score' => 'Skor', 'score_reason' => 'Alasan Skor', 'address' => 'Alamat', 'city' => 'Kecamatan / Kota', 'province' => 'Provinsi',
        'postal_code' => 'Kode Pos', 'phone' => 'Telepon', 'whatsapp' => 'WhatsApp', 'email' => 'Email', 'website' => 'Website',
        'rating' => 'Rating', 'rating_count' => 'Jumlah Ulasan', 'opening_hours' => 'Jam Buka', 'maps_url' => 'Link Peta',
        'lat' => 'Latitude', 'lon' => 'Longitude', 'is_closed' => 'Buka / Tutup', 'status' => 'Status', 'notes' => 'Catatan',
        'contacted_at' => 'Dihubungi', 'created_at' => 'Disimpan',
    ];

    public function index(Request $request): View
    {
        return view('superadmin.tools.prospects', [
            'prospects' => $this->sorted($this->filtered($request), $request)->paginate(15)->withQueryString(),
            // Angka di tab status mengikuti filter cari/segmen/kota yang sedang dipakai.
            'counts' => $this->filtered($request, false)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
            'cities' => Prospect::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            // [segmen => jumlah prospek]: untuk filter & hapus per segmen.
            'segments' => Prospect::query()->whereNotNull('segment')->selectRaw('segment, COUNT(*) AS total')->groupBy('segment')->orderBy('segment')->pluck('total', 'segment'),
        ]);
    }

    /** Tab Agent: pengaturan & riwayat Agent Prospek (App\Services\Marketing\ProspectAgent). */
    public function agent(): View
    {
        return view('superadmin.tools.prospects', ['tab' => 'agent', 'setting' => ProspectAgentSetting::current()]);
    }

    public function updateAgent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'is_active' => ['nullable', 'boolean'],
            'segments' => ['required', 'string', 'max:3000'],
            'cities' => ['required', 'string', 'max:2000'],
            'daily_quota' => ['required', 'integer', 'between:1,200'],
            'run_at' => ['required', 'date_format:H:i'],
            'report_email' => ['nullable', 'email', 'max:150'],
        ]);
        $clean = fn (string $text, int $max) => trim(mb_substr(preg_replace('/[^\p{L}\p{N}\s&().,\/-]/u', '', $text), 0, $max));

        // Satu baris "Nama segmen: kata kunci 1, kata kunci 2" (maks 20 segmen x 6 kata kunci).
        $segments = collect(preg_split('/\R/', $data['segments']))->map(function ($line) use ($clean) {
            [$name, $keywords] = array_pad(explode(':', $line, 2), 2, '');
            $keywords = collect(explode(',', $keywords ?: $name))->map(fn ($k) => $clean($k, 50))->filter()->unique()->take(6)->values()->all();

            return $clean($name, 60) !== '' && $keywords ? ['name' => $clean($name, 60), 'keywords' => $keywords] : null;
        })->filter()->take(20)->values()->all();
        $cities = collect(preg_split('/\R/', $data['cities']))->map(fn ($c) => $clean($c, 80))->filter()->unique()->take(50)->values()->all();

        if (! $segments || ! $cities) {
            return back()->withInput()->with('error', 'Isi minimal satu segmen (format "Nama: kata kunci, ...") dan satu kota.');
        }

        ProspectAgentSetting::current()->update([
            'is_active' => $request->boolean('is_active'),
            'segments' => $segments,
            'cities' => $cities,
            'daily_quota' => $data['daily_quota'],
            'run_at' => $data['run_at'],
            'report_email' => $data['report_email'] ?? null,
            'updated_by' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Pengaturan agent disimpan'.($request->boolean('is_active') ? ' & agent AKTIF.' : '. Agent nonaktif.'));
    }

    /** Template email pembuka per segmen (tab Agent) & skor minimal prospek yang dibuatkan draf. */
    public function updateTemplates(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'min_score' => ['required', 'integer', 'between:0,100'],
            'templates' => ['nullable', 'array', 'max:20'],
            'templates.*.segment' => ['required', 'string', 'max:60'],
            'templates.*.subject' => ['required', 'string', 'max:200'],
            'templates.*.body' => ['required', 'string', 'max:5000'],
        ]);
        $setting = ProspectAgentSetting::current();
        $names = collect($setting->templateSegments());

        $setting->update([
            'templates' => collect($data['templates'] ?? [])->filter(fn ($t) => $names->contains($t['segment']))
                ->mapWithKeys(fn ($t) => [$t['segment'] => ProspectAgent::clean($t['subject'], $t['body'])])->all(),
            'min_score' => $data['min_score'],
            'updated_by' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Template email disimpan.');
    }

    /** Pengaturan kirim otomatis (tab Agent): tombol aktif, hari, jam, jumlah per jam & per hari, kontak import langsung kirim. */
    public function updateSending(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'send_enabled' => ['boolean'],
            'send_days' => ['required_if_accepted:send_enabled', 'array'],
            'send_days.*' => ['integer', 'between:1,7'],
            'send_start' => ['required', 'integer', 'between:0,23'],
            'send_end' => ['required', 'integer', 'between:1,24', 'gt:send_start'],
            'send_per_hour' => ['required', 'integer', 'between:1,200'],
            'send_per_day' => ['required', 'integer', 'between:1,2000'],
            'auto_approve_import' => ['boolean'],
        ], ['send_end.gt' => 'Jam selesai harus setelah jam mulai.', 'send_days.required_if_accepted' => 'Centang minimal satu hari kirim.']);
        $setting = ProspectAgentSetting::current();

        // Kontak import dikirim tanpa diperiksa satu per satu, jadi template-nya wajib sudah ditulis & disimpan sendiri.
        $importTemplate = ($setting->templates ?? [])[ProspectAgentSetting::IMPORT_SEGMENT]['body'] ?? '';
        if ($request->boolean('auto_approve_import') && ($importTemplate === '' || str_contains($importTemplate, '(Tulis isi promo di sini)'))) {
            return back()->withInput()->with('error', 'Tulis & simpan dulu template "'.ProspectAgentSetting::IMPORT_SEGMENT.'" (isi promonya), baru aktifkan kirim langsung.');
        }

        $setting->update([
            'send_enabled' => $request->boolean('send_enabled'),
            'send_days' => array_values(array_unique(array_map('intval', $data['send_days'] ?? $setting->sendDays()))),
            'auto_approve_import' => $request->boolean('auto_approve_import'),
            'updated_by' => $request->user()->getKey(),
        ] + array_intersect_key($data, array_flip(['send_start', 'send_end', 'send_per_hour', 'send_per_day'])));

        return back()->with('success', $setting->send_enabled ? 'Pengiriman otomatis AKTIF.' : 'Pengiriman otomatis dimatikan. Tidak ada email yang dikirim.');
    }

    /** Kirim tes satu template ke email sendiri (masuk hitungan batas harian Email Blast). */
    public function testTemplate(Request $request, ProspectAgent $agent): RedirectResponse
    {
        $setting = ProspectAgentSetting::current();
        $data = $request->validate([
            'segment' => ['required', Rule::in($setting->templateSegments())],
            'email' => ['required', 'email', 'max:150'],
        ]);

        try {
            $agent->sendTest($setting, $data['segment'], $data['email'], $request->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Email tes belum terkirim: '.$e->getMessage());
        }

        return back()->with('success', "Email tes \"{$data['segment']}\" sedang dikirim ke {$data['email']}. Cek kotak masuk (dan folder spam) 1–2 menit lagi.");
    }

    /** Import kontak dari Excel/CSV (kolom nama, whatsapp dan/atau email; opsional kota, catatan). Kontak dobel dilewati. */
    public function import(Request $request, ContactBookService $book): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']],
            ['file.mimes' => 'File harus Excel (.xlsx/.xls) atau CSV.', 'file.max' => 'Ukuran file maksimal 5 MB.']);

        [$rows] = $book->read($request->file('file'), ['city' => ['kota', 'city'], 'notes' => ['catatan', 'notes', 'link']], Prospect::IMPORT_MAX_ROWS);
        if (! collect($rows)->contains(fn ($r) => filled($r['email']) || filled($r['phone']))) {
            return back()->with('error', 'Kolom whatsapp atau email tidak ditemukan. Baris pertama harus berisi judul kolom: nama, whatsapp, email, kota, catatan. Pakai file contoh supaya pas.');
        }

        $result = Prospect::importRows($rows, $request->user()->getKey());

        return back()->with('success', "Import selesai: {$result['saved']} kontak masuk".($result['skipped'] ? ", {$result['skipped']} dilewati (tanpa WhatsApp/email yang valid, atau sudah ada)." : '.')
            .(count($rows) >= Prospect::IMPORT_MAX_ROWS ? ' Maksimal '.Prospect::IMPORT_MAX_ROWS.' baris per file; lanjutkan sisanya dengan file berikutnya.' : ''));
    }

    public function importTemplate(ContactBookService $book): StreamedResponse
    {
        return $book->template('contoh-import-kontak.xlsx', [
            ['nama', 'whatsapp', 'email', 'kota', 'catatan'],
            ['Budi Santoso', '081234567890', 'budi@contoh.com', 'Bekasi', ''],
            ['Siti Aminah', '', 'siti@contoh.com', 'Jakarta Selatan', ''],
            ['Bimbel Cerdas', '081298765432', '', 'Depok', 'Dari grup Facebook, tanya aplikasi absensi'],
        ]);
    }

    /** Link berhenti dari email (URL bertanda tangan, publik). GET = konfirmasi, POST = berhenti. */
    public function unsubscribe(Request $request, Prospect $prospect): View
    {
        if ($request->isMethod('post')) {
            $prospect->forceFill(['outreach_status' => 'unsubscribed', 'status' => 'rejected'])->save();
        }

        return view('shared.unsubscribe', ['email' => $prospect->email, 'done' => $prospect->outreach_status === 'unsubscribed']);
    }

    /** Contoh template dari AI untuk semua segmen (menimpa template segmen itu), lalu bisa diedit. */
    public function aiTemplates(ProspectAgent $agent): RedirectResponse
    {
        try {
            $count = $agent->suggestTemplates(ProspectAgentSetting::current());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'AI belum bisa membuat template: '.mb_substr($e->getMessage(), 0, 150));
        }

        return back()->with($count ? 'success' : 'error', $count ? "AI membuat {$count} template. Periksa & ubah sesuai kebutuhan, lalu Simpan." : 'Jawaban AI tidak bisa dipakai, coba lagi.');
    }

    /** Tab Persetujuan: draf email pembuka dari agent. Yang disetujui dikirim lewat Email Blast (ProspectAgent::sendApproved). */
    public function outreach(): View
    {
        return view('superadmin.tools.prospects', [
            'tab' => 'outreach',
            'drafts' => Prospect::where('outreach_status', 'draft')->orderByDesc('score')->paginate(20),
            'approvedCount' => Prospect::where('outreach_status', 'approved')->count(),
            'blastSetting' => EmailBlastSetting::current(),
        ]);
    }

    /** Satu draf: simpan editan & setujui, atau lewati. Hanya draf yang masih "draf" yang bisa diubah. */
    public function updateOutreach(Request $request, Prospect $prospect): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'skip'])],
            'subject' => ['required_if:action,approve', 'nullable', 'string', 'max:200'],
            'body' => ['required_if:action,approve', 'nullable', 'string', 'max:5000'],
        ]);

        if ($prospect->outreach_status !== 'draft') {
            return back()->with('error', "Draf {$prospect->name} sudah diproses sebelumnya.");
        }

        $clean = ProspectAgent::clean((string) $data['subject'], (string) $data['body']);
        $prospect->forceFill($data['action'] === 'approve'
            ? ['outreach_status' => 'approved', 'outreach_subject' => $clean['subject'], 'outreach_body' => $clean['body']]
            : ['outreach_status' => 'skipped'])->save();

        return back()->with('success', $data['action'] === 'approve' ? "Email ke {$prospect->name} disetujui, dikirim di jam kerja." : "Draf {$prospect->name} dilewati.");
    }

    /** Setujui / lewati banyak draf sekaligus (tanpa mengubah isinya). */
    public function bulkOutreach(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'skip'])],
            'ids' => ['required', 'array', 'max:100'],
            'ids.*' => ['uuid'],
        ]);
        $count = Prospect::whereIn('id', $data['ids'])->where('outreach_status', 'draft')
            ->update(['outreach_status' => $data['action'] === 'approve' ? 'approved' : 'skipped', 'updated_at' => now()]);

        return back()->with('success', $count.' draf '.($data['action'] === 'approve' ? 'disetujui, dikirim di jam kerja.' : 'dilewati.'));
    }

    /** Jalankan satu putaran sekarang (lewat antrean). Kuota harian & kuota Open Places tetap berlaku. */
    public function runAgent(): RedirectResponse
    {
        dispatch(fn () => app(ProspectAgent::class)->run(force: true));

        return back()->with('success', 'Agent sedang berjalan. Muat ulang halaman ini 1-2 menit lagi untuk melihat riwayatnya.');
    }

    /** Ubah status, catatan, dan kolom yang diisi manual (rating, ulasan, jam buka, email, website). */
    public function update(Request $request, Prospect $prospect): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Prospect::STATUSES))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'rating' => ['nullable', 'numeric', 'between:0,5'],
            'rating_count' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'opening_hours' => ['nullable', 'string', 'max:300'],
            'email' => ['nullable', 'email', 'max:150'],
            'website' => ['nullable', 'url:http,https', 'max:300'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        // Nomor HP 08xx / +628xx -> WhatsApp 628xx.
        $digits = preg_replace('/^0/', '62', preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')));
        $data['whatsapp'] = preg_match('/^628\d{6,13}$/', $digits) ? $digits : null;
        // Tanggal pertama kali dihubungi tercatat otomatis saat status pertama kali keluar dari "Baru".
        $data['contacted_at'] = $prospect->contacted_at ?? ($data['status'] !== 'new' ? now() : null);

        $prospect->update($data);

        return back()->with('success', "Prospek {$prospect->name} diperbarui.");
    }

    /**
     * Hapus banyak prospek sekaligus: yang dicentang di daftar, atau SEMUA prospek dalam satu segmen (mis. hasil impor yang
     * salah). Hapus segmen wajib diketik "HAPUS" dan dicatat di log, karena tidak bisa dikembalikan.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        if ($request->filled('segment')) {
            $data = $request->validate(
                ['segment' => ['required', 'string', 'max:100'], 'confirm' => ['required', 'in:HAPUS']],
                ['confirm.required' => 'Ketik HAPUS untuk menghapus seluruh segmen.', 'confirm.in' => 'Ketik HAPUS (huruf besar) untuk menghapus seluruh segmen.'],
            );
            $count = Prospect::where('segment', $data['segment'])->delete();
            Log::info('[PROSPEK] Satu segmen dihapus', ['segment' => $data['segment'], 'jumlah' => $count, 'user' => $request->user()->getKey()]);

            return redirect()->route('superadmin.tools.prospects.index')->with('success', "{$count} prospek di segmen {$data['segment']} dihapus.");
        }

        $data = $request->validate(['ids' => ['required', 'array', 'max:500'], 'ids.*' => ['uuid']], ['ids.required' => 'Centang dulu prospek yang mau dihapus.']);
        $count = Prospect::whereIn('id', $data['ids'])->delete();

        return back()->with('success', "{$count} prospek dihapus.");
    }

    public function destroy(Prospect $prospect): RedirectResponse
    {
        $prospect->delete();

        return back()->with('success', "Prospek {$prospect->name} dihapus.");
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->sorted($this->filtered($request), $request)->limit(5000)->get()->map(fn (Prospect $p) => collect(self::COLUMNS)->map(fn ($label, $key) => match ($key) {
            'is_closed' => $p->is_closed ? 'Tutup' : 'Buka',
            'status' => $p->statusLabel(),
            'contacted_at', 'created_at' => $p->{$key}?->format('Y-m-d H:i') ?? '',
            'rating', 'lat', 'lon', 'score' => $p->{$key} ?? '',
            'rating_count' => $p->rating_count ?? '',
            default => (string) ($p->{$key} ?? ''),
        })->all());

        return XlsxExport::download('prospek-'.now()->format('Ymd-His'), array_values(self::COLUMNS), $rows);
    }

    /** Terbaru (bawaan) atau skor tertinggi dulu (?sort=score). */
    private function sorted(Builder $query, Request $request): Builder
    {
        return $request->query('sort') === 'score' ? $query->orderByDesc('score')->latest() : $query->latest();
    }

    private function filtered(Request $request, bool $withStatus = true): Builder
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(array_keys(Prospect::STATUSES))],
            'city' => ['nullable', 'string', 'max:150'],
            'segment' => ['nullable', 'string', 'max:60'],
        ]);
        $search = trim((string) ($data['search'] ?? ''));

        return Prospect::query()
            ->when($withStatus ? ($data['status'] ?? null) : null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['city'] ?? null, fn ($q, $city) => $q->where('city', $city))
            ->when($data['segment'] ?? null, fn ($q, $segment) => $q->where('segment', $segment))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('address', 'like', "%{$search}%")));
    }
}
