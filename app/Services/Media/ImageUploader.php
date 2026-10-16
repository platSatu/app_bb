<?php

namespace App\Services\Media;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Semua upload gambar lewat sini: diperkecil, diubah ke WebP (EXIF/GPS ikut
 * terbuang), disimpan ulang dengan nama acak di disk 'public'.
 */
class ImageUploader
{
    private const DISK = 'public';

    public function store(UploadedFile $file, string $directory, string $prefix, int $maxSize = 1200, bool $square = false): string
    {
        $image = (new ImageManager(new Driver()))->read($file->getRealPath());

        $square ? $image->cover($maxSize, $maxSize) : $image->scaleDown($maxSize, $maxSize);

        $path = trim($directory, '/').'/'.$prefix.'-'.Str::lower(Str::random(12)).'.webp';
        Storage::disk(self::DISK)->put($path, (string) $image->toWebp(80));

        return $path;
    }

    public function replace(?string $oldPath, UploadedFile $file, string $directory, string $prefix, int $maxSize = 1200, bool $square = false): string
    {
        $newPath = $this->store($file, $directory, $prefix, $maxSize, $square);
        $this->delete($oldPath);

        return $newPath;
    }

    /**
     * Simpan model sekaligus gambarnya. Gagal simpan = file baru dibuang;
     * berhasil = file lama baru dihapus setelah transaksi commit.
     */
    public function saveWith(Model $model, string $column, ?UploadedFile $file, string $directory, int $maxSize = 1200): Model
    {
        if (! $file) {
            $model->save();

            return $model;
        }

        $model->{$model->getKeyName()} ??= $model->newUniqueId();
        $oldPath = $model->getOriginal($column);
        $model->{$column} = $this->store($file, trim($directory, '/').'/'.$model->getKey(), $column, $maxSize);

        try {
            $model->save();
        } catch (Throwable $e) {
            $this->delete($model->{$column});

            throw $e;
        }

        DB::afterCommit(fn () => $this->delete($oldPath));

        return $model;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
