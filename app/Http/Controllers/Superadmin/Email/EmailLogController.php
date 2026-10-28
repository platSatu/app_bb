<?php

namespace App\Http\Controllers\Superadmin\Email;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Superadmin > Riwayat Email: semua email aplikasi, statusnya, dan kirim ulang yang gagal. */
class EmailLogController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));
        $query = EmailLog::query()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('recipient', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%")))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->date('date_from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('date_to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()));

        $items = $query->latest()->paginate(30)->withQueryString();

        return view('superadmin.email.index', [
            'items' => $items,
            // Hanya yang masih ada di failed_jobs yang bisa dikirim ulang.
            'retryable' => DB::table('failed_jobs')->whereIn('uuid', $items->getCollection()->where('status', EmailLog::STATUS_FAILED)->pluck('job_uuid')->filter())->pluck('uuid')->flip(),
            'types' => EmailLog::query()->distinct()->orderBy('type')->pluck('type'),
            'summary' => [
                'sent' => EmailLog::where('status', EmailLog::STATUS_SENT)->where('created_at', '>=', now()->subDay())->count(),
                'failed' => EmailLog::where('status', EmailLog::STATUS_FAILED)->where('created_at', '>=', now()->subDay())->count(),
                'stuck' => EmailLog::where('status', EmailLog::STATUS_QUEUED)->where('created_at', '<', now()->subMinutes(EmailLog::STUCK_MINUTES))->count(),
            ],
        ]);
    }

    /** Kirim ulang = jalankan lagi job gagalnya; isi email dibuat ulang dari data terbaru. */
    public function resend(EmailLog $emailLog): RedirectResponse
    {
        $retryable = $emailLog->status === EmailLog::STATUS_FAILED && $emailLog->job_uuid
            && DB::table('failed_jobs')->where('uuid', $emailLog->job_uuid)->exists();

        if (! $retryable) {
            return back()->with('error', 'Email ini tidak bisa dikirim ulang (masih dicoba otomatis, atau data job gagalnya sudah dihapus).');
        }

        Artisan::call('queue:retry', ['id' => [$emailLog->job_uuid]]);
        $emailLog->update(['status' => EmailLog::STATUS_QUEUED, 'error' => null]);

        return back()->with('success', "Email ke {$emailLog->recipient} dimasukkan lagi ke antrean.");
    }
}
