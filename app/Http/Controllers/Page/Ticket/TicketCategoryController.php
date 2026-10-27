<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\TicketCategory;
use App\Models\TicketCategoryChange;
use App\Models\TicketStockLog;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TicketCategoryController extends Controller
{
    use ResolvesPage;

    private const MAX = 30;

    /** Ringkasan penjualan + form atur kategori + riwayat perubahan dalam satu halaman. */
    public function index(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);

        return view('page.ticket.categories', [
            'page' => $model,
            'categories' => $model->ticketCategories()->get(),
            'freeEvent' => $model->isFreeEvent(),
            'freeLimit' => $model->isFreeEvent() ? $model->freeTicketLimit() : null,
            'history' => TicketCategoryChange::where('page_id', $model->getKey())->with('actor:id,name')->latest('created_at')->limit(30)->get(),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    /**
     * Simpan semua kategori sekaligus. Aturannya (semua dicek di server, apa pun yang dikirim browser):
     *  1. Kategori yang sudah ada pembelinya: nama & harga dikunci; kolom lain boleh diubah dan dicatat di riwayat.
     *  2. Event gratis: semua harga harus Rp0, dan TOTAL kuota semua kategori tidak boleh melewati batas superadmin.
     *  3. Event berbayar: harga wajib diisi (kategori gratis lama tetap boleh dipertahankan).
     *  4. Jumlah dijual tidak boleh di bawah yang sudah terjual / sedang ditahan.
     * Semua berjalan dalam 1 transaksi dengan page & kategori dikunci: ada satu saja yang salah, tidak ada yang tersimpan.
     */
    public function sync(Request $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);

        $rows = array_values($request->validate([
            'categories' => ['present', 'array', 'max:'.self::MAX],
            'categories.*.id' => ['nullable', 'uuid'],
            'categories.*.name' => ['required', 'string', 'max:100'],
            'categories.*.description' => ['nullable', 'string', 'max:255'],
            'categories.*.price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'categories.*.quota' => ['required', 'integer', 'min:1', 'max:1000000'],
            'categories.*.max_per_order' => ['required', 'integer', 'min:1', 'max:100'],
            'categories.*.sale_starts_at' => ['nullable', 'date'],
            'categories.*.sale_ends_at' => ['nullable', 'date'],
            'categories.*.status' => ['required', Rule::in(array_keys(TicketCategory::STATUS_LABELS))],
        ], [
            'categories.*.name.required' => 'Nama kategori tiket belum diisi.',
            'categories.*.price.required' => 'Harga tiket belum diisi.',
            'categories.*.quota.required' => 'Jumlah tiket yang dijual belum diisi.',
            'categories.*.quota.min' => 'Jumlah tiket yang dijual minimal 1.',
            'categories.*.max_per_order.required' => 'Batas pembelian per orang belum diisi.',
            'categories.*.max_per_order.max' => 'Batas pembelian per orang maksimal 100 tiket.',
        ])['categories']);

        $actorId = $request->user()->getKey();

        DB::transaction(function () use ($model, $rows, $actorId) {
            $locked = PageCategory::whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $existing = TicketCategory::where('page_id', $model->getKey())->lockForUpdate()->get()->keyBy('id');
            $freeEvent = $locked->isFreeEvent();
            $errors = [];
            $kept = [];

            foreach ($rows as $position => $row) {
                $category = $existing->get($row['id'] ?? '') ?? new TicketCategory();
                $label = 'Kategori '.$row['name'];
                $price = (int) $row['price'];
                $hasBuyers = $category->exists && $category->sold + $category->reserved > 0;

                if ($hasBuyers && ($row['name'] !== $category->name || $price !== $category->price)) {
                    $errors["categories.{$position}.price"] = "{$category->name}: sudah ada pembelinya, jadi nama dan harganya tidak bisa diubah. Kolom lain tetap boleh diubah.";
                }

                if ($freeEvent && $price !== 0) {
                    $errors["categories.{$position}.price"] = "{$label}: ini event gratis, jadi harga tiket harus Rp0.";
                } elseif (! $freeEvent && $price === 0 && ! ($category->exists && $category->price === 0)) {
                    $errors["categories.{$position}.price"] = "{$label}: isi harga tiketnya. Kalau tiketnya gratis, pilih jenis Event gratis di Informasi Page (selama belum ada pesanan).";
                }

                if ($category->exists && $row['quota'] < $category->sold + $category->reserved) {
                    $errors["categories.{$position}.quota"] = "{$label}: jumlah tiket minimal ".($category->sold + $category->reserved).', karena sebanyak itu sudah terjual atau sedang menunggu pembayaran.';
                }

                if (filled($row['sale_starts_at'] ?? null) && filled($row['sale_ends_at'] ?? null) && $row['sale_ends_at'] < $row['sale_starts_at']) {
                    $errors["categories.{$position}.sale_ends_at"] = "{$label}: waktu berhenti dijual harus setelah waktu mulai dijual.";
                }

                $before = $category->exists ? TicketStockLog::snapshot($category) : ['quota' => 0, 'reserved' => 0, 'sold' => 0];
                $beforeValues = $category->exists ? TicketCategoryChange::displayValues($category) : null;
                $category->fill($row);
                $category->page_id = $model->getKey();
                $category->sort_order = $position;
                $category->save();
                TicketStockLog::record($category, $before, TicketStockLog::ACTION_QUOTA, null, $actorId, $before['quota'] === 0 ? 'Kategori baru' : null);

                if ($beforeValues) {
                    TicketCategoryChange::record($category, $beforeValues, $actorId);
                }

                $kept[] = $category->getKey();
            }

            foreach ($existing->except($kept) as $removed) {
                if ($removed->sold > 0 || $removed->reserved > 0 || $removed->orderItems()->exists()) {
                    $errors['categories'] = "Kategori {$removed->name} sudah ada yang membeli, jadi tidak bisa dihapus. Ubah statusnya menjadi Tidak Aktif supaya tidak dijual lagi.";
                } else {
                    $removed->delete();
                }
            }

            // Batas event gratis dihitung dari SEMUA kategori (aktif maupun tidak) yang tersimpan, bukan per kategori.
            $limit = $freeEvent ? $locked->freeTicketLimit() : null;
            $total = $limit === null ? 0 : (int) TicketCategory::where('page_id', $model->getKey())->sum('quota');

            if ($limit !== null && $total > $limit) {
                $errors['categories'] = 'Total jumlah tiket semua kategori '.number_format($total, 0, ',', '.').', melebihi batas event gratis '.number_format($limit, 0, ',', '.').' tiket. Kurangi jumlahnya, atau buat event berbayar kalau butuh lebih banyak.';
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        });

        return back()->with('success', 'Kategori tiket sudah disimpan.');
    }
}
