<?php

namespace App\Services\Social\Contracts;

use App\Models\SocialAccount;
use App\Models\SocialApp;
use App\Models\User;

/** Sambungan OAuth satu penyedia (Meta, TikTok, Google): halaman izin, tukar code jadi token, perpanjang token. */
interface SocialConnector
{
    public function authorizeUrl(SocialApp $app, string $state): string;

    /**
     * Tukar code dari callback menjadi token lalu simpan/perbarui akun-akunnya.
     *
     * @return list<SocialAccount>
     */
    public function connect(SocialApp $app, string $code, User $user): array;

    /** Perpanjang access token (atau perbarui data akun). Gagal = SocialException. */
    public function refresh(SocialAccount $account, SocialApp $app): void;
}
