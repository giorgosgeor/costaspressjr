<?php

/**
 * ACS Courier REST web services — only the part the shop needs: the list of
 * ACS points in Cyprus a customer can collect from.
 *
 * Written from "ACS Rest API Web Services – June 2024" (acscourier.net). Every
 * call is a POST of {ACSAlias, ACSInputParameters} to one URL, with the API key
 * in an ACSApiKey header and the company/user credentials in the parameters.
 * ACS issues all five values to business customers; until they are in .env
 * this class reports itself unconfigured and points are managed by hand.
 */
class AcsClient
{
    private const URL = 'https://webservices.acscourier.net/ACSRestServices/api/ACSAutoRest';

    /**
     * ACS_SHOP_KIND values that return anything for Cyprus (API doc p.26) and
     * what we call them. 2, 3, 5 and 7 are empty for CY.
     */
    public const CY_KINDS = [
        1 => 'store',   // central ACS stores
        4 => 'shop',    // "shop in a shop" counters
        8 => 'locker',  // Smartpoints with parcel locker (parcels up to 6 kg)
    ];

    public static function configured(): bool
    {
        foreach (['ACS_API_KEY', 'ACS_COMPANY_ID', 'ACS_COMPANY_PASSWORD', 'ACS_USER_ID', 'ACS_USER_PASSWORD'] as $k) {
            if (trim((string)Env::get($k, '')) === '') {
                return false;
            }
        }
        return true;
    }

    /** @return array decoded response */
    public static function call(string $alias, array $params): array
    {
        if (!self::configured()) {
            throw new RuntimeException('ACS web services are not configured');
        }

        $body = json_encode([
            'ACSAlias'            => $alias,
            'ACSInputParameters'  => $params + [
                'Company_ID'       => Env::get('ACS_COMPANY_ID'),
                'Company_Password' => Env::get('ACS_COMPANY_PASSWORD'),
                'User_ID'          => Env::get('ACS_USER_ID'),
                'User_Password'    => Env::get('ACS_USER_PASSWORD'),
            ],
        ]);

        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'ACSApiKey: ' . Env::get('ACS_API_KEY'),
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err  = curl_errno($ch);

        if ($err || $raw === false) {
            throw new RuntimeException('ACS connection failed');
        }
        // 403 = wrong/missing key or alias not granted to it; 406 = over the
        // default 10 calls per second.
        if ($code !== 200) {
            throw new RuntimeException("ACS returned HTTP $code");
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('ACS returned invalid JSON');
        }
        if (!empty($data['ACSExecution_HasError'])) {
            throw new RuntimeException('ACS error: ' . ($data['ACSExecutionErrorMessage'] ?? 'unknown'));
        }
        return $data;
    }

    /**
     * Every ACS point in Cyprus, normalised to pickup_points columns.
     *
     * @return list<array<string,mixed>>
     */
    public static function cyprusPoints(): array
    {
        $points = [];
        foreach (self::CY_KINDS as $kindId => $kind) {
            $res = self::call('ACS_Stations', [
                'language'            => 'EN',
                'ACS_SHOP_COUNTRY_ID' => 'CY',
                'ACS_SHOP_KIND'       => $kindId,
            ]);
            // The documentation spells the envelope both ways.
            $out  = $res['ACSOutputResponce'] ?? $res['ACSOutputResponse'] ?? [];
            $rows = $out['ACSTableOutput']['Table_Data'] ?? [];

            foreach ($rows as $r) {
                $lat = (float)($r['ACS_SHOP_LAT'] ?? 0);
                $lng = (float)($r['ACS_SHOP_LONG'] ?? 0);
                $code = trim((string)($r['ACS_SHOP_ID_CODE'] ?? ''));
                // A point nobody can find on the map, or can't be addressed on
                // a voucher, is no use at checkout.
                if ($code === '' || !$lat || !$lng) {
                    continue;
                }
                $name = trim((string)($r['ACS_SHOP_DESCR'] ?? $r['ACS_SHOP_NAME'] ?? $r['ACS_SHOP_STATION_DESCR'] ?? ''));
                $points[] = [
                    'kind'           => $kind,
                    'external_code'  => $code,
                    'station_code'   => (string)($r['ACS_SHOP_STATION_ID'] ?? ''),
                    'branch_code'    => (string)($r['ACS_SHOP_BRANCH_ID'] ?? ''),
                    'name'           => mb_substr($name !== '' ? $name : 'ACS ' . $code, 0, 150),
                    'address'        => mb_substr(trim((string)($r['ACS_SHOP_ADDRESS'] ?? '')), 0, 255),
                    'city'           => mb_substr(trim((string)($r['ACS_SHOP_AREA_DESCR'] ?? '')), 0, 100),
                    'zipcode'        => mb_substr(trim((string)($r['ACS_SHOP_ZIPCODE'] ?? '')), 0, 10),
                    'phone'          => mb_substr(trim((string)($r['ACS_SHOP_PHONES'] ?? '')), 0, 80),
                    'hours'          => mb_substr(trim((string)($r['ACS_SHOP_WORKING_HOURS'] ?? '')), 0, 120),
                    'hours_saturday' => mb_substr(trim((string)($r['ACS_SHOP_WORKING_HOURS_SATURDAY'] ?? '')), 0, 120),
                    'lat'            => $lat,
                    'lng'            => $lng,
                ];
            }
        }
        return $points;
    }
}
