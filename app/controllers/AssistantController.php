<?php

/**
 * The shop assistant chat (POST /assistant/ask); the answering itself is
 * ShopAssistant.
 */
class AssistantController extends Controller {
    /**
     * Shop assistant endpoint.
     *
     * Public on purpose: most of these questions ("how much for 50 shirts?",
     * "do you deliver?") are asked BEFORE someone has an account, and putting
     * them behind a login would mean the bot only ever talks to people who
     * are already customers.
     *
     * Public also means it needs its own limits, since nothing upstream is
     * rate-limiting an anonymous visitor: a CSRF token ties the request to a
     * real session, the question is length-capped, and a per-session counter
     * caps the rate. Answering costs nothing here, but the endpoint should
     * still not be usable as a free amplifier, and the same limits keep the
     * cost bounded if this is ever pointed at a paid language model.
     */
    public function ask(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $raw  = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (!is_array($body)) {
            $body = [];
        }

        // Csrf::tokenFromRequest() would re-read php://input, which has
        // already been consumed above, so the token is taken from the header
        // (or the parsed body) and handed to Csrf::check() directly.
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['_csrf'] ?? '');
        if (!Csrf::check(is_string($token) ? $token : '')) {
            http_response_code(419);
            echo json_encode(['error' => 'csrf']);
            return;
        }

        // A ROLLING WINDOW, not a lifetime counter.
        //
        // This was a running total that never reset, so the 30th question
        // locked a visitor out of the assistant for the rest of their session
        // with no way back — including anyone who was simply curious and
        // clicked a few suggested questions. A window recovers on its own:
        // ask a lot, wait a few minutes, carry on.
        //
        // The cap is deliberately generous because answering is nearly free:
        // the matcher handles most questions on this server at no cost, and
        // the model calls behind it have their own, tighter limit in
        // ShopAssistant. This one exists only to stop automated abuse.
        $now    = time();
        $window = 600;   // 10 minutes
        $hits   = $_SESSION['assistant_hits'] ?? [];
        // Previously an int; drop any old value rather than crash on it.
        if (!is_array($hits)) {
            $hits = [];
        }
        $hits = array_values(array_filter($hits, static fn($t) => is_int($t) && $t > $now - $window));
        $hits[] = $now;
        $_SESSION['assistant_hits'] = $hits;

        if (count($hits) > 40) {
            http_response_code(429);
            header('Retry-After: ' . $window);
            echo json_encode([
                'intent' => 'rate_limited',
                'text'   => t('assistant.rate_limited', false),
                'links'  => [['label' => t('assistant.link.contact', false), 'href' => '/contact']],
                'suggestions' => [],
            ]);
            return;
        }

        $question = (string)($body['q'] ?? '');
        // Cut rather than reject: someone pasting a long order description
        // should still get an answer, not an error.
        if (mb_strlen($question) > 500) {
            $question = mb_substr($question, 0, 500);
        }

        $assistant = new ShopAssistant($this->db, I18n::locale());
        echo json_encode($assistant->answer($question), JSON_UNESCAPED_UNICODE);
    }
}
