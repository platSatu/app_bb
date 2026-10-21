<?php

namespace App\Http\Controllers\Page\Form;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageSubmissionAnswer;
use App\Services\Page\PageSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Unduh file jawaban Form Pembeli (disk privat), hanya untuk pemilik page. */
class PageSubmissionController extends Controller
{
    use ResolvesPage;

    public function file(Request $request, string $page, string $submission, string $answer): StreamedResponse
    {
        $item = PageSubmissionAnswer::query()
            ->where('page_submission_id', $this->page($request, $page)->submissions()->findOrFail($submission)->getKey())
            ->whereNotNull('file_path')
            ->findOrFail($answer);

        abort_unless(Storage::disk(PageSubmissionService::DISK)->exists($item->file_path), 404);

        return Storage::disk(PageSubmissionService::DISK)->download($item->file_path, $item->file_name);
    }
}
