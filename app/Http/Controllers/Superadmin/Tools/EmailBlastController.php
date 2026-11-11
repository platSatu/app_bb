<?php

namespace App\Http\Controllers\Superadmin\Tools;

use App\Http\Controllers\Controller;
use App\Jobs\SendEmailBlast;
use App\Models\EmailBlast;
use App\Models\EmailBlastSetting;
use App\Models\Prospect;
use App\Models\ProspectAgentSetting;
use App\Services\Marketing\EmailBlastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

/** Superadmin › Tools › Email Blast: tab Inbox, Terkirim, Email Baru, Pengaturan. */
class EmailBlastController extends Controller
{
    private const TABS = ['inbox', 'sent', 'compose', 'settings'];

    public function __construct(private readonly EmailBlastService $service) {}

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'inbox';
        $setting = EmailBlastSetting::current();
        $data = ['tab' => $tab, 'setting' => $setting, 'usedToday' => $this->service->usedToday()];

        if ($tab === 'inbox' && $setting->isConfigured()) {
            $page = max(1, min(500, (int) $request->query('page', 1)));

            try {
                $data['inbox'] = $this->service->inbox($page) + ['page' => $page];
            } catch (Throwable $e) {
                report($e);
                $data['inboxError'] = 'Inbox belum bisa dibuka: '.mb_substr($e->getMessage(), 0, 200);
            }
        }

        if ($tab === 'compose') {
            // Template email dari Tools › Prospek › Agent, untuk diisi ke Subjek & Isi (tombol "Pakai template").
            $agent = ProspectAgentSetting::current();
            $data['templates'] = collect($agent->templateSegments())->mapWithKeys(fn ($name) => [$name => $agent->template($name)])->all();
            $data['segments'] = Prospect::whereNotNull('email')->whereNotNull('segment')->distinct()->orderBy('segment')->pluck('segment');
        }

        if ($tab === 'sent') {
            $data['blasts'] = EmailBlast::with('creator:id,name')->latest()->paginate(15)->withQueryString();
        }

        return view('superadmin.tools.email-blast', $data);
    }

    public function message(int $uid): View
    {
        try {
            $message = $this->service->message($uid);
        } catch (Throwable $e) {
            report($e);
            abort(404, 'Email tidak ditemukan atau server email belum bisa dihubungi.');
        }

        return view('superadmin.tools.email-blast', ['tab' => 'inbox', 'setting' => EmailBlastSetting::current(), 'usedToday' => $this->service->usedToday(), 'message' => $message]);
    }

    public function show(EmailBlast $blast): View
    {
        return view('superadmin.tools.email-blast', ['tab' => 'sent', 'setting' => EmailBlastSetting::current(), 'usedToday' => $this->service->usedToday(), 'blast' => $blast]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:20000'],
            'recipients' => ['required', 'string', 'max:20000'],
        ]);

        try {
            $blast = $this->service->queue($data['subject'], $data['body'], EmailBlastService::parseRecipients($data['recipients']), $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Satu data dengan Prospek: penerima yang ada di Prospek ikut tercatat Dihubungi & tidak dikirimi email pembuka Agent lagi.
        Prospect::whereIn('email', $blast->recipients)->whereNull('outreach_status')
            ->update(['outreach_status' => 'sent', 'email_blast_id' => $blast->getKey(), 'updated_at' => now()]);
        Prospect::whereIn('email', $blast->recipients)->where('status', 'new')->update(['status' => 'contacted', 'contacted_at' => now()]);

        Log::info('[EMAIL BLAST] Diantrekan', ['blast' => $blast->id, 'recipients' => count($blast->recipients), 'user' => $request->user()->id]);

        return redirect()->route('superadmin.tools.email-blast.show', $blast)
            ->with('success', count($blast->recipients).' email diantrekan. Status per penerima tampil di halaman ini.');
    }

    /** Pratinjau tampilan email dari isian form Email Baru (dibuka di tab baru, tidak mengirim apa pun). */
    public function preview(Request $request): \Illuminate\Http\Response
    {
        $data = $request->validate(['subject' => ['nullable', 'string', 'max:200'], 'body' => ['required', 'string', 'max:20000']]);
        $setting = EmailBlastSetting::current();

        return response($this->service->html((string) ($data['subject'] ?? ''), $data['body'], $setting->from_name ?: config('app.name')));
    }

    /** Daftar email dari Prospek untuk kolom "Kepada" (cari nama/email, filter segmen). Yang berhenti / tidak tertarik tidak ikut. */
    public function prospects(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'segment' => ['nullable', 'string', 'max:60'], 'status' => ['nullable', 'in:new,contacted,interested,customer']]);
        $like = '%'.addcslashes((string) ($data['q'] ?? ''), '%_\\').'%';
        $query = Prospect::whereNotNull('email')->where('status', '!=', 'rejected')
            ->where(fn ($q) => $q->whereNull('outreach_status')->orWhere('outreach_status', '!=', 'unsubscribed'))
            ->when($data['q'] ?? null, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)))
            ->when($data['segment'] ?? null, fn ($q, $segment) => $q->where('segment', $segment))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status));

        return response()->json([
            'total' => (clone $query)->count(),
            'items' => $query->orderBy('name')->limit(EmailBlastService::MAX_RECIPIENTS)->get(['name', 'email', 'status'])
                ->map(fn ($p) => ['name' => $p->name, 'email' => $p->email, 'status' => $p->statusLabel()]),
        ]);
    }

    /** Lanjutkan kiriman yang berhenti di tengah (mis. worker sempat mati). Penerima yang sudah terkirim tidak dikirimi ulang. */
    public function resume(EmailBlast $blast): RedirectResponse
    {
        if ($blast->status === 'done') {
            return back()->with('error', 'Kiriman ini sudah selesai.');
        }

        SendEmailBlast::dispatch($blast->getKey());

        return back()->with('success', 'Kiriman dilanjutkan.');
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_name' => ['nullable', 'string', 'max:100'],
            'from_address' => ['required', 'email', 'max:150'],
            'host' => ['required', 'string', 'max:150', 'regex:/^[A-Za-z0-9.\-]+$/'],
            'username' => ['required', 'string', 'max:150'],
            'password' => ['nullable', 'string', 'max:300'],
            'encryption' => ['required', 'in:ssl,tls'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'imap_port' => ['required', 'integer', 'between:1,65535'],
            'daily_limit' => ['required', 'integer', 'between:1,2000'],
        ]);

        $setting = EmailBlastSetting::current();
        $setting->fill($data + ['updated_by' => $request->user()->getKey()]);

        // Password kosong = tetap pakai yang lama.
        if (filled($data['password'] ?? null)) {
            $setting->password = $data['password'];
        }

        if (! $setting->isConfigured()) {
            return back()->withInput($request->except('password'))->with('error', 'Password wajib diisi.');
        }

        // Dicek dulu ke server email, baru disimpan: pengaturan yang salah tidak menggantikan yang benar.
        if ($error = $this->service->test($setting)) {
            return back()->withInput($request->except('password'))->with('error', $error);
        }

        $setting->save();

        return redirect()->route('superadmin.tools.email-blast.index', ['tab' => 'settings'])->with('success', 'Akun email tersimpan dan berhasil terhubung (SMTP & IMAP).');
    }
}
