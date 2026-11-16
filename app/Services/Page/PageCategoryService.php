<?php

namespace App\Services\Page;

use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\TicketSetting;
use App\Models\UserBranch;
use App\Services\Media\ImageUploader;
use App\Services\Media\QrCodeGenerator;
use App\Services\Subscription\PlanLimits;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class PageCategoryService
{
    /**
     * Template yang isinya bisa disalin utuh. Catalog Online belum masuk karena produknya
     * bertingkat (kategori > produk > foto) dan tiap foto perlu disalin jadi berkas sendiri;
     * menyalin setengah-setengah lebih berbahaya daripada menolak.
     */
    private const DUPLICABLE = [PageType::TEMPLATE_LANDING, PageType::TEMPLATE_BIOLINK, PageType::TEMPLATE_QUIZ, PageType::TEMPLATE_TICKET];

    public function __construct(private readonly QrCodeGenerator $qrcodes, private readonly ImageUploader $images, private readonly PlanLimits $limits)
    {
    }

    /**
     * Salinan utuh sebuah page: info, pengaturan, gambar, soal quiz beserta kunci jawabannya,
     * kolom form pembeli, serta kategori & sesi tiket.
     *
     * Catatan transaksi sengaja TIDAK ikut -- jawaban peserta, pesanan tiket, order katalog --
     * karena itu milik page aslinya dan tidak boleh tampak dua kali.
     *
     * Salinan selalu dibuat Tidak Aktif supaya tidak ada dua halaman publik kembar sebelum sempat diedit.
     *
     * @throws RuntimeException kalau template page-nya belum didukung.
     */
    public function duplicate(PageCategory $page): PageCategory
    {
        if (! in_array($page->template(), self::DUPLICABLE, true)) {
            throw new RuntimeException("Duplikat belum tersedia untuk kategori {$page->type?->templateLabel()}.");
        }

        $copy = $page->replicate(['slug', 'qrcode', 'directory_qrcode']);
        $copy->{$copy->getKeyName()} = $copy->newUniqueId();
        $copy->name = Str::limit($page->name.' (Salinan)', 100, '');
        $copy->status = PageCategory::STATUS_INACTIVE;
        $copy->slug = $this->uniqueSlug($copy->name);

        // Berkas baru dicatat supaya ikut dibuang kalau penyimpanan gagal di tengah jalan.
        $created = [];

        // Gambar disalin jadi berkas sendiri: menghapus page asal tidak boleh mematikan gambar salinannya.
        foreach (['banner', 'og_image'] as $column) {
            $copy->{$column} = $created[] = $this->copyFile($page->{$column}, 'pages/'.$copy->getKey(), $column);
        }

        $copy->directory_qrcode = 'pages/'.$page->branch_id.'/qrcodes';
        $copy->qrcode = $this->qrcodes->store(route('page.show', $copy->slug), $copy->directory_qrcode);
        $created[] = $copy->qrcodePath();

        try {
            DB::transaction(function () use ($page, $copy) {
                $this->limits->assertPage($copy->branch_id, $copy->page_type_id);
                $copy->save();
                $this->copyContent($page, $copy);
            });
        } catch (Throwable $e) {
            array_map(fn ($path) => $this->images->delete($path), array_filter($created));

            throw $e;
        }

        return $copy;
    }

    /** Isi page: soal quiz + pilihannya, kolom form, kategori & sesi tiket. Urutan, poin & kunci jawaban ikut apa adanya. */
    private function copyContent(PageCategory $page, PageCategory $copy): void
    {
        foreach ($page->quizQuestions()->with('options')->get() as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->page_id = $copy->getKey();
            $newQuestion->save();

            foreach ($question->options as $option) {
                $newOption = $option->replicate();
                $newOption->question_id = $newQuestion->getKey();
                $newOption->save();
            }
        }

        foreach ($page->fields as $field) {
            $newField = $field->replicate();
            $newField->page_category_id = $copy->getKey();
            $newField->save();
        }

        foreach ($page->ticketCategories as $category) {
            $newCategory = $category->replicate();
            $newCategory->page_id = $copy->getKey();
            // Jumlah terjual & dipesan milik event asal; salinan selalu mulai dari nol.
            $newCategory->sold = 0;
            $newCategory->reserved = 0;
            $newCategory->save();
        }

        foreach ($page->ticketSessions as $session) {
            $newSession = $session->replicate();
            $newSession->page_id = $copy->getKey();
            $newSession->save();
        }
    }

    /** Salin satu berkas di disk public ke folder lain dengan nama acak. Null kalau tidak ada atau berkasnya sudah hilang. */
    private function copyFile(?string $path, string $directory, string $prefix): ?string
    {
        $disk = Storage::disk('public');

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        $target = trim($directory, '/').'/'.$prefix.'-'.Str::lower(Str::random(12)).'.'.pathinfo($path, PATHINFO_EXTENSION);
        $disk->copy($path, $target);

        return $target;
    }

    /** $branch sudah dipastikan milik user oleh request. QR dibuat ulang hanya kalau link berubah. */
    public function save(PageCategory $page, UserBranch $branch, array $data): PageCategory
    {
        $page->fill($data);
        $page->branch_id = $branch->getKey();
        $page->{$page->getKeyName()} ??= $page->newUniqueId();
        $this->ticketFeePayer($page, $data['ticket_fee_payer'] ?? null);

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

    /** Mode biaya tiket disimpan di settings.ticket.fee_payer; dikunci setelah ada pesanan. */
    private function ticketFeePayer(PageCategory $page, ?string $payer): void
    {
        if (PageType::whereKey($page->page_type_id)->value('template') !== PageType::TEMPLATE_TICKET) {
            return;
        }

        $payer = array_key_exists((string) $payer, TicketSetting::PAYER_LABELS) ? $payer : $page->ticketFeePayer();

        if ($page->setting('ticket.fee_payer') === $payer) {
            return;
        }

        if ($payer !== $page->ticketFeePayer() && $page->ticketFeeLocked()) {
            throw ValidationException::withMessages(['ticket_fee_payer' => 'Jenis biaya event tidak bisa diganti karena sudah ada pesanan tiket.']);
        }

        $settings = $page->settings ?? [];
        data_set($settings, 'ticket.fee_payer', $payer);
        $page->settings = $settings;
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
