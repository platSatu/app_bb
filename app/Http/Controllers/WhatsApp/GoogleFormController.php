<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\InvoiceSetting;
use App\Models\WaDevice;
use App\Models\WaFormIntegration;
use App\Models\WaFormSubmission;
use App\Models\WaTemplate;
use App\Services\Subscription\AppAccess;
use App\Services\WhatsApp\FormRelay;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WhatsApp > Third Party > Google Form: daftar form (tab pertama), panduan (tab terakhir),
 * pengaturan (pesan, registrasi ulang, pembayaran), dan laporan pendaftar + export Excel.
 */
class GoogleFormController extends Controller
{
    use InWhatsAppArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.google-form.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'hasDevice' => WaDevice::where('branch_id', $branch->id)->exists(),
            'forms' => WaFormIntegration::where('branch_id', $branch->id)->with('device:id,name,phone')->withCount([
                'submissions',
                'submissions as sent_count' => fn ($q) => $q->where('status', 'sent'),
                'submissions as attended_count' => fn ($q) => $q->whereNotNull('attended_at'),
            ])->latest()->paginate(15)->withQueryString(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.google-form.form', [...$this->formData($branch->id), 'integration' => new WaFormIntegration(['is_active' => true]), 'branchId' => $branch->id]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = $this->ownerBranches($request)->firstWhere('id', $request->input('branch_id'));
        abort_unless($branch, 404);

        $integration = new WaFormIntegration();
        $integration->forceFill(['branch_id' => $branch->id, 'type' => 'google_form', 'webhook_token' => WaFormIntegration::newToken()]);
        $this->save($request, $integration);

        return redirect()->route('whatsapp.google-forms.show', [$integration, 'tab' => 'script'])
            ->with('success', 'Google Form siap dihubungkan. Pasang script di bawah ini ke form Anda.');
    }

    /** Laporan: pendaftar, pesan terkirim, pembayaran, kehadiran. */
    public function show(Request $request, string $form): View
    {
        $integration = $this->integration($request, $form)->load('device:id,name,phone,status');
        $all = $integration->submissions();

        return view('whatsapp.google-form.show', [
            'integration' => $integration,
            'stats' => [
                'daftar' => (clone $all)->count(),
                'terkirim' => (clone $all)->where('status', 'sent')->count(),
                'lunas' => $integration->payment_enabled ? (clone $all)->whereHas('invoice', fn ($q) => $q->where('status', 'paid'))->count() : null,
                'hadir' => $integration->registration_enabled ? (clone $all)->whereNotNull('attended_at')->count() : null,
            ],
            'submissions' => $integration->submissions()->with('invoice:id,status,paid_at')
                ->when($request->query('cari'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$term.'%')->orWhere('phone', 'like', '%'.preg_replace('/\D/', '', $term).'%')->orWhere('code', strtoupper($term))))
                ->latest('created_at')->paginate(25)->withQueryString(),
        ]);
    }

    public function edit(Request $request, string $form): View
    {
        $integration = $this->integration($request, $form);

        return view('whatsapp.google-form.form', [...$this->formData($integration->branch_id), 'integration' => $integration, 'branchId' => $integration->branch_id]);
    }

    public function update(Request $request, string $form): RedirectResponse
    {
        $integration = $this->integration($request, $form);
        $this->save($request, $integration);

        return redirect()->route('whatsapp.google-forms.show', $integration)->with('success', 'Pengaturan Google Form diperbarui.');
    }

    public function destroy(Request $request, string $form): RedirectResponse
    {
        $integration = $this->integration($request, $form);
        $integration->delete();

        return redirect()->route('whatsapp.google-forms.index', ['branch' => $integration->branch_id])
            ->with('success', "Integrasi {$integration->name} dihapus. Script di Google Form tidak lagi mengirim WhatsApp.");
    }

    public function regenerate(Request $request, string $form): RedirectResponse
    {
        $integration = $this->integration($request, $form);
        $integration->forceFill(['webhook_token' => WaFormIntegration::newToken()])->save();

        return back()->with('success', 'URL webhook baru dibuat. URL lama langsung tidak berlaku, salin ulang script ke Google Form ya.');
    }

    /** Kirim ulang QR registrasi ulang ke satu pendaftar (mis. pesan sebelumnya gagal atau hilang). */
    public function resend(Request $request, string $form, string $submission, FormRelay $relay): RedirectResponse
    {
        $row = $this->integration($request, $form)->submissions()->with('invoice', 'integration')->findOrFail($submission);
        abort_unless($row->code && $row->isPaid(), 422, 'Pendaftar ini belum bisa dikirimi QR.');

        $row->forceFill(['qr_sent_at' => null])->save();
        $sent = $relay->sendTicket($row);

        return back()->with($sent ? 'success' : 'error', $sent ? "QR registrasi ulang dikirim ulang ke {$row->displayName()}." : 'QR gagal dikirim: '.$row->fresh()->error);
    }

    /** Laporan pendaftar ke Excel (.xlsx). Bisa dibuka di Google Sheets lewat File > Import. */
    public function export(Request $request, string $form): StreamedResponse
    {
        $integration = $this->integration($request, $form);
        $rows = $integration->submissions()->with('invoice:id,status,paid_at')->oldest('created_at')->get();
        $questions = $rows->flatMap(fn ($r) => array_keys($r->payload ?? []))->unique()->values()->all();

        $header = ['Waktu daftar', 'Nama', 'WhatsApp', 'Pesan WhatsApp', 'Keterangan'];
        if ($integration->payment_enabled) {
            array_push($header, 'Pembayaran', 'Nominal', 'Dibayar pada');
        }
        if ($integration->registration_enabled) {
            array_push($header, 'Kode', 'Hadir pada');
        }

        $data = $rows->map(fn (WaFormSubmission $r) => [
            $r->created_at->format('Y-m-d H:i'), $r->name, $r->phone ? '+'.$r->phone : '', $r->status === 'sent' ? 'Terkirim' : 'Gagal', $r->error,
            ...($integration->payment_enabled ? [$r->paymentLabel(), $r->invoice_id ? $integration->amount : null, $r->invoice?->paid_at?->format('Y-m-d H:i')] : []),
            ...($integration->registration_enabled ? [$r->code, $r->attended_at?->format('Y-m-d H:i')] : []),
            ...array_map(fn ($q) => $r->payload[$q] ?? '', $questions),
        ])->all();

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet()->setTitle('Pendaftar');
        $sheet->fromArray([[...$header, ...$questions], ...$data], null, 'A1', true);
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->freezePane('A2');

        return response()->streamDownload(fn () => (new Xlsx($book))->save('php://output'), 'laporan-'.Str::slug($integration->name).'-'.now()->format('Ymd').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function integration(Request $request, string $id): WaFormIntegration
    {
        return WaFormIntegration::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->findOrFail($id);
    }

    private function formData(string $branchId): array
    {
        return [
            'devices' => WaDevice::where('branch_id', $branchId)->orderBy('name')->get(['id', 'name', 'phone', 'status']),
            // Isi template bisa disalin ke pesan (teks saja).
            'templates' => WaTemplate::where('branch_id', $branchId)->where('is_active', true)->orderBy('name')->get()->map(fn ($t) => ['name' => $t->name, 'text' => $t->toText()]),
            'invoicesActive' => app(AppAccess::class)->branchActive($branchId, 'invoices'),
            'setting' => InvoiceSetting::current(),
        ];
    }

    private function save(Request $request, WaFormIntegration $integration): void
    {
        $setting = InvoiceSetting::current();
        $registration = $request->boolean('registration_enabled');
        $payment = $request->boolean('payment_enabled');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'device_id' => ['required', Rule::exists('wa_devices', 'id')->where('branch_id', $integration->branch_id)],
            'phone_field' => ['required', 'string', 'max:191'],
            'name_field' => ['nullable', 'string', 'max:191'],
            'message' => ['required', 'string', 'max:4000'],
            // PIN aplikasi registrasi ulang: wajib saat pertama kali diaktifkan, kosong = tidak diubah.
            'checkin_pin' => [Rule::requiredIf($registration && ! $integration->checkin_pin_hash), 'nullable', 'digits:6'],
            'amount' => array_filter([Rule::requiredIf($payment), 'nullable', 'integer', 'min:'.max(1, (int) $setting->min_amount), $setting->max_amount ? 'max:'.(int) $setting->max_amount : null]),
        ], ['checkin_pin.required' => 'Buat PIN 6 angka untuk petugas registrasi ulang.', 'amount.required' => 'Isi nominal pembayaran.'],
            ['name' => 'nama integrasi', 'device_id' => 'nomor pengirim', 'phone_field' => 'judul pertanyaan nomor WhatsApp', 'name_field' => 'judul pertanyaan nama', 'message' => 'isi pesan', 'checkin_pin' => 'PIN', 'amount' => 'nominal']);

        if ($payment && ! app(AppAccess::class)->branchActive($integration->branch_id, 'invoices')) {
            throw ValidationException::withMessages(['payment_enabled' => 'Pembayaran butuh paket Tagihan (Invoice) yang aktif di branch ini.']);
        }

        $integration->fill([
            ...$data,
            'phone_field' => trim($data['phone_field']),
            'name_field' => filled($data['name_field'] ?? null) ? trim($data['name_field']) : null,
            'is_active' => $request->boolean('is_active'),
            'registration_enabled' => $registration,
            'payment_enabled' => $payment,
            'amount' => $payment ? $data['amount'] : $integration->amount,
        ]);
        if ($registration && ! $integration->checkin_token) {
            $integration->checkin_token = Str::random(48);
        }
        if (filled($data['checkin_pin'] ?? null)) {
            $integration->checkin_pin_hash = Hash::make($data['checkin_pin']);
        }
        $integration->save();
    }
}
