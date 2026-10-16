<?php

namespace App\Services\Landing;

use App\Models\LandingSection;
use App\Models\PageCategory;
use App\Services\Media\ImageUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class LandingSectionService
{
    public function __construct(private readonly ImageUploader $images)
    {
    }

    public static function directory(PageCategory $page): string
    {
        return 'landing/'.$page->getKey();
    }

    /**
     * Ubah baris form (sudah tervalidasi) jadi section siap simpan/preview.
     * Gambar lama hanya diterima kalau memang milik page ini; tujuan tombol hanya page satu branch.
     *
     * @return array<int, array{id: ?string, type: string, status: string, data: array, uploads: array}>
     */
    public function normalize(PageCategory $page, array $rows): array
    {
        $prefix = self::directory($page).'/';
        $owned = $this->currentImages($page);
        $branchPages = PageCategory::where('branch_id', $page->branch_id)->pluck('id')->all();

        return collect($rows)->values()->map(function (array $row) use ($prefix, $owned, $branchPages) {
            [, $fields, $media] = LandingSection::TYPES[$row['type']];
            $data = [];

            foreach ($fields as $field => [, $max]) {
                $data[$field] = Str::limit(trim((string) ($row['data'][$field] ?? '')), $max, '');
            }

            if (in_array($row['type'], LandingSection::WITH_BUTTON, true)) {
                $pageId = $row['button_page_id'] ?? null;
                $url = trim((string) ($row['button_url'] ?? ''));
                $data['button_page_id'] = in_array($pageId, $branchPages, true) ? $pageId : null;
                $data['button_url'] = preg_match('~^https?://~i', $url) ? Str::limit($url, 500, '') : null;
            }

            $keep = fn ($path) => is_string($path) && str_starts_with($path, $prefix) && in_array($path, $owned, true);

            if ($media === 'image') {
                $data['image'] = empty($row['remove_image']) && $keep($row['image'] ?? null) ? $row['image'] : null;
            }

            if ($media === 'images') {
                $data['images'] = array_values(array_filter((array) ($row['images'] ?? []), $keep));
            }

            return [
                'id' => $row['id'] ?? null,
                'type' => $row['type'],
                'status' => $row['status'],
                'data' => $data,
                'uploads' => array_values(array_filter(
                    $media === 'image' ? [$row['image_file'] ?? null] : (array) ($row['image_files'] ?? []),
                    fn ($file) => $file instanceof UploadedFile
                )),
            ];
        })->all();
    }

    /** Simpan semua section. File baru di-upload dulu; gagal = dibuang. File yang tak dipakai lagi dihapus setelah commit. */
    public function sync(PageCategory $page, array $rows): void
    {
        $before = $this->currentImages($page);
        $stored = [];

        try {
            foreach ($rows as $i => $row) {
                foreach ($row['uploads'] as $file) {
                    $stored[$i][] = $this->images->store($file, self::directory($page), $row['type'], 1600);
                }
            }

            DB::transaction(function () use ($page, $rows, $stored) {
                PageCategory::whereKey($page->getKey())->lockForUpdate()->first();
                $existing = LandingSection::where('page_id', $page->getKey())->get()->keyBy('id');
                $kept = [];

                foreach ($rows as $position => $row) {
                    $data = $row['data'];

                    if (isset($stored[$position])) {
                        $media = LandingSection::TYPES[$row['type']][2];
                        if ($media === 'image') {
                            $data['image'] = $stored[$position][0];
                        } else {
                            $data['images'] = array_slice([...$data['images'], ...$stored[$position]], 0, LandingSection::MAX_GALLERY);
                        }
                    }

                    $section = $existing->get($row['id'] ?? '') ?? new LandingSection();
                    $section->fill(['type' => $row['type'], 'data' => $data, 'status' => $row['status']]);
                    $section->page_id = $page->getKey();
                    $section->sort_order = $position;
                    $section->save();
                    $kept[] = $section->getKey();
                }

                LandingSection::where('page_id', $page->getKey())->whereKeyNot($kept)->delete();
            });
        } catch (Throwable $e) {
            collect($stored)->flatten()->each(fn ($path) => $this->images->delete($path));

            throw $e;
        }

        foreach (array_diff($before, $this->currentImages($page)) as $unused) {
            $this->images->delete($unused);
        }
    }

    private function currentImages(PageCategory $page): array
    {
        return LandingSection::where('page_id', $page->getKey())->get()
            ->flatMap(fn ($s) => array_filter([$s->get('image'), ...(array) $s->get('images', [])]))
            ->unique()
            ->values()
            ->all();
    }
}
