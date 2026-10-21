<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\TicketCategory;
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

    /** Ringkasan penjualan + form atur kategori dalam satu halaman. */
    public function index(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);

        return view('page.ticket.categories', [
            'page' => $model,
            'categories' => $model->ticketCategories()->get(),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

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
            'categories.*.price.required' => 'Harga tiket belum diisi. Isi 0 kalau tiketnya gratis.',
            'categories.*.quota.required' => 'Jumlah tiket yang dijual belum diisi.',
            'categories.*.quota.min' => 'Jumlah tiket yang dijual minimal 1.',
            'categories.*.max_per_order.required' => 'Batas pembelian per orang belum diisi.',
            'categories.*.max_per_order.max' => 'Batas pembelian per orang maksimal 100 tiket.',
        ])['categories']);

        $actorId = $request->user()->getKey();

        DB::transaction(function () use ($model, $rows, $actorId) {
            PageCategory::whereKey($model->getKey())->lockForUpdate()->first();
            $existing = TicketCategory::where('page_id', $model->getKey())->lockForUpdate()->get()->keyBy('id');
            $errors = [];
            $kept = [];

            foreach ($rows as $position => $row) {
                $category = $existing->get($row['id'] ?? '') ?? new TicketCategory();
                $label = 'Kategori '.$row['name'];

                if ($category->exists && $row['quota'] < $category->sold + $category->reserved) {
                    $errors["categories.{$position}.quota"] = "{$label}: jumlah tiket minimal ".($category->sold + $category->reserved).', karena sebanyak itu sudah terjual atau sedang menunggu pembayaran.';
                }

                if (filled($row['sale_starts_at'] ?? null) && filled($row['sale_ends_at'] ?? null) && $row['sale_ends_at'] < $row['sale_starts_at']) {
                    $errors["categories.{$position}.sale_ends_at"] = "{$label}: waktu berhenti dijual harus setelah waktu mulai dijual.";
                }

                $before = $category->exists ? TicketStockLog::snapshot($category) : ['quota' => 0, 'reserved' => 0, 'sold' => 0];
                $category->fill($row);
                $category->page_id = $model->getKey();
                $category->sort_order = $position;
                $category->save();
                TicketStockLog::record($category, $before, TicketStockLog::ACTION_QUOTA, null, $actorId, $before['quota'] === 0 ? 'Kategori baru' : null);
                $kept[] = $category->getKey();
            }

            foreach ($existing->except($kept) as $removed) {
                if ($removed->sold > 0 || $removed->reserved > 0 || $removed->orderItems()->exists()) {
                    $errors['categories'] = "Kategori {$removed->name} sudah ada yang membeli, jadi tidak bisa dihapus. Ubah statusnya menjadi Tidak Aktif supaya tidak dijual lagi.";
                } else {
                    $removed->delete();
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        });

        return back()->with('success', 'Kategori tiket sudah disimpan.');
    }
}
