<?php

namespace App\Models\Concerns;

/** Data aplikasi Schedules: milik satu branch, dikelola superadmin lewat izin superadmin.schedules. */
trait BelongsToScheduleBranch
{
    use ScopedToBranch;

    public const ADMIN_PERMISSION = 'superadmin.schedules';

    /** Nama untuk pilihan dropdown; relasi di LABEL_WITH harus sudah dimuat. */
    public function label(): string
    {
        return $this->name;
    }
}
