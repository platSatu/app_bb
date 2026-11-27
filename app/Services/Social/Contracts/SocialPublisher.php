<?php

namespace App\Services\Social\Contracts;

use App\Models\ContentPost;
use App\Models\ContentPublication;
use App\Models\SocialAccount;

/**
 * Kirim satu konten ke satu akun. Kedua metode mengembalikan:
 *   ['status' => 'published', 'external_id' => ..., 'permalink' => ...]  sudah terbit, atau
 *   ['status' => 'processing', 'remote_id' => ...]                         platform masih memproses (dicek lagi nanti).
 * Gagal = SocialException (pesan aman ditampilkan).
 */
interface SocialPublisher
{
    /** @param  list<string>  $urls  link media bertanda tangan (berlaku beberapa jam), urut sesuai slide */
    public function publish(ContentPublication $publication, ContentPost $post, SocialAccount $account, array $urls): array;

    public function check(ContentPublication $publication, ContentPost $post, SocialAccount $account): array;
}
