<?php

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Services\Social\Inbox\SocialInbox;
use App\Services\Social\SocialException;
use Illuminate\Console\Command;
use Throwable;

/** Tools › Inbox: YouTube tidak punya webhook komentar, jadi komentar terbaru diambil berkala. */
class PollSocialComments extends Command
{
    protected $signature = 'social:poll-comments';

    protected $description = 'Ambil komentar YouTube terbaru ke Tools › Inbox';

    public function handle(SocialInbox $inbox): int
    {
        foreach (SocialAccount::connected()->where('platform', 'youtube')->get() as $account) {
            try {
                $new = $inbox->pollYoutube($account);
                $this->line($account->displayName().': '.$new.' komentar baru');
                $account->forceFill(['meta' => array_merge($account->meta ?? [], ['inbox_polled_at' => now()->toIso8601String(), 'inbox_error' => null])])->save();
            } catch (SocialException $e) {
                // Mis. izin komentar belum diberikan: dicatat di akun, tampil di Tools › Inbox.
                $account->forceFill(['meta' => array_merge($account->meta ?? [], ['inbox_error' => mb_substr($e->getMessage(), 0, 300)])])->save();

                if ($e->expired) {
                    $account->markExpired($e->getMessage());
                }

                $this->warn($account->displayName().': '.$e->getMessage());
            } catch (Throwable $e) {
                report($e);
            }
        }

        return self::SUCCESS;
    }
}
