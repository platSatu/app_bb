<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Percakapan dengan jawaban bertahap (streaming SSE) untuk Asisten AI superadmin.
 * Format Anthropic (anthropic, classai) dan OpenAI (openai, deepseek) dibaca langsung; Gemini memakai
 * jawaban biasa (sekaligus) lewat AiClient. Kunci API hanya dipakai di server.
 */
class AiChatStream
{
    /**
     * @param  array<int, array{role: 'user'|'assistant', content: string}>  $messages
     * @param  callable(string): bool  $onDelta  dipanggil per potongan teks; kembalikan false untuk berhenti.
     * @return array{text: string, input_tokens: ?int, output_tokens: ?int, stopped: bool}
     */
    public function stream(AiProvider $provider, string $model, string $system, array $messages, int $maxTokens, callable $onDelta): array
    {
        if ($provider->driver === 'gemini') {
            $text = (new AiClient($provider))->withModel($model)->chat($system, $messages, $maxTokens);
            $onDelta($text);

            return ['text' => $text, 'input_tokens' => null, 'output_tokens' => null, 'stopped' => false];
        }

        $anthropic = $provider->usesAnthropicFormat();
        $response = $this->open($provider, $model, $system, $messages, $maxTokens, $anthropic);
        $body = $response->toPsrResponse()->getBody();
        $result = ['text' => '', 'input_tokens' => null, 'output_tokens' => null, 'stopped' => false];
        $buffer = '';

        try {
            while (! $body->eof()) {
                $buffer .= $body->read(2048);

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = rtrim(substr($buffer, 0, $pos), "\r");
                    $buffer = substr($buffer, $pos + 1);

                    if (! str_starts_with($line, 'data:')) {
                        continue;
                    }

                    $delta = $this->line(trim(substr($line, 5)), $anthropic, $result);

                    if ($delta !== '') {
                        $result['text'] .= $delta;

                        if ($onDelta($delta) === false) {
                            $result['stopped'] = true;

                            return $result;
                        }
                    }
                }
            }
        } finally {
            $body->close();
        }

        return $result;
    }

    private function open(AiProvider $provider, string $model, string $system, array $messages, int $maxTokens, bool $anthropic): Response
    {
        try {
            $request = Http::timeout(180)->connectTimeout(15)->withOptions(['stream' => true])->accept('text/event-stream');

            $response = $anthropic
                ? $request->withHeaders(['x-api-key' => $provider->api_key, 'anthropic-version' => '2023-06-01'])
                    ->post($provider->baseUrl().'/messages', [
                        'model' => $model,
                        'max_tokens' => $maxTokens,
                        'system' => $system,
                        'messages' => $messages,
                        'stream' => true,
                    ])
                : $request->withToken($provider->api_key)->post($provider->baseUrl().'/chat/completions', [
                    'model' => $model,
                    'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'system', 'content' => $system], ...$messages],
                    'stream' => true,
                    'stream_options' => ['include_usage' => true],
                ]);
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Layanan AI sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        if ($response->failed()) {
            report(new RuntimeException('AI stream '.$provider->driver.' HTTP '.$response->status().': '.mb_substr($response->body(), 0, 500)));

            throw new RuntimeException(AiClient::errorMessage($response->status()));
        }

        return $response;
    }

    /** Satu baris "data: {...}" -> potongan teks; jumlah token dicatat ke $result. */
    private function line(string $data, bool $anthropic, array &$result): string
    {
        if ($data === '' || $data === '[DONE]') {
            return '';
        }

        $event = json_decode($data, true);

        if (! is_array($event)) {
            return '';
        }

        if ($anthropic) {
            switch ($event['type'] ?? null) {
                case 'message_start':
                    $result['input_tokens'] = $this->int($event['message']['usage']['input_tokens'] ?? null) ?? $result['input_tokens'];

                    return '';
                case 'content_block_delta':
                    $text = $event['delta']['text'] ?? '';

                    return is_string($text) ? $text : '';
                case 'message_delta':
                    $result['output_tokens'] = $this->int($event['usage']['output_tokens'] ?? null) ?? $result['output_tokens'];
                    $result['input_tokens'] = $this->int($event['usage']['input_tokens'] ?? null) ?? $result['input_tokens'];

                    return '';
                case 'error':
                    report(new RuntimeException('AI stream error: '.mb_substr(json_encode($event['error'] ?? $event), 0, 500)));

                    throw new RuntimeException('Jawaban AI terputus di tengah jalan. Coba kirim ulang.');
                default:
                    return '';
            }
        }

        if (isset($event['error'])) {
            report(new RuntimeException('AI stream error: '.mb_substr(json_encode($event['error']), 0, 500)));

            throw new RuntimeException('Jawaban AI terputus di tengah jalan. Coba kirim ulang.');
        }

        if (is_array($event['usage'] ?? null)) {
            $result['input_tokens'] = $this->int($event['usage']['prompt_tokens'] ?? null) ?? $result['input_tokens'];
            $result['output_tokens'] = $this->int($event['usage']['completion_tokens'] ?? null) ?? $result['output_tokens'];
        }

        $text = $event['choices'][0]['delta']['content'] ?? '';

        return is_string($text) ? $text : '';
    }

    private function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
