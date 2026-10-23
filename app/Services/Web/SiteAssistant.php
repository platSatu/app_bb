<?php

namespace App\Services\Web;

use App\Models\AiProvider;
use App\Models\Package;
use App\Models\WebFaq;
use App\Models\WebFeature;
use App\Models\WebSetting;
use App\Support\Apps;
use App\Services\Ai\AiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Live chat bizbos.id: menjawab pertanyaan pengunjung tentang bizbos.
 * Pengetahuannya HANYA dari konten website yang dikelola superadmin (FAQ, Fitur, Paket, kontak),
 * plus catatan di Settings > Live Chat, jadi jawaban ikut berubah begitu kontennya diubah. Tanpa AI aktif, dijawab dari FAQ yang paling cocok.
 */
class SiteAssistant
{
    private const CACHE_MINUTES = 10;

    public const CACHE_KEY = 'site-assistant:knowledge';

    /** @param  array<int, array{role: string, content: string}>  $history */
    public function reply(string $message, array $history = []): string
    {
        if (! AiProvider::active()) {
            return $this->fallback($message);
        }

        try {
            $messages = [...array_slice($history, -8), ['role' => 'user', 'content' => $message]];

            return Str::limit(AiClient::make()->chat($this->systemPrompt(), $messages, 500), 2000);
        } catch (Throwable $e) {
            report($e);

            return $this->fallback($message);
        }
    }

    public function contact(): array
    {
        $setting = WebSetting::current();

        return ['whatsapp' => preg_replace('/\D/', '', (string) $setting?->handphone) ?: null, 'email' => $setting?->email];
    }

    private function systemPrompt(): string
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(self::CACHE_MINUTES), function () {
            $contact = $this->contact();
            $extra = trim((string) WebSetting::current()->chat_knowledge);

            return implode("\n\n", array_filter([
                'Kamu adalah asisten live chat di website bizbos.id. Jawab dalam Bahasa Indonesia yang ramah, singkat (maks 5 kalimat), dan jelas.',
                'Jawab HANYA berdasarkan informasi di bawah. Kalau informasinya tidak ada, katakan dengan jujur dan arahkan ke tim bizbos lewat WhatsApp/email. Jangan mengarang harga, fitur, atau janji.',
                'Untuk mendaftar atau masuk, arahkan ke https://app.bizbos.id/auth/register atau https://app.bizbos.id/auth/login.',
                "# Aplikasi di bizbos\n".collect(Apps::LIST)->map(fn ($a) => "- {$a['label']}: {$a['hint']}")->implode("\n"),
                $this->section('Fitur', WebFeature::where('status', 'active')->orderBy('sort_order')->get(['name', 'description'])
                    ->map(fn ($f) => "- {$f->name}: ".$this->plain($f->description))),
                $this->section('Paket & harga', Package::active()->orderBy('price')->get(['name', 'description', 'duration', 'price', 'is_trial'])
                    ->map(fn ($p) => "- {$p->name}: ".($p->price > 0 ? 'Rp '.number_format($p->price, 0, ',', '.') : 'gratis')." untuk {$p->duration} hari".($p->is_trial ? ' (trial)' : '').'. '.$this->plain($p->description))),
                $this->section('Pertanyaan yang sering ditanyakan', WebFaq::where('status', 'active')->orderBy('sort_order')->get(['name', 'descriptions'])
                    ->map(fn ($f) => "T: {$f->name}\nJ: ".$this->plain($f->descriptions))),
                $extra !== '' ? "# Info & arahan tambahan dari tim bizbos\n".Str::limit($extra, 6000) : null,
                "# Kontak tim bizbos\n".implode("\n", array_filter([
                    $contact['whatsapp'] ? 'WhatsApp: https://wa.me/'.$contact['whatsapp'] : null,
                    $contact['email'] ? 'Email: '.$contact['email'] : null,
                ])),
            ]));
        });
    }

    /** Tanpa AI: FAQ yang kata-katanya paling banyak sama dengan pertanyaan. */
    private function fallback(string $message): string
    {
        $words = collect(preg_split('/\W+/u', mb_strtolower($message)))->filter(fn ($w) => mb_strlen($w) > 2);
        $best = WebFaq::where('status', 'active')->get(['name', 'descriptions'])
            ->map(fn ($f) => [$f, $words->filter(fn ($w) => str_contains(mb_strtolower($f->name.' '.$f->descriptions), $w))->count()])
            ->sortByDesc(1)->first();

        if ($best && $best[1] > 0) {
            return Str::limit($this->plain($best[0]->descriptions), 800);
        }

        return 'Maaf, saya belum bisa menjawab pertanyaan itu. Tim bizbos siap membantu lewat WhatsApp, klik tombol di bawah ya.';
    }

    private function section(string $title, $lines): ?string
    {
        return $lines->isEmpty() ? null : "# {$title}\n".Str::limit($lines->implode("\n"), 6000);
    }

    private function plain(?string $html): string
    {
        return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $html))));
    }
}
