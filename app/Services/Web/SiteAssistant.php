<?php

namespace App\Services\Web;

use App\Models\AiProvider;
use App\Models\Package;
use App\Models\WebFaq;
use App\Models\WebFeature;
use App\Models\WebSetting;
use App\Support\Apps;
use App\Services\Ai\AiClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

/**
 * Live chat bizbos.id: menjawab pertanyaan pengunjung tentang bizbos.
 * Default TANPA AI: jawaban diambil dari FAQ (Website > FAQ) yang paling cocok, dibungkus sapaan memakai nama
 * pengunjung supaya terasa seperti dibalas orang. Merawatnya cukup menambah/mengubah FAQ.
 * AI hanya dipakai kalau superadmin menyalakannya di Settings > Live Chat DAN ada AI aktif; pengetahuannya
 * tetap HANYA dari konten website (FAQ, Fitur, Paket, kontak) plus catatan Live Chat.
 */
class SiteAssistant
{
    private const CACHE_MINUTES = 10;

    public const CACHE_KEY = 'site-assistant:knowledge';

    // Singkatan/bahasa chat sehari-hari -> kata baku, supaya "brp hrg nya" tetap cocok dengan FAQ "Berapa harga paket?".
    private const SLANG = [
        'brp' => 'berapa', 'brapa' => 'berapa', 'hrg' => 'harga', 'hrga' => 'harga', 'gmn' => 'bagaimana', 'gimana' => 'bagaimana',
        'gmna' => 'bagaimana', 'bgmn' => 'bagaimana', 'bs' => 'bisa', 'bsa' => 'bisa', 'gk' => 'tidak', 'ga' => 'tidak', 'gak' => 'tidak',
        'nggak' => 'tidak', 'enggak' => 'tidak', 'tdk' => 'tidak', 'yg' => 'yang', 'dgn' => 'dengan', 'utk' => 'untuk', 'sy' => 'saya',
        'aja' => 'saja', 'gratisan' => 'gratis', 'free' => 'gratis', 'register' => 'daftar', 'signup' => 'daftar', 'login' => 'masuk',
        'wa' => 'whatsapp', 'promo' => 'diskon', 'paketnya' => 'paket', 'biayanya' => 'biaya', 'harganya' => 'harga',
    ];

    private const STOPWORDS = ['apa', 'apakah', 'yang', 'dan', 'atau', 'di', 'ke', 'dari', 'ini', 'itu', 'saya', 'aku', 'kak', 'min', 'admin',
        'ada', 'untuk', 'dengan', 'sih', 'dong', 'deh', 'kah', 'mau', 'tanya', 'nanya', 'mohon', 'info', 'tolong', 'ingin', 'bagaimana', 'cara'];

    /** @param  array<int, array{role: string, content: string}>  $history */
    public function reply(string $message, array $history = [], ?string $name = null): string
    {
        if (! WebSetting::current()->chat_ai || ! AiProvider::active()) {
            return $this->answer($message, $name);
        }

        try {
            $messages = [...array_slice($history, -8), ['role' => 'user', 'content' => $message]];
            $prompt = $this->systemPrompt().($name ? "\n\nNama pengunjung: {$this->firstName($name)}. Sapa dengan \"Kak {$this->firstName($name)}\"." : '');

            return Str::limit(AiClient::make()->chat($prompt, $messages, 500), 2000);
        } catch (Throwable $e) {
            report($e);

            return $this->answer($message, $name);
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

    /**
     * Tanpa AI: obrolan ringan (sapaan, terima kasih, minta bicara dengan tim) dijawab langsung; selain itu
     * FAQ yang paling cocok (kata di judul FAQ bernilai 2, di isi bernilai 1, minimal 2), dibungkus sapaan bernama.
     */
    private function answer(string $message, ?string $name): string
    {
        $kak = 'Kak '.($this->firstName($name) ?: '');
        $kak = rtrim($kak);
        $text = $this->normalize($message);

        if (preg_match('/^(halo|hallo|hai|hi|hello|hey|pagi|siang|sore|malam|selamat \w+|assalamu.?alaikum|permisi|p)\b/u', $text) && str_word_count($text) <= 3) {
            return "Halo {$kak}, selamat {$this->partOfDay()}! 😊 Ada yang bisa saya bantu seputar bizbos? Silakan tanyakan soal fitur, harga paket, atau cara mulai.";
        }

        if (preg_match('/\b(makasih|terima kasih|trima kasih|thanks|thank you|thx|tq|tengkyu)\b/u', $text)) {
            return "Sama-sama, {$kak}! Senang bisa membantu. Kalau nanti ada pertanyaan lain, tanya saja di sini ya 🙏";
        }

        if (preg_match('/\b(cs|customer service|manusia|orang asli|telepon|telpon|hubungi|kontak|bicara dengan)\b/u', $text)) {
            return "Tentu, {$kak}. Tim kami siap membantu langsung lewat WhatsApp, klik tombol hijau di bawah ya. Kami balas secepatnya 😊";
        }

        $words = collect(explode(' ', $text))
            ->map(fn ($w) => preg_replace('/(nya|kah|lah)$/u', '', $w))
            ->filter(fn ($w) => mb_strlen($w) > 2 && ! in_array($w, self::STOPWORDS, true))->unique();

        $best = WebFaq::where('status', 'active')->get(['name', 'descriptions'])
            ->map(function ($faq) use ($words) {
                $title = $this->normalize($faq->name);
                $body = $this->normalize($this->plain($faq->descriptions));

                return [$faq, $words->sum(fn ($w) => (str_contains($title, $w) ? 2 : 0) + (str_contains($body, $w) ? 1 : 0))];
            })
            ->sortByDesc(1)->first();

        if (! $best || $best[1] < 2) {
            return "Maaf {$kak}, saya belum menemukan jawaban yang pas untuk pertanyaan itu. Biar tim kami yang bantu langsung lewat WhatsApp ya, klik tombol hijau di bawah 🙏";
        }

        $openers = ["Baik, {$kak}.", "Siap, {$kak}!", "Oke, {$kak}, saya jelaskan ya."];
        $closers = ['Ada lagi yang ingin ditanyakan?', "Semoga membantu ya, {$kak} 😊", 'Kalau masih ada yang kurang jelas, tanya saja lagi ya.'];

        return Arr::random($openers)."\n".Str::limit($this->plain($best[0]->descriptions), 800)."\n\n".Arr::random($closers);
    }

    private function normalize(string $text): string
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);

        return implode(' ', array_map(fn ($w) => self::SLANG[$w] ?? $w, $words));
    }

    private function firstName(?string $name): string
    {
        return Str::title(Str::limit(trim((string) strtok(trim((string) $name), ' ')), 30, ''));
    }

    private function partOfDay(): string
    {
        $hour = (int) now('Asia/Jakarta')->format('G');

        return match (true) {
            $hour < 11 => 'pagi',
            $hour < 15 => 'siang',
            $hour < 18 => 'sore',
            default => 'malam',
        };
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
