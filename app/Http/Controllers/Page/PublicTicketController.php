<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Rules\Turnstile;
use App\Services\Media\QrCodeGenerator;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/** Pembelian tiket & halaman e-ticket publik. Semua link e-ticket dikunci token acak, tidak bisa ditebak. */
class PublicTicketController extends Controller
{
    public function checkout(Request $request, TicketOrderService $orders, string $slug): RedirectResponse
    {
        $page = $this->openPage($slug);

        $fieldRules = $page->fields->mapWithKeys(fn ($field) => ['fields.'.$field->getKey() => $field->rules()])->all();
        $fieldNames = $page->fields->mapWithKeys(fn ($field) => ['fields.'.$field->getKey() => $field->label])->all();

        $data = $request->validate([
            ...$fieldRules,
            'terms' => filled($page->setting('ticket.terms')) ? ['accepted'] : ['nullable'],
            'website' => ['nullable', 'max:0'],
            'cf-turnstile-response' => [new Turnstile()],
            'tickets' => ['required', 'array', 'max:30'],
            'tickets.*' => ['nullable', 'integer', 'min:0', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
        ], [
            'tickets.required' => 'Pilih minimal 1 tiket dulu ya.',
            'terms.accepted' => 'Centang persetujuan Syarat & Ketentuan dulu ya.',
            'phone.regex' => 'Nomor WhatsApp belum benar.',
            'email.required' => 'Email wajib diisi, e-ticket akan dikirim ke sini.',
            'website.max' => 'Pesanan gagal dikirim.',
        ], ['name' => 'nama', 'phone' => 'nomor WhatsApp', ...$fieldNames]);

        $quantities = collect($data['tickets'])
            ->filter(fn ($qty, $id) => is_string($id) && preg_match('/^[0-9a-f-]{36}$/i', $id))
            ->map(fn ($qty) => (int) $qty)
            ->all();

        try {
            $order = $orders->create($page, $quantities, [
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'phone' => $data['phone'],
            ], $data['fields'] ?? [], $request->ip(), $request->userAgent());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $order->total > 0
            ? redirect()->away($order->payment_url)
            : redirect()->route('eticket.order', [$page->slug, $order->access_token]);
    }

    /** Halaman pesanan untuk pembeli: status bayar + daftar e-ticket. Ini juga returnUrl dari Duitku. */
    public function order(string $slug, string $token): View
    {
        $page = $this->page($slug);
        $order = TicketOrder::where('page_id', $page->getKey())->where('access_token', $token)->with(['items', 'tickets'])->firstOrFail();

        return view('page.public.ticket-order', compact('page', 'order'));
    }

    public function show(QrCodeGenerator $qr, string $slug, string $number, string $token): View
    {
        [$page, $ticket] = $this->ticket($slug, $number, $token);

        return view('page.public.eticket', [
            'page' => $page,
            'ticket' => $ticket,
            'qr' => $qr->dataUri($ticket->token),
            'background' => $page->setting('ticket.background') ? Storage::disk('public')->url($page->setting('ticket.background')) : null,
        ]);
    }

    public function pdf(QrCodeGenerator $qr, string $slug, string $number, string $token): Response
    {
        [$page, $ticket] = $this->ticket($slug, $number, $token);
        abort_unless(class_exists(\Barryvdh\DomPDF\Facade\Pdf::class), 503, 'Download PDF belum aktif. Silakan screenshot e-ticket dulu ya.');

        // dompdf butuh gambar dalam bentuk data URI (bukan URL).
        $path = $page->setting('ticket.background');
        // dompdf belum tentu bisa WebP, jadi diubah ke JPEG dulu.
        $background = $path && Storage::disk('public')->exists($path)
            ? 'data:image/jpeg;base64,'.base64_encode((string) (new ImageManager(new Driver()))->read(Storage::disk('public')->path($path))->toJpeg(80))
            : null;

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('page.public.eticket-pdf', [
            'page' => $page,
            'ticket' => $ticket,
            'qr' => $qr->dataUri($ticket->token),
            'background' => $background,
        ])->setPaper('a5')->download('e-ticket-'.$ticket->number.'.pdf');
    }

    /** @return array{0: PageCategory, 1: Ticket} */
    private function ticket(string $slug, string $number, string $token): array
    {
        $page = $this->page($slug);
        $ticket = Ticket::where('page_id', $page->getKey())->where('number', $number)->where('token', $token)->with('order:id,reference,access_token,page_id')->firstOrFail();

        return [$page, $ticket];
    }

    private function page(string $slug): PageCategory
    {
        $page = PageCategory::with(['type', 'fields', 'branch.company:id,name,logo'])->where('slug', $slug)->firstOrFail();
        abort_unless($page->isTemplate(PageType::TEMPLATE_TICKET), 404);

        return $page;
    }

    private function openPage(string $slug): PageCategory
    {
        $page = $this->page($slug);
        abort_unless($page->isOpen() && $page->branch?->isActive(), 404);

        return $page;
    }
}
