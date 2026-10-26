<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Models\CatalogCategory;
use App\Models\CatalogOrder;
use App\Models\CatalogProduct;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Rules\Turnstile;
use App\Services\Catalog\CatalogOrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class PublicPageController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $page = $this->find($slug);
        $data = ['page' => $page, 'open' => $this->isOpen($page)];

        if ($data['open'] && $page->isTemplate(PageType::TEMPLATE_CATALOG)) {
            $categories = CatalogCategory::where('page_id', $page->getKey())->active()->orderBy('sort_order')->get(['id', 'name', 'slug']);
            $active = $categories->firstWhere('slug', $request->query('kategori'));

            $data += [
                'categories' => $categories,
                'activeCategory' => $active,
                'products' => $this->products($page)
                    ->when($active, fn ($q) => $q->where('category_id', $active->getKey()))
                    ->paginate(24)
                    ->withQueryString(),
            ];
        }

        if ($data['open'] && $page->isTemplate(PageType::TEMPLATE_LANDING)) {
            $data['sections'] = \App\Models\LandingSection::where('page_id', $page->getKey())->active()->orderBy('sort_order')->get();
        }

        if ($data['open'] && $page->isTemplate(PageType::TEMPLATE_TICKET)) {
            $data['ticketCategories'] = $page->ticketCategories()->active()->get();
        }

        if ($data['open'] && $page->isTemplate(PageType::TEMPLATE_QUIZ)) {
            $data['questionCount'] = $page->quizQuestions()->active()->count();
        }

        return view('page.public.show', $data);
    }

    public function product(string $slug, string $product): View
    {
        $page = $this->find($slug);
        abort_unless($this->isOpen($page) && $page->isTemplate(PageType::TEMPLATE_CATALOG), 404);

        return view('page.public.catalog-product', [
            'page' => $page,
            'product' => $this->products($page)->where('slug', $product)->firstOrFail(),
        ]);
    }

    public function checkout(string $slug): View
    {
        $page = $this->find($slug);
        abort_unless($this->isOpen($page) && $page->isTemplate(PageType::TEMPLATE_CATALOG), 404);

        return view('page.public.catalog-checkout', ['page' => $page, 'paymentMethods' => CatalogOrderService::paymentMethods($page)]);
    }

    public function placeOrder(Request $request, CatalogOrderService $orders, string $slug): RedirectResponse
    {
        $page = $this->find($slug);
        abort_unless($this->isOpen($page) && $page->isTemplate(PageType::TEMPLATE_CATALOG), 404);
        $methods = CatalogOrderService::paymentMethods($page);

        $data = $request->validate([
            'website' => ['nullable', 'max:0'],
            'cf-turnstile-response' => [new Turnstile()],
            'items' => ['required', 'array', 'min:1', 'max:'.CatalogOrderService::MAX_LINES],
            'items.*' => ['integer', 'min:1', 'max:'.CatalogOrderService::MAX_QTY],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'email' => ['nullable', 'required_if:payment,'.CatalogOrder::PAYMENT_ONLINE, 'email', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:1000'],
            // Hanya metode yang diaktifkan owner; tanpa metode = alur lama (dikonfirmasi penjual).
            'payment' => $methods ? ['required', Rule::in($methods)] : ['prohibited'],
        ], [
            'items.required' => 'Keranjang masih kosong.',
            'payment.required' => 'Pilih metode pembayaran dulu ya.',
            'email.required_if' => 'Email wajib diisi untuk bayar online, tagihan dikirim ke sini.',
            'phone.regex' => 'Nomor WhatsApp belum benar.',
            'website.max' => 'Pesanan gagal dikirim.',
        ], ['name' => 'nama', 'phone' => 'nomor WhatsApp', 'address' => 'alamat', 'note' => 'catatan']);

        // Kunci keranjang = id produk; yang bukan uuid langsung dibuang.
        $cart = collect($data['items'])->filter(fn ($qty, $id) => is_string($id) && preg_match('/^[0-9a-f-]{36}$/i', $id))->map(fn ($qty) => (int) $qty)->all();

        $order = $orders->place($page, $cart, [
            'name' => trim($data['name']),
            'phone' => $data['phone'],
            'email' => isset($data['email']) ? strtolower(trim($data['email'])) : null,
            'address' => $data['address'],
            'note' => $data['note'] ?? null,
        ], $data['payment'] ?? null, $request->ip(), $request->userAgent());

        if ($order->payment_type === CatalogOrder::PAYMENT_ONLINE) {
            try {
                return redirect()->away($orders->startPayment($order, $page));
            } catch (RuntimeException $e) {
                return back()->withInput()->withErrors(['cart' => $e->getMessage()]);
            }
        }

        return redirect()->route('page.order', [$page->slug, $order->access_token]);
    }

    /** Halaman pesanan pembeli: status, rincian, rekening tujuan / tombol bayar. Juga returnUrl dari Duitku. */
    public function order(string $slug, string $token): View
    {
        $page = $this->find($slug);
        abort_unless($page->isTemplate(PageType::TEMPLATE_CATALOG), 404);

        return view('page.public.catalog-order', [
            'page' => $page,
            'order' => CatalogOrder::where('page_id', $page->getKey())->where('access_token', $token)->with('items')->firstOrFail(),
        ]);
    }

    private function products(PageCategory $page)
    {
        return CatalogProduct::where('page_id', $page->getKey())
            ->active()
            ->with(['images' => fn ($q) => $q->active()->orderBy('sort_order')])
            ->orderBy('sort_order');
    }

    private function find(string $slug): PageCategory
    {
        return PageCategory::with(['type', 'fields', 'branch.company:id,name,logo'])->where('slug', $slug)->firstOrFail();
    }

    private function isOpen(PageCategory $page): bool
    {
        return $page->isOpen() && (bool) $page->branch?->isActive();
    }
}
