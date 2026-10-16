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
    public function __construct(private readonly AiProvider|WaAiBot $provider)
    {
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
                'gemini' => Http::timeout($timeout)->withHeaders(['x-goog-api-key' => $this->provider->api_key])->post($this->provider->baseUrl().'/models/'.$this->provider->model.':generateContent', [
                    'systemInstruction' => ['parts' => [['text' => $system]]],
                    'contents' => array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['content']]]], $messages),
                    'generationConfig' => array_filter(['responseMimeType' => $json ? 'application/json' : null, 'maxOutputTokens' => $maxTokens]),
                ]),
                'anthropic' => Http::timeout($timeout)->withHeaders([
                    'x-api-key' => $this->provider->api_key,
                    'anthropic-version' => '2023-06-01',
                ])->post($this->provider->baseUrl().'/messages', [
                    'model' => $this->provider->model,
                    'max_tokens' => $maxTokens,
                    'system' => $system,
                    'messages' => $messages,
                ]),
                // openai, deepseek, openrouter: format chat completions yang sama.
                default => Http::timeout($timeout)->withToken($this->provider->api_key)->post($this->provider->baseUrl().'/chat/completions', array_filter([
                    'model' => $this->provider->model,
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

            throw new RuntimeException(match ($response->status()) {
                401, 403 => 'API key AI ditolak penyedia. Periksa kembali API key-nya.',
                429 => 'Kuota atau batas pemakaian AI di penyedia sudah habis.',
                default => 'Layanan AI sedang bermasalah (kode '.$response->status().'). Coba lagi sebentar lagi.',
            });
        }

        $text = match ($driver) {
            'gemini' => $response->json('candidates.0.content.parts.0.text'),
            'anthropic' => $response->json('content.0.text'),
            default => $response->json('choices.0.message.content'),
        };

        return is_string($text) ? $text : '';
    }
}
