<?php

namespace App\Http\Controllers\Page\Registration;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageSubmission;
use App\Models\PageType;
use App\Services\Contact\ContactBookService;
use App\Support\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Tab Pendaftar pada page Registrasi: daftar jawaban form, bisa dicari, diunduh, dan dihapus satuan. */
class RegistrationEntryController extends Controller
{
    use ResolvesPage;

    private const MAX_EXPORT = 5000;

    public function index(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_REGISTRATION);

        return view('page.registration.entries', [
            'page' => $model->load('fields'),
            'entries' => $this->filtered($model, $request)->paginate(25)->withQueryString(),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function export(Request $request, string $page, ContactBookService $book): StreamedResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_REGISTRATION)->load('fields');
        $labels = $model->fields->pluck('label', 'id');

        $rows = [['Referensi', 'Waktu Daftar', ...$labels->values()->all()]];

        foreach ($this->filtered($model, $request)->limit(self::MAX_EXPORT)->get() as $entry) {
            $answers = $entry->answers->keyBy('page_form_field_id');
            $rows[] = [
                $entry->reference,
                $entry->created_at?->format('Y-m-d H:i') ?? '',
                ...$labels->keys()->map(fn ($id) => $this->plain($answers->get($id)))->all(),
            ];
        }

        return $book->template('pendaftar-'.$model->slug.'.xlsx', $rows);
    }

    public function destroy(Request $request, string $page, string $submission): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_REGISTRATION, Menu::ABILITY_MANAGE);
        $entry = PageSubmission::where('page_category_id', $model->getKey())->findOrFail($submission);
        $entry->delete();

        return back()->with('success', "Pendaftar {$entry->reference} dihapus.");
    }

    /** Pendaftar page ini sesuai kotak cari; dicocokkan ke isi jawaban, bukan cuma nomor referensi. */
    private function filtered(PageCategory $page, Request $request): Builder
    {
        $search = trim((string) $request->query('search'));

        return PageSubmission::where('page_category_id', $page->getKey())
            ->with('answers')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('answers', fn ($a) => $a->where('value', 'like', "%{$search}%"))))
            ->latest();
    }

    /** Isi satu jawaban sebagai teks; jawaban berupa berkas diwakili nama berkasnya. */
    private function plain(mixed $answer): string
    {
        return (string) ($answer?->file_name ?? $answer?->value ?? '');
    }
}
