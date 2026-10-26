<?php

namespace App\Services\Page;

use App\Models\PageCategory;
use App\Models\UserBranch;
use App\Services\Media\ImageUploader;
use App\Services\Media\QrCodeGenerator;
use App\Services\Subscription\PlanLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PageCategoryService
{
    public function __construct(private readonly QrCodeGenerator $qrcodes, private readonly ImageUploader $images, private readonly PlanLimits $limits)
    {
    }

    /** $branch sudah dipastikan milik user oleh request. QR dibuat ulang hanya kalau link berubah. */
    public function save(PageCategory $page, UserBranch $branch, array $data): PageCategory
    {
        $page->fill($data);
        $page->branch_id = $branch->getKey();
        $page->{$page->getKeyName()} ??= $page->newUniqueId();

        $slug = filled($data['slug'] ?? null) ? Str::slug($data['slug']) : ($page->slug ?? $this->uniqueSlug($page->name));
        $linkChanged = ! $page->exists || $slug !== $page->slug;
        $page->slug = $slug;

        // File baru dicatat supaya bisa dibuang kalau simpan gagal; file lama dihapus setelah commit.
        $created = [];
        $replaced = $this->images($page, $data, $created);

        if ($linkChanged) {
            $replaced[] = $page->qrcodePath();
            $page->directory_qrcode = 'pages/'.$branch->getKey().'/qrcodes';
            $page->qrcode = $this->qrcodes->store(route('page.show', $slug), $page->directory_qrcode);
            $created[] = $page->qrcodePath();
        }

        // Batas page per kategori dicek saat page baru dibuat atau dipindah kategori/branch, dalam transaksi yang sama.
        $countsTowardLimit = ! $page->exists || $page->isDirty(['page_type_id', 'branch_id']);

        try {
            DB::transaction(function () use ($page, $countsTowardLimit) {
                if ($countsTowardLimit) {
                    $this->limits->assertPage($page->branch_id, $page->page_type_id, $page->exists ? $page->getKey() : null);
                }

                $page->save();
            });
        } catch (Throwable $e) {
            array_map(fn ($path) => $this->images->delete($path), $created);

            throw $e;
        }

        DB::afterCommit(fn () => array_map(fn ($path) => $this->images->delete($path), array_filter($replaced)));

        return $page;
    }

    /**
     * Banner header (lebar maks 1920) & gambar share (maks 1200), dikompres ke WebP.
     * Mengembalikan path lama yang diganti/dihapus; path baru masuk ke $created.
     */
    private function images(PageCategory $page, array $data, array &$created): array
    {
        $replaced = [];

        foreach (['banner' => 1920, 'og_image' => 1200] as $column => $size) {
            $file = $data[$column] ?? null;

            if (! $file && empty($data['remove_'.$column])) {
                continue;
            }

            $replaced[] = $page->getOriginal($column);
            $page->{$column} = $file ? $created[] = $this->images->store($file, 'pages/'.$page->getKey(), $column, $size) : null;
        }

        return $replaced;
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
        Storage::disk('public')->deleteDirectory('pages/'.$page->getKey());
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
