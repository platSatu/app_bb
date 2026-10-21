<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Services\Media\ImageUploader;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Background & warna e-ticket per event (opsional). Disimpan di settings.ticket. */
class TicketDesignController extends Controller
{
    use ResolvesPage;

    public function edit(Request $request, string $page): View
    {
        return view('page.ticket.design', [
            'page' => $this->templatePage($request, $page, PageType::TEMPLATE_TICKET),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(Request $request, ImageUploader $images, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);
        $data = $request->validate([
            'background' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove' => ['boolean'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], ['background.max' => 'Ukuran gambar maksimal 5 MB.', 'color.regex' => 'Pilih warna dari pemilih warna.']);

        $old = $model->setting('ticket.background');
        $new = $request->file('background')
            ? $images->store($request->file('background'), 'tickets/'.$model->getKey(), 'background', 1600)
            : ($request->boolean('remove') ? null : $old);

        DB::transaction(function () use ($model, $new, $data) {
            $locked = PageCategory::whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $locked->settings = array_replace_recursive($locked->settings ?? [], ['ticket' => ['background' => $new, 'color' => $data['color'] ?? null]]);
            $locked->save();
        });

        if ($old && $old !== $new) {
            $images->delete($old);
        }

        return back()->with('success', 'Desain e-ticket berhasil disimpan.');
    }
}
