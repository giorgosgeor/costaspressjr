<?php

/**
 * What every controller shares: the database connection, rendering, the
 * 404 page and who the current shopper is.
 */
abstract class Controller {
    public function __construct(protected PDO $db) {
    }

    /**
     * Render views/{$template}.php with $data. The layouts also get the
     * database connection, for the signed-in visitor's banner and cookie
     * state (see CurrentUser).
     */
    protected function render(string $template, array $data = []): void {
        View::render($template, $data + ['db' => $this->db]);
    }

    /** The branded 404 page — unknown URLs (via Router) and missing records. */
    public function notFound(): void {
        http_response_code(404);
        $this->render('pages/not_found');
    }

    /**
     * The user id shopping flows act as: the account, or the session's guest
     * row (see Auth::effectiveUserId()). Pass true on paths that need a cart
     * to exist; read paths leave it false so crawlers don't create guest rows.
     */
    protected function effectiveUserId(bool $createGuest = false): ?int {
        return Auth::effectiveUserId($this->db, $createGuest);
    }
}
