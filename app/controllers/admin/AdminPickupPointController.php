<?php

/**
 * The store and ACS points customers can collect from.
 */
class AdminPickupPointController extends AdminController {
    // ==================== PICKUP POINTS ====================

    public function index(): void {
        $this->requireAdmin();

        try {
            $points = $this->db->query("SELECT * FROM pickup_points ORDER BY active DESC, city, name")->fetchAll(PDO::FETCH_ASSOC);
            $migrated = true;
        } catch (PDOException $e) {
            $points = [];
            $migrated = false;
        }
        $acsFee        = Pickup::acsFee();
        $acsConfigured = AcsClient::configured();
        $flash = $_SESSION['admin_flash'] ?? null;
        unset($_SESSION['admin_flash']);

        $this->render('admin/pickup_points', ['points' => $points, 'migrated' => $migrated, 'acsFee' => $acsFee, 'acsConfigured' => $acsConfigured, 'flash' => $flash]);
    }

    public function store(): void {
        $this->requireAdmin();

        $name    = trim((string)($_POST['name'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $city    = trim((string)($_POST['city'] ?? ''));
        $kind    = in_array($_POST['kind'] ?? '', ['store', 'shop', 'locker'], true) ? $_POST['kind'] : 'store';
        $lat     = (float)($_POST['lat'] ?? 0);
        $lng     = (float)($_POST['lng'] ?? 0);

        // Cyprus, generously: anything outside is a typo or swapped lat/lng.
        if ($name === '' || $address === '' || $lat < 34.4 || $lat > 35.8 || $lng < 32.0 || $lng > 34.7) {
            $_SESSION['admin_flash'] = ['error', 'Name, address and a location in Cyprus are required — click the map to set it.'];
            header('Location: /admin/pickup-points');
            return;
        }

        $this->db->prepare("
            INSERT INTO pickup_points (carrier, kind, external_code, station_code, branch_code, name, address, city, hours, lat, lng, source, active)
            VALUES ('acs', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual', 1)
        ")->execute([
            $kind,
            // NULL, not '' — the (carrier, external_code) UNIQUE key allows many NULLs.
            ($c = trim((string)($_POST['external_code'] ?? ''))) !== '' ? mb_substr($c, 0, 40) : null,
            mb_substr(trim((string)($_POST['station_code'] ?? '')), 0, 20) ?: null,
            mb_substr(trim((string)($_POST['branch_code'] ?? '')), 0, 20) ?: null,
            mb_substr($name, 0, 150),
            mb_substr($address, 0, 255),
            mb_substr($city, 0, 100),
            mb_substr(trim((string)($_POST['hours'] ?? '')), 0, 120) ?: null,
            $lat,
            $lng,
        ]);
        $_SESSION['admin_flash'] = ['success', 'Pickup point added.'];
        header('Location: /admin/pickup-points');
    }

    public function toggle(): void {
        $this->requireAdmin();
        $this->db->prepare("UPDATE pickup_points SET active = 1 - active WHERE id = ?")->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: /admin/pickup-points');
    }

    public function destroy(): void {
        $this->requireAdmin();
        // Only hand-entered points. Synced ones come back on the next sync —
        // deactivate those instead.
        $this->db->prepare("DELETE FROM pickup_points WHERE id = ? AND source = 'manual'")->execute([(int)($_POST['id'] ?? 0)]);
        header('Location: /admin/pickup-points');
    }

    public function sync(): void {
        $this->requireAdmin();
        try {
            $r = Pickup::syncFromAcs($this->db);
            $_SESSION['admin_flash'] = ['success', "ACS sync: {$r['added']} added, {$r['updated']} updated, {$r['deactivated']} deactivated."];
        } catch (Throwable $e) {
            $_SESSION['admin_flash'] = ['error', 'ACS sync failed: ' . $e->getMessage()];
        }
        header('Location: /admin/pickup-points');
    }
}
