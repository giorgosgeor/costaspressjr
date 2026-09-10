<?php
/**
 * Favourites for the account area.
 *
 * A row points at EITHER a product or a premade design — the shop sells both,
 * and a shopper saving "that hoodie" and "that dragon print" means different
 * things. The unused column stays NULL.
 *
 * MySQL treats NULLs as distinct in a unique index, so the two unique keys
 * below can coexist on one table: they stop the same user favouriting the same
 * product (or design) twice without blocking rows of the other kind.
 *
 * Run:  php database/add_user_favorites.php
 * Idempotent: does nothing if the table already exists.
 */

require_once __DIR__ . '/../app/core/Env.php';
Env::load(__DIR__ . '/../.env');
$pdo = require __DIR__ . '/../app/config/database.php';

$exists = $pdo->query("SHOW TABLES LIKE 'user_favorites'")->fetch();
if ($exists) {
    echo "user_favorites already exists — nothing to do.\n";
    return;
}

$pdo->exec("
    CREATE TABLE user_favorites (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        user_id    INT NOT NULL,
        product_id INT NULL,
        design_id  INT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_fav_product (user_id, product_id),
        UNIQUE KEY uq_fav_design  (user_id, design_id),
        KEY idx_fav_user (user_id),
        CONSTRAINT fk_fav_user    FOREIGN KEY (user_id)    REFERENCES users(id)           ON DELETE CASCADE,
        CONSTRAINT fk_fav_product FOREIGN KEY (product_id) REFERENCES products(id)        ON DELETE CASCADE,
        CONSTRAINT fk_fav_design  FOREIGN KEY (design_id)  REFERENCES premade_designs(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");
echo "created user_favorites.\n";

// A row must name exactly one target. Enforced here rather than in PHP alone so
// a stray insert can't leave a favourite pointing at nothing.
try {
    $pdo->exec("
        ALTER TABLE user_favorites
        ADD CONSTRAINT chk_fav_one_target
        CHECK ((product_id IS NULL) <> (design_id IS NULL))
    ");
    echo "added one-target check constraint.\n";
} catch (PDOException $e) {
    // MariaDB/MySQL below 8.0.16 parse CHECK but ignore it; not fatal.
    echo "check constraint not supported here, skipping: " . $e->getMessage() . "\n";
}
