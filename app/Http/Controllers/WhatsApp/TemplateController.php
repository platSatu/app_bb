<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Settings > Template: pesan siap pakai dengan header, footer, link & lampiran opsional. */
class TemplateController extends Controller
{
    use InWhatsAppArea;

    private const MAX_FILE_KB = 16 * 1024;
    private const MIMES = 'jpg,jpeg,png,webp,gif,mp4,3gp,mp3,ogg,m4a,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip';

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.templates.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'templates' => WaTemplate::where('branch_id', $branch->id)->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.templates.form', ['template' => new WaTemplate(['is_active' => true]), 'branchId' => $branch->id]);
    }

    public function edit(Request $request, string $template): View
    {
        $model = $this->template($request, $template);

        return view('whatsapp.templates.form', ['template' => $model, 'branchId' => $model->branch_id]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branchId = $request->validate(['branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))]])['branch_id'];
        $template = new WaTemplate();
        $template->forceFill(['branch_id' => $branchId]);
        $this->save($request, $template);

        return redirect()->route('whatsapp.templates.index', ['branch' => $branchId])->with('success', 'Template disimpan.');
    }

    public function update(Request $request, string $template): RedirectResponse
    {
        $model = $this->template($request, $template);
        $this->save($request, $model);

        return redirect()->route('whatsapp.templates.index', ['branch' => $model->branch_id])->with('success', 'Template diperbarui.');
    }

    public function destroy(Request $request, string $template): RedirectResponse
    {
        $this->template($request, $template)->delete();

        return back()->with('success', 'Template dihapus.');
    }

    public function file(Request $request, string $template): StreamedResponse
    {
        $model = $this->template($request, $template);
        abort_unless($model->file_path && Storage::disk(WaTemplate::DISK)->exists($model->file_path), 404);

        // Hanya gambar yang dibuka di browser; file lain selalu diunduh.
        return Storage::disk(WaTemplate::DISK)->response($model->file_path, $model->file_name, [
            'Content-Type' => $model->file_mime,
            'X-Content-Type-Options' => 'nosniff',
        ], $model->fileKind() === 'image' ? 'inline' : 'attachment');
    }

    private function template(Request $request, string $id): WaTemplate
    {
        return WaTemplate::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->findOrFail($id);
    }

    private function save(Request $request, WaTemplate $template): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'header' => ['nullable', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:1024'],
            'footer' => ['nullable', 'string', 'max:60'],
            'link' => ['nullable', 'url:http,https', 'max:500'],
            'file' => ['nullable', 'file', 'mimes:'.self::MIMES, 'max:'.self::MAX_FILE_KB],
        ], [
            'link.url' => 'Link harus diawali http:// atau https://.',
            'file.mimes' => 'Jenis file belum didukung. Gunakan gambar, video, audio, PDF, atau dokumen Office.',
            'file.max' => 'Ukuran lampiran maksimal 16 MB.',
        ], ['name' => 'nama template', 'body' => 'isi pesan']);

        $old = $template->file_path;
        $template->fill(['is_active' => $request->boolean('is_active')] + collect($data)->except('file')->all());

        if ($file = $request->file('file')) {
            $template->forceFill([
                'file_path' => $file->store('wa-templates/'.$template->branch_id, WaTemplate::DISK),
                'file_name' => mb_substr($file->getClientOriginalName(), 0, 191),
                'file_mime' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        } elseif ($request->boolean('remove_file')) {
            $template->forceFill(['file_path' => null, 'file_name' => null, 'file_mime' => null, 'file_size' => null]);
        }

        $template->save();

        if ($old && $old !== $template->file_path) {
            Storage::disk(WaTemplate::DISK)->delete($old);
        }
    }
}
