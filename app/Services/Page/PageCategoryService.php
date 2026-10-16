<?php

namespace App\Services\Page;

use App\Models\PageCategory;
use App\Models\UserBranch;
use App\Services\Media\QrCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PageCategoryService
{
    public function __construct(private readonly QrCodeGenerator $qrcodes)
    {
    }

    /** $branch sudah dipastikan milik user oleh request. QR dibuat ulang hanya kalau link berubah. */
    public function save(PageCategory $page, UserBranch $branch, array $data): PageCategory
    {
        $page->fill($data);
        $page->branch_id = $branch->getKey();

        $slug = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : ($page->slug ?? $this->uniqueSlug($page->name));
        $linkChanged = ! $page->exists || $slug !== $page->slug;
        $page->slug = $slug;

        if (! $linkChanged) {
            $page->save();

            return $page;
        }

        $oldQr = $page->qrcodePath();
        $page->directory_qrcode = 'pages/'.$branch->getKey().'/qrcodes';
        $page->qrcode = $this->qrcodes->store(route('page.show', $slug), $page->directory_qrcode);

        try {
            $page->save();
        } catch (Throwable $e) {
            $this->qrcodes->delete($page->qrcodePath());

            throw $e;
        }

        DB::afterCommit(fn () => $this->qrcodes->delete($oldQr));

        return $page;
    }

    /** @throws RuntimeException kalau page sudah punya transaksi tiket (catatan uang tidak boleh hilang). */
    public function delete(PageCategory $page): void
    {
        if ($page->ticketOrders()->exists()) {
            throw new RuntimeException("Page {$page->name} sudah punya pesanan tiket, jadi tidak bisa dihapus. Ubah statusnya menjadi Tidak Aktif saja.");
        }

        $qr = $page->qrcodePath();
        $page->delete();
        $this->qrcodes->delete($qr);
        Storage::disk(PageSubmissionService::DISK)->deleteDirectory(PageSubmissionService::directory($page));
        Storage::disk('public')->deleteDirectory('catalog/'.$page->getKey());
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'page';
        $slug = $base;

        while (PageCategory::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(5));
        }

        return $slug;
    }
}
