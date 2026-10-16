<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\TicketCategory;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
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
            'categories.*.name.required' => 'Nama kategori wajib diisi.',
            'categories.*.quota.min' => 'Kuota minimal 1.',
        ])['categories']);

        DB::transaction(function () use ($model, $rows) {
            PageCategory::whereKey($model->getKey())->lockForUpdate()->first();
            $existing = TicketCategory::where('page_id', $model->getKey())->lockForUpdate()->get()->keyBy('id');
            $errors = [];
            $kept = [];

            foreach ($rows as $position => $row) {
                $category = $existing->get($row['id'] ?? '') ?? new TicketCategory();
                $label = 'Kategori '.$row['name'];

                if ($category->exists && $row['quota'] < $category->sold + $category->reserved) {
                    $errors["categories.{$position}.quota"] = "{$label}: kuota tidak boleh kurang dari ".($category->sold + $category->reserved).' (sudah terjual/ditahan).';
                }

                if (filled($row['sale_starts_at'] ?? null) && filled($row['sale_ends_at'] ?? null) && $row['sale_ends_at'] < $row['sale_starts_at']) {
                    $errors["categories.{$position}.sale_ends_at"] = "{$label}: waktu tutup penjualan tidak boleh sebelum waktu buka.";
                }

                $category->fill($row);
                $category->page_id = $model->getKey();
                $category->sort_order = $position;
                $category->save();
                $kept[] = $category->getKey();
            }

            foreach ($existing->except($kept) as $removed) {
                if ($removed->sold > 0 || $removed->reserved > 0 || $removed->orderItems()->exists()) {
                    $errors['categories'] = "Kategori {$removed->name} sudah punya pesanan, jadi tidak bisa dihapus. Ubah statusnya menjadi Tidak Aktif saja.";
                } else {
                    $removed->delete();
                }
            }

            if ($errors) {
                throw ValidationException::withMessages($errors);
            }
        });

        return back()->with('success', 'Kategori tiket berhasil disimpan.');
    }

    /** Riwayat pembelian satu kategori (hanya yang lunas). */
    public function sales(Request $request, string $page, string $category): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);
        $item = TicketCategory::where('page_id', $model->getKey())->findOrFail($category);
        $search = trim((string) $request->query('search'));

        $sales = TicketOrderItem::query()
            ->where('category_id', $item->getKey())
            ->whereHas('order', fn ($q) => $q->where('status', TicketOrder::STATUS_PAID)
                ->when($search !== '', fn ($w) => $w->where(fn ($s) => $s
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"))))
            ->with('order:id,reference,name,email,phone,paid_at')
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('page.ticket.sales', [
            'page' => $model,
            'category' => $item,
            'sales' => $sales,
            'routes' => $this->routes($request),
        ]);
    }
}
