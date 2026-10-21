<?php

namespace App\Http\Controllers\Page\Form;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Http\Requests\Page\Form\PageFormSyncRequest;
use App\Models\PageCategory;
use App\Models\PageFormField;
use App\Models\PageType;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PageFormFieldController extends Controller
{
    use ResolvesPage;

    public function index(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);

        return view('page.form.builder', [
            'page' => $model->load('fields'),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    /** Simpan semua baris sekaligus: baris lama diperbarui, baris baru dibuat, baris yang dihapus ikut dihapus. */
    public function sync(PageFormSyncRequest $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);

        DB::transaction(function () use ($model, $request) {
            // Kunci page supaya 2 tab yang menyimpan bersamaan tidak saling menimpa urutan.
            PageCategory::whereKey($model->getKey())->lockForUpdate()->first();
            $existing = $model->fields()->get()->keyBy('id');
            $kept = [];

            foreach ($request->validated('fields') as $position => $row) {
                // id hanya dipakai kalau memang field milik page ini; selain itu dianggap baris baru.
                $field = $existing->get($row['id'] ?? '') ?? new PageFormField();
                $field->fill(collect($row)->except('id')->all());
                $field->page_category_id = $model->getKey();
                $field->sort_order = $position;
                $field->save();
                $kept[] = $field->getKey();
            }

            $model->fields()->whereKeyNot($kept)->delete();
        });

        return back()->with('success', 'Form pembeli sudah disimpan.');
    }
}
