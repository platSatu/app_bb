<?php

namespace App\Http\Controllers\Superadmin\Monitor;

use App\Http\Controllers\Controller;
use App\Models\Deposit;
use App\Models\Disbursement;
use App\Models\InvoiceCheckout;
use App\Models\InvoicePayment;
use App\Models\TicketOrder;
use App\Models\WalletWithdrawal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Settings > Antrean & Tugas: semua yang nyangkut di satu tempat.
 * Antrean email/job, job gagal, pembayaran yang callback-nya tidak datang, dan transfer yang belum pasti.
 */
class MonitorController extends Controller
{
    /** Job menunggu lebih lama dari ini = worker kemungkinan mati. */
    private const STUCK_MINUTES = 3;

    /** Pembayaran pending yang lewat batas + jeda ini = callback/expire tidak jalan (normalnya beres dalam 15 menit). */
    private const PAYMENT_STUCK_MINUTES = 20;

    public const HEARTBEAT = 'scheduler:heartbeat';

    public function index(): View
    {
        $now = now()->getTimestamp();
        $jobs = DB::table('jobs')->orderBy('id')->limit(100)->get()->map(fn ($job) => (object) [
            'id' => $job->id,
            'name' => $this->jobName($job->payload),
            'queue' => $job->queue,
            'attempts' => $job->attempts,
            'since' => Carbon::createFromTimestamp($job->reserved_at ?? $job->available_at),
            'running' => $job->reserved_at !== null,
            'stuck' => ($now - ($job->reserved_at ?? $job->available_at)) > self::STUCK_MINUTES * 60,
        ]);
        $failed = DB::table('failed_jobs')->latest('failed_at')->limit(100)->get()->map(fn ($job) => (object) [
            'uuid' => $job->uuid,
            'name' => $this->jobName($job->payload),
            'error' => Str::limit(strtok($job->exception, "\n"), 220),
            'failed_at' => Carbon::parse($job->failed_at),
        ]);
        $heartbeat = Cache::get(self::HEARTBEAT);

        return view('superadmin.monitor.index', [
            'jobs' => $jobs,
            'jobTotal' => DB::table('jobs')->count(),
            'failed' => $failed,
            'failedTotal' => DB::table('failed_jobs')->count(),
            'heartbeat' => $heartbeat ? Carbon::parse($heartbeat) : null,
            'payments' => $this->stuckPayments(),
            'transfers' => [
                ['label' => 'Tarik saldo user', 'count' => WalletWithdrawal::whereIn('status', [WalletWithdrawal::STATUS_PROCESSING, WalletWithdrawal::STATUS_NEEDS_REVIEW])->count(), 'route' => 'superadmin.withdrawals.index'],
                ['label' => 'Disbursement owner', 'count' => Disbursement::whereIn('status', [Disbursement::STATUS_PROCESSING, Disbursement::STATUS_NEEDS_REVIEW])->count(), 'route' => 'superadmin.payouts.index'],
            ],
        ]);
    }

    /** Coba lagi job gagal: satu (uuid) atau semua. */
    public function retry(Request $request): RedirectResponse
    {
        $id = $request->input('uuid', 'all');
        Artisan::call('queue:retry', ['id' => [$id]]);

        return back()->with('success', $id === 'all' ? 'Semua job gagal dimasukkan lagi ke antrean.' : 'Job dimasukkan lagi ke antrean.');
    }

    /** Hapus job gagal: satu (uuid) atau semua. */
    public function forget(Request $request): RedirectResponse
    {
        $request->filled('uuid')
            ? DB::table('failed_jobs')->where('uuid', $request->input('uuid'))->delete()
            : DB::table('failed_jobs')->delete();

        return back()->with('success', 'Job gagal dihapus.');
    }

    /** Buang satu job dari antrean (mis. email ke alamat yang salah dan terus diulang). */
    public function destroyJob(int $job): RedirectResponse
    {
        DB::table('jobs')->where('id', $job)->delete();

        return back()->with('success', 'Job dibuang dari antrean.');
    }

    /** Jalankan sekarang pengecekan pembayaran pending: dicek ke Duitku dulu, yang sudah dibayar tetap dilunasi. */
    public function process(): RedirectResponse
    {
        set_time_limit(0);

        foreach (['deposits:expire', 'invoices:expire', 'tickets:expire'] as $command) {
            Artisan::call($command);
        }

        $left = collect($this->stuckPayments())->sum('count');

        return back()->with($left ? 'warning' : 'success', $left
            ? "Sudah diproses, tapi masih ada {$left} pembayaran tertahan. Kemungkinan Duitku belum bisa dihubungi; coba lagi beberapa saat."
            : 'Semua pembayaran tertahan sudah dicek ke Duitku dan dibereskan.');
    }

    private function stuckPayments(): array
    {
        $limit = now()->subMinutes(self::PAYMENT_STUCK_MINUTES);
        $rows = [
            'Top up saldo' => Deposit::where('status', Deposit::STATUS_PENDING),
            'Bayar invoice (link)' => InvoicePayment::where('status', InvoicePayment::STATUS_PENDING),
            'Bayar Tagihan Saya' => InvoiceCheckout::where('status', InvoiceCheckout::STATUS_PENDING),
            'Pesanan tiket' => TicketOrder::where('status', TicketOrder::STATUS_PENDING),
        ];

        return collect($rows)->map(fn ($query, $label) => [
            'label' => $label,
            'count' => (clone $query)->where('expires_at', '<', $limit)->count(),
            'oldest' => ($oldest = (clone $query)->where('expires_at', '<', $limit)->min('expires_at')) ? Carbon::parse($oldest) : null,
        ])->values()->all();
    }

    /** Nama job yang mudah dibaca, mis. "Email: TicketOrderPaid". */
    private function jobName(string $payload): string
    {
        $data = json_decode($payload, true) ?: [];
        $name = class_basename($data['displayName'] ?? 'Job');

        if (preg_match('/App\\\\+Mail\\\\+(\w+)/', $data['data']['command'] ?? '', $mail)) {
            return 'Email: '.$mail[1];
        }

        return $name;
    }
}
