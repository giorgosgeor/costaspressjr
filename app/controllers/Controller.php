<?php

/**
 * What every controller shares: the database connection and rendering.
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
}
