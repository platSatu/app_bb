<?php

namespace App\Services\Catalog;

use App\Models\CatalogProduct;
use App\Models\CatalogProductImage;
use App\Models\PageCategory;
use App\Services\Media\ImageUploader;
use App\Support\Slug;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

class CatalogProductService
{
    private const LARGE = 1200;
    private const THUMB = 400;

    public function __construct(private readonly ImageUploader $images)
    {
    }

    /**
     * Simpan produk + semua baris foto sekaligus. $rows sudah tervalidasi, urutannya = urutan foto.
     * File baru di-upload dulu; kalau simpan gagal, file baru dibuang. File lama baru dihapus setelah commit.
     *
     * @param  array<int, array{id: ?string, name: ?string, description: ?string, status: string, file: ?UploadedFile}>  $rows
     */
    public function save(PageCategory $page, CatalogProduct $product, array $data, array $rows): CatalogProduct
    {
        $uploaded = [];

        try {
            foreach ($rows as $i => $row) {
                if ($row['file'] instanceof UploadedFile) {
                    $dir = 'catalog/'.$page->getKey();
                    $uploaded[$i] = [
                        'image' => $this->images->store($row['file'], $dir, 'product', self::LARGE),
                        'thumbnail' => $this->images->store($row['file'], $dir, 'thumb', self::THUMB),
                    ];
                }
            }

            $removed = DB::transaction(function () use ($page, $product, $data, $rows, $uploaded) {
                $product->fill($data);
                $product->page_id = $page->getKey();
                $product->type ??= CatalogProduct::TYPE_PHYSICAL;
                $product->slug ??= Slug::unique(CatalogProduct::where('page_id', $page->getKey()), $product->name, 'produk', null, 180);
                $product->sort_order ??= (int) CatalogProduct::where('page_id', $page->getKey())->max('sort_order') + 1;
                $product->save();

                $existing = $product->images()->lockForUpdate()->get()->keyBy('id');
                $kept = [];

                foreach ($rows as $position => $row) {
                    $image = $existing->get($row['id'] ?? '');

                    if (! $image && ! isset($uploaded[$position])) {
                        continue;
                    }

                    $image ??= new CatalogProductImage();
                    $image->fill(['name' => $row['name'], 'description' => $row['description'], 'status' => $row['status']]);
                    $image->product_id = $product->getKey();
                    $image->sort_order = $position;

                    if (isset($uploaded[$position])) {
                        $image->forceFill($uploaded[$position]);
                    }

                    $image->save();
                    $kept[] = $image->getKey();
                }

                $removed = $existing->except($kept);
                CatalogProductImage::whereKey($removed->keys())->delete();

                return $removed;
            });
        } catch (Throwable $e) {
            foreach ($uploaded as $files) {
                $this->images->delete($files['image']);
                $this->images->delete($files['thumbnail']);
            }

            throw $e;
        }

        foreach ($removed as $image) {
            $this->images->delete($image->image);
            $this->images->delete($image->thumbnail);
        }

        return $product;
    }

    public function delete(CatalogProduct $product): void
    {
        $files = $product->images()->get(['image', 'thumbnail']);
        $product->delete();

        foreach ($files as $image) {
            $this->images->delete($image->image);
            $this->images->delete($image->thumbnail);
        }
    }
}
