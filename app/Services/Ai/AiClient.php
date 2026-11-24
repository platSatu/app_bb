<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use App\Models\WaAiBot;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Satu pintu ke penyedia AI, kunci API hanya di server.
 * complete(): satu prompt, jawaban JSON (fitur AI platform). chat(): percakapan, jawaban teks biasa (AI Bot WhatsApp).
 */
class AiClient
{
    /** Model pengganti untuk satu pemanggilan (mis. dipilih di Asisten AI); null = model milik penyedia. */
    private ?string $model = null;

    public function __construct(private readonly AiProvider|WaAiBot $provider)
    {
    }

    /** Salinan klien yang memakai model lain dari penyedia yang sama (kunci & alamat tetap). */
    public function withModel(?string $model): self
    {
        $clone = clone $this;
        $clone->model = filled($model) ? $model : null;

        return $clone;
    }

    public function modelId(): string
    {
        return $this->model ?? $this->provider->model;
    }

    public static function make(): self
    {
        $provider = AiProvider::active();

        if (! $provider) {
            throw new RuntimeException('Fitur AI belum diaktifkan oleh admin. Silakan isi konten secara manual dulu ya.');
        }

        return new self($provider);
    }

    public function complete(string $system, string $prompt, int $maxTokens = 2000): string
    {
        $text = $this->send($system, [['role' => 'user', 'content' => $prompt]], $maxTokens, true, 60);

        if (trim($text) === '') {
            throw new RuntimeException('AI tidak memberikan jawaban. Coba ubah deskripsinya lalu ulangi.');
        }

        return $text;
    }

    /** @param  array<int, array{role: 'user'|'assistant', content: string}>  $messages */
    public function chat(string $system, array $messages, int $maxTokens = 400): string
    {
        $text = trim($this->send($system, $messages, $maxTokens, false, 30));

        if ($text === '') {
            throw new RuntimeException('AI tidak memberikan jawaban.');
        }

        return $text;
    }

    private function send(string $system, array $messages, int $maxTokens, bool $json, int $timeout): string
    {
        $driver = $this->provider->driver;

        try {
            $response = match ($driver) {
                // Kunci lewat header, bukan query string, supaya tidak ikut tercatat di log URL.
                'gemini' => Http::timeout($timeout)->withHeaders(['x-goog-api-key' => $this->provider->api_key])->post($this->provider->baseUrl().'/models/'.$this->modelId().':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['content']]]], $messages),
                    'generationConfig' => array_filter(['responseMimeType' => $json ? 'application/json' : null, 'maxOutputTokens' => $maxTokens]),
                ]),
                // classai: Classai Router menerima format Anthropic Messages yang sama (x-api-key juga diterima).
                'anthropic', 'classai' => Http::timeout($timeout)->withHeaders([
                    'x-api-key' => $this->provider->api_key,
                    'anthropic-version' => '2023-06-01',
                ])->post($this->provider->baseUrl().'/messages', [
                    'model' => $this->modelId(),
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'messages' => $messages,
                ]),
                // openai, deepseek, openrouter: format chat completions yang sama.
                default => Http::timeout($timeout)->withToken($this->provider->api_key)->post($this->provider->baseUrl().'/chat/completions', array_filter([
                    'model' => $this->modelId(),
                    'max_tokens' => $maxTokens,
                    'response_format' => $json ? ['type' => 'json_object'] : null,
                    'messages' => [['role' => 'system', 'content' => $system], ...$messages],
                ])),
            };
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Layanan AI sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        return $this->text($response, $driver);
    }

    private function text(Response $response, string $driver): string
    {
        if ($response->failed()) {
            report(new RuntimeException('AI '.$driver.' HTTP '.$response->status().': '.mb_substr($response->body(), 0, 500)));

            throw new RuntimeException(self::errorMessage($response->status()));
        }

        $text = match ($driver) {
            'gemini' => $response->json('candidates.0.content.parts.0.text'),
            'anthropic', 'classai' => $response->json('content.0.text'),
            default => $response->json('choices.0.message.content'),
        };

        return is_string($text) ? $text : '';
    }

    /** Pesan untuk user dari kode HTTP penyedia (tanpa isi balasan mentah, yang bisa memuat detail internal). */
    public static function errorMessage(int $status): string
    {
        return match ($status) {
            401 => 'API key AI ditolak penyedia. Periksa kembali API key-nya.',
            402 => 'Saldo atau paket di penyedia AI sudah habis. Isi ulang dulu, lalu coba lagi.',
            403 => 'API key AI ditolak penyedia atau tidak diizinkan memakai model ini.',
            404 => 'Model AI tidak ditemukan atau sedang tidak aktif. Pilih model lain.',
            429 => 'Kuota atau batas pemakaian AI di penyedia sudah habis.',
            default => 'Layanan AI sedang bermasalah (kode '.$status.'). Coba lagi sebentar lagi.',
        };
    }
}
