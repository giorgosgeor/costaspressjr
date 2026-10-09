<?php

/**
 * How an order gets to the customer. There is no home delivery and no
 * collection from the store: every order is sent by ACS to the ACS store or
 * Smartpoint locker the customer picks, for a fixed fee (ACS_PICKUP_FEE).
 * STORE remains only for orders placed when store pickup was offered.
 */
class Pickup
{
    public const STORE = 'store';
    public const ACS   = 'acs_point';

    /** The delivery fee in EUR, or null while it isn't set — the checkout takes no orders until it is. */
    public static function acsFee(): ?float
    {
        $raw = trim((string)Env::get('ACS_PICKUP_FEE', ''));
        if ($raw === '' || !is_numeric($raw) || (float)$raw < 0) {
            return null;
        }
        return round((float)$raw, 2);
    }

    /** The store's collection address as shown at checkout; empty when not configured. */
    public static function storeAddress(): string
    {
        return trim((string)Env::get('STORE_PICKUP_ADDRESS', ''));
    }

    /** Orders can be taken only with a fee set AND at least one point to pick. */
    public static function acsAvailable(PDO $db): bool
    {
        if (self::acsFee() === null) {
            return false;
        }
        try {
            return (int)$db->query("SELECT COUNT(*) FROM pickup_points WHERE active = 1 AND carrier = 'acs'")->fetchColumn() > 0;
        } catch (PDOException $e) {
            return false; // migration not applied yet
        }
    }

    /** Active ACS points for the checkout map — only what the browser needs. */
    public static function activePoints(PDO $db): array
    {
        try {
            $rows = $db->query("
                SELECT id, kind, name, address, city, zipcode, hours, hours_saturday, lat, lng
                FROM pickup_points
                WHERE active = 1 AND carrier = 'acs'
                ORDER BY city, name
            ")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
        foreach ($rows as &$r) {
            $r['id']  = (int)$r['id'];
            $r['lat'] = (float)$r['lat'];
            $r['lng'] = (float)$r['lng'];
        }
        return $rows;
    }

    public static function findActivePoint(PDO $db, int $id): ?array
    {
        try {
            $stmt = $db->prepare("SELECT * FROM pickup_points WHERE id = ? AND active = 1 AND carrier = 'acs'");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    /**
     * Validate the checkout's collection block.
     *
     * @param ?string $accountEmail the logged-in customer's email; null for guests,
     *                              who must type one.
     * @return array either ['error' => message] or the normalised choice:
     *               method, point (row), fee, name, phone, email
     */
    public static function validateChoice(PDO $db, array $data, ?string $accountEmail): array
    {
        $method = (string)($data['delivery_method'] ?? '');

        // ACS is the only way an order travels; store pickup is no longer offered.
        if ($method !== self::ACS) {
            return ['error' => I18n::t('checkout.pickup.errors.no_point')];
        }
        if (!self::acsAvailable($db)) {
            return ['error' => I18n::t('checkout.pickup.errors.acs_unavailable')];
        }
        $point = self::findActivePoint($db, (int)($data['pickup_point_id'] ?? 0));
        if (!$point) {
            return ['error' => I18n::t('checkout.pickup.errors.no_point')];
        }
        $fee = (float)self::acsFee();

        $contact = is_array($data['contact'] ?? null) ? $data['contact'] : [];
        // Control characters out; names are free text in two alphabets.
        $clean = static fn($v) => trim((string)preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$v));

        $name = $clean($contact['name'] ?? '');
        if ($name === '' || mb_strlen($name) > 100) {
            return ['error' => I18n::t('checkout.pickup.errors.name')];
        }

        // ACS needs a mobile number: it texts the customer when the parcel
        // arrives (with the code, for a Smartpoint locker). Loose check: 8–15
        // digits, with the usual + ( ) - and spaces allowed.
        $phone  = $clean($contact['phone'] ?? '');
        $digits = preg_replace('/\D/', '', $phone);
        if (!preg_match('/^[+\d][\d\s()\-]*$/', $phone) || strlen($digits) < 8 || strlen($digits) > 15 || mb_strlen($phone) > 30) {
            return ['error' => I18n::t('checkout.pickup.errors.phone')];
        }

        $email = $accountEmail;
        if ($email === null) {
            $email = $clean($contact['email'] ?? '');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 254) {
                return ['error' => I18n::t('checkout.pickup.errors.email')];
            }
        }

        return [
            'method' => $method,
            'point'  => $point,
            'fee'    => $fee,
            'name'   => $name,
            'phone'  => $phone,
            'email'  => $email,
        ];
    }

    /** The point as frozen onto the order. */
    public static function snapshot(?array $point): ?string
    {
        if (!$point) {
            return null;
        }
        return json_encode([
            'id'            => (int)$point['id'],
            'kind'          => $point['kind'],
            'name'          => $point['name'],
            'address'       => $point['address'],
            'city'          => $point['city'],
            'zipcode'       => $point['zipcode'],
            'external_code' => $point['external_code'],
            'station_code'  => $point['station_code'],
            'branch_code'   => $point['branch_code'],
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Human-readable block stored in orders.shipping_address. The admin order
     * screens already print that column, so this is how staff see where an
     * order goes without any change to those screens.
     */
    public static function orderText(string $method, ?array $point, string $name, string $phone, ?string $email): string
    {
        if ($method === self::ACS && $point) {
            $code  = trim(($point['station_code'] ?? '') . ' ' . ($point['branch_code'] ?? ''));
            $lines = ['COLLECT AT ACS POINT: ' . $point['name'] . ($code !== '' ? " [$code]" : ''),
                      trim($point['address'] . ', ' . ($point['city'] ?? ''), ', ')];
        } else {
            $lines = ['COLLECT FROM STORE'];
        }
        $lines[] = '';
        $lines[] = $name;
        $lines[] = 'Tel: ' . $phone;
        if ($email) {
            $lines[] = 'Email: ' . $email;
        }
        return implode("\n", $lines);
    }

    /**
     * Refresh the ACS points from the ACS_Stations web service.
     *
     * Points ACS no longer lists are deactivated, never deleted — past orders
     * reference them. Points that are still listed keep whatever active flag
     * the admin gave them.
     *
     * @return array{added:int, updated:int, deactivated:int}
     */
    public static function syncFromAcs(PDO $db): array
    {
        $points = AcsClient::cyprusPoints();
        if (!$points) {
            // An empty answer is far more likely a fault than ACS closing
            // every point in Cyprus. Don't wipe the list over it.
            throw new RuntimeException('ACS returned no points; nothing changed');
        }

        $existing = [];
        foreach ($db->query("SELECT external_code FROM pickup_points WHERE carrier = 'acs' AND source = 'acs_api'") as $r) {
            $existing[$r['external_code']] = true;
        }

        $upsert = $db->prepare("
            INSERT INTO pickup_points
                (carrier, kind, external_code, station_code, branch_code, name, address, city, zipcode, phone, hours, hours_saturday, lat, lng, source, active)
            VALUES ('acs', :kind, :external_code, :station_code, :branch_code, :name, :address, :city, :zipcode, :phone, :hours, :hours_saturday, :lat, :lng, 'acs_api', 1)
            ON DUPLICATE KEY UPDATE
                kind = VALUES(kind), station_code = VALUES(station_code), branch_code = VALUES(branch_code),
                name = VALUES(name), address = VALUES(address), city = VALUES(city), zipcode = VALUES(zipcode),
                phone = VALUES(phone), hours = VALUES(hours), hours_saturday = VALUES(hours_saturday),
                lat = VALUES(lat), lng = VALUES(lng), source = 'acs_api'
        ");

        $added = $updated = 0;
        $seen  = [];
        $db->beginTransaction();
        try {
            foreach ($points as $p) {
                $upsert->execute($p);
                $seen[] = $p['external_code'];
                isset($existing[$p['external_code']]) ? $updated++ : $added++;
            }
            $gone = array_diff(array_keys($existing), $seen);
            $deactivated = 0;
            if ($gone) {
                $in = implode(',', array_fill(0, count($gone), '?'));
                $stmt = $db->prepare("UPDATE pickup_points SET active = 0 WHERE carrier = 'acs' AND source = 'acs_api' AND external_code IN ($in)");
                $stmt->execute(array_values($gone));
                $deactivated = $stmt->rowCount();
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return ['added' => $added, 'updated' => $updated, 'deactivated' => $deactivated];
    }
}
