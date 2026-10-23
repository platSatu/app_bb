<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceCheckout;
use App\Models\InvoicePayment;
use App\Models\UserBranch;
use App\Services\Invoice\InvoiceCheckoutService;
use App\Services\Invoice\PayerInvoices;
use App\Services\Subscription\AppAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Menu Tagihan untuk pembayar: tagihan yang ditujukan ke email akunnya, tab per cabang, centang lalu bayar sekali. */
class BillController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        if (! PayerInvoices::verified($user)) {
            return view('bills.index', ['user' => $user, 'tabs' => null]);
        }

        $branches = UserBranch::with('company:id,name')
            ->whereIn('id', PayerInvoices::query($user)->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PAID])->select('branch_id'))
            ->orderBy('name')->get();
        // Nama perusahaan ikut ditampilkan kalau tagihan datang dari lebih dari satu perusahaan.
        $tabs = $branches->mapWithKeys(fn ($b) => [$b->id => ($branches->pluck('company_id')->unique()->count() > 1 ? $b->company?->name.' · ' : '').$b->name])->all();
        $active = array_key_exists((string) $request->query('cabang'), $tabs) ? $request->query('cabang') : array_key_first($tabs);
        $history = $request->query('lihat') === 'riwayat';
        $payable = $active ? PayerInvoices::payable($user, $active) : collect();

        return view('bills.index', [
            'user' => $user,
            'tabs' => $tabs,
            'active' => $active,
            'history' => $history && $active
                ? PayerInvoices::query($user)->where('branch_id', $active)->where('status', Invoice::STATUS_PAID)->latest('paid_at')->paginate(15)->withQueryString()
                : null,
            'groups' => $payable->groupBy(fn ($i) => $i->category?->name ?? 'Tagihan lainnya'),
            // Tagihan yang sedang menunggu pembayaran (di menu ini atau lewat link bayar) tidak bisa dicentang.
            'busy' => InvoicePayment::whereIn('invoice_id', $payable->modelKeys())->where('status', InvoicePayment::STATUS_PENDING)->where('expires_at', '>', now())->pluck('invoice_id')
                ->merge(InvoiceCheckout::open()->with('items:id,checkout_id,invoice_id')->where('branch_id', $active)->get()->flatMap->items->pluck('invoice_id'))->flip(),
            'open' => InvoiceCheckout::open()->whereNotNull('payment_url')->where('user_id', $user->id)->where('branch_id', $active)->latest()->get(),
            'online' => $active && app(AppAccess::class)->branchActive($active, 'invoices'),
        ]);
    }

    public function pay(Request $request, InvoiceCheckoutService $checkouts): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'uuid'],
            'invoices' => ['required', 'array', 'max:60'],
            'invoices.*' => ['uuid'],
        ], ['invoices.required' => 'Centang minimal satu tagihan dulu ya.']);

        try {
            $checkout = $checkouts->create($request->user(), $data['branch_id'], $data['invoices']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($checkout->payment_url);
    }

    /** returnUrl Duitku: langsung cek status, lalu kembali ke daftar tagihan. */
    public function returned(Request $request, InvoiceCheckoutService $checkouts, InvoiceCheckout $checkout): RedirectResponse
    {
        abort_unless($checkout->user_id === $request->user()->id, 404);
        $back = redirect()->route('bills.index', ['cabang' => $checkout->branch_id]);

        return $checkouts->refresh($checkout)
            ? $back->with('success', 'Pembayaran berhasil, terima kasih! Tagihannya sudah lunas dan bukti bayar dikirim ke email Anda.')
            : $back->with('info', 'Pembayaran belum kami terima. Kalau sudah membayar, tunggu sebentar lalu tekan "Cek lagi" di bawah.')->with('recheck', route('bills.return', $checkout));
    }
}
