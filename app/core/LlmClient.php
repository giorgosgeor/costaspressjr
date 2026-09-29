<?php
declare(strict_types=1);

/**
 * Minimal chat-completion client for a free-tier LLM provider.
 *
 * WHY THIS IS PROVIDER-AGNOSTIC
 *
 * It is pointed at a free tier, and free tiers move: limits tighten without
 * notice, models get renamed or retired, and a provider can simply stop
 * offering one. Being able to change provider by editing .env — with no code
 * change and no redeploy — is the difference between a five-minute fix and an
 * outage on the shop's contact path.
 *
 * Two request shapes cover everything worth using:
 *   - "openai"  — the OpenAI-compatible /chat/completions shape, which Groq,
 *                 OpenRouter, Mistral, Cerebras and most others all speak.
 *   - "gemini"  — Google's own generateContent shape, kept because Gemini's
 *                 Greek is better than the open models', and this shop is
 *                 half Greek-speaking.
 *
 * WHAT IT DELIBERATELY DOES NOT DO
 *
 * It never throws. Every failure path — no key, bad key, rate limit, timeout,
 * malformed JSON, provider outage — returns null, and the caller falls back to
 * the intent matcher. A customer-facing widget must not break because a free
 * API had a bad afternoon, and free tiers carry no SLA whatsoever.
 */
final class LlmClient
{
    /** Hard ceiling on the wait. A shopper will not sit through more. */
    private const TIMEOUT_SECONDS = 8;
    private const CONNECT_TIMEOUT = 4;

    private string $shape;
    private string $endpoint;
    private string $model;
    private string $apiKey;

    public function __construct()
    {
        $this->shape    = strtolower(trim((string)Env::get('LLM_PROVIDER', 'openai')));
        $this->endpoint = trim((string)Env::get('LLM_ENDPOINT', ''));
        $this->model    = trim((string)Env::get('LLM_MODEL', ''));
        $this->apiKey   = trim((string)Env::get('LLM_API_KEY', ''));
    }

    /**
     * Configured and safe to call? The widget uses this to decide whether the
     * LLM layer exists at all, so an unconfigured install behaves exactly as
     * it did before this file was added.
     */
    public function isEnabled(): bool
    {
        return $this->apiKey !== '' && $this->endpoint !== '' && $this->model !== '';
    }

    /**
     * One-shot completion.
     *
     * @param string $system Instructions plus the knowledge the model may use.
     * @param string $user   The customer's question.
     * @return string|null   The answer, or null on ANY failure.
     */
    public function complete(string $system, string $user): ?string
    {
        if (!$this->isEnabled() || !function_exists('curl_init')) {
            return null;
        }

        [$url, $headers, $payload] = $this->shape === 'gemini'
            ? $this->buildGemini($system, $user)
            : $this->buildOpenAi($system, $user);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            // Certificate verification stays ON. Turning it off is the usual
            // "fix" for a local TLS misconfiguration and it silently converts
            // an encrypted call into one anybody on the path can read.
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $raw    = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        // No curl_close(): it has done nothing since PHP 8.0 and is deprecated
        // as of 8.5 (this host runs 8.5.1), so calling it only emits a notice
        // on every request. The handle is freed when it goes out of scope.
        unset($ch);

        if ($raw === false || $status < 200 || $status >= 300) {
            // Logged, not surfaced: the customer gets the fallback answer and
            // never learns an API failed, but the cause is recoverable later.
            self::note('llm http ' . $status . ' ' . ($err !== '' ? $err : substr((string)$raw, 0, 200)));
            return null;
        }

        $data = json_decode((string)$raw, true);
        if (!is_array($data)) {
            self::note('llm returned non-JSON');
            return null;
        }

        $text = $this->shape === 'gemini'
            ? ($data['candidates'][0]['content']['parts'][0]['text'] ?? null)
            : ($data['choices'][0]['message']['content'] ?? null);

        if (!is_string($text)) {
            self::note('llm response missing text');
            return null;
        }

        $text = trim($text);
        return $text === '' ? null : $text;
    }

    /** @return array{0:string,1:array<int,string>,2:array<string,mixed>} */
    private function buildOpenAi(string $system, string $user): array
    {
        return [
            $this->endpoint,
            [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            [
                'model'    => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
                // Short on purpose: this is a support reply in a small chat
                // panel, and a long one both reads badly and eats the free
                // tier's tokens-per-minute allowance faster.
                'max_tokens'  => 200,
                'temperature' => 0.3,
            ],
        ];
    }

    /** @return array{0:string,1:array<int,string>,2:array<string,mixed>} */
    private function buildGemini(string $system, string $user): array
    {
        // Gemini takes the key as a query parameter and the system prompt in
        // its own field rather than as a message role.
        $url = $this->endpoint . (str_contains($this->endpoint, '?') ? '&' : '?')
             . 'key=' . urlencode($this->apiKey);

        return [
            $url,
            ['Content-Type: application/json'],
            [
                'system_instruction' => ['parts' => [['text' => $system]]],
                'contents' => [
                    ['role' => 'user', 'parts' => [['text' => $user]]],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => 200,
                    'temperature'     => 0.3,
                ],
            ],
        ];
    }

    private static function note(string $message): void
    {
        if (class_exists('Log') && method_exists('Log', 'warning')) {
            Log::warning($message);
        } else {
            error_log('[assistant] ' . $message);
        }
    }
}
