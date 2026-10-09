<?php

/**
 * The customer's order confirmation email.
 *
 * EU consumer law wants a distance sale confirmed on a "durable medium" — an
 * email counts, a web page doesn't — with who the seller is, what was bought
 * and the cancellation terms, the model withdrawal form included (Consumer
 * Rights Directive, art. 8(7)). Without it, a pre-made design's 14-day right
 * to cancel would run for a year and 14 days instead (art. 10). The terms are
 * the refund policy page's own sentences, so the two can't drift apart.
 *
 * OrderPlacement sends it once, from whichever caller placed the order, in
 * the language the customer checked out in. A failure is logged and never
 * touches the order.
 */
final class OrderConfirmation
{
    public static function send(PDO $db, int $orderId, string $to, string $name, string $locale): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $previous = I18n::locale();
        try {
            I18n::setLocale($locale, false);
            [$subject, $html, $text] = self::compose($db, $orderId, $name);
            // Replies reach the shop rather than a no-reply sender.
            return Mailer::send($to, $subject, $html, $text, Business::email() ?: null);
        } catch (Throwable $e) {
            Log::error('order confirmation email failed', ['order' => $orderId, 'error' => $e->getMessage()]);
            return false;
        } finally {
            I18n::setLocale($previous, false);
        }
    }

    /** @return array{0:string, 1:string, 2:string} subject, HTML body, text body */
    private static function compose(PDO $db, int $orderId, string $name): array
    {
        $stmt = $db->prepare("SELECT id, tracking_token, total_price, shipping_fee, delivery_method, pickup_point FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new RuntimeException("order $orderId not found");
        }
        $stmt = $db->prepare("
            SELECT oi.quantity, oi.size_name, oi.color_name, oi.unit_price, oi.custom_design_fee,
                   p.name AS product_name, d.design_data
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            LEFT JOIN order_item_designs d ON d.order_item_id = oi.id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ");
        $stmt->execute([$orderId]);

        $html = [];
        $text = [];
        // $fragment is HTML: escape plain text with e() before passing it.
        $para = static function (string $fragment) use (&$html, &$text): void {
            $fragment = self::absoluteLinks($fragment);
            $html[] = '<p style="margin:0 0 12px">' . $fragment . '</p>';
            $text[] = self::plain($fragment);
        };
        $heading = static function (string $title) use (&$html, &$text): void {
            $html[] = '<h3 style="margin:28px 0 8px;font-size:16px">' . e($title) . '</h3>';
            $text[] = "\n" . $title . "\n" . str_repeat('-', mb_strlen($title));
        };

        $para(e(I18n::t('email.order.greeting', ['name' => $name])));
        $para(e(I18n::t('email.order.intro')));
        if (!empty($order['tracking_token'])) {
            $track = app_url('/track-order?code=' . urlencode($order['tracking_token']));
            $para(e(I18n::t('email.order.track', ['code' => $order['tracking_token']])) . '<br><a href="' . e($track) . '">' . e($track) . '</a>');
        }

        // What was bought. Pre-made and custom designs have different
        // cancellation terms, so each line says which it is.
        $hasPremade = false;
        $hasCustom  = false;
        $lines = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $design = json_decode((string)($item['design_data'] ?? ''), true);
            if (is_array($design) && ($design['type'] ?? '') === 'premade') {
                $hasPremade = true;
                $label = I18n::t('email.order.design_premade', ['name' => (string)($design['premade_design_name'] ?? '')]);
            } else {
                $hasCustom = true;
                $label = I18n::t('email.order.design_custom');
            }
            $qty     = (int)$item['quantity'];
            $details = implode(', ', array_filter([(string)$item['size_name'], (string)$item['color_name']], 'strlen'));
            $lines[] = e($qty . ' × ' . ($item['product_name'] ?? '') . ($details !== '' ? " ($details)" : '')
                . ' — ' . $label . ' — ' . money(((float)$item['unit_price'] + (float)$item['custom_design_fee']) * $qty));
        }
        if ((float)$order['shipping_fee'] > 0) {
            $lines[] = e(I18n::t('email.order.pickup_fee') . ': ' . money($order['shipping_fee']));
        }
        $lines[] = '<strong>' . e(I18n::t('email.order.total') . ': ' . money($order['total_price'])) . '</strong>';
        $heading(I18n::t('email.order.items_h'));
        $para(implode('<br>', $lines));

        $heading(I18n::t('email.order.collect_h'));
        $point = $order['pickup_point'] ? json_decode($order['pickup_point'], true) : null;
        if ($order['delivery_method'] === Pickup::ACS && is_array($point)) {
            $where = implode(', ', array_filter([(string)($point['name'] ?? ''), (string)($point['address'] ?? ''), (string)($point['city'] ?? '')], 'strlen'));
            $para(e(I18n::t('email.order.collect_acs', ['point' => $where])));
        } else {
            $para(e(I18n::t('email.order.collect_store')));
        }

        // The cancellation terms that apply to this order, as on /returns.
        $heading(I18n::t('email.order.returns_h'));
        if ($hasPremade) {
            $para(I18n::t('info.returns.p1'));
            $para(I18n::t('info.returns.p1_how'));
            $para(e(I18n::t('info.returns.p1_return')));
            $para(e(I18n::t('info.returns.p1_refund')));
        }
        if ($hasCustom) {
            $para(I18n::t('info.returns.p_custom'));
        }
        $para(I18n::t('info.returns.p2'));
        $para(I18n::t('info.returns.p4'));
        if ($hasPremade) {
            $heading(I18n::t('info.returns.h_form'));
            $para(e(I18n::t('info.returns.form_intro')));
            $para(implode('<br>', array_map('e', WithdrawalForm::lines())));
        }

        // Who sold it (Business, from .env).
        $b = Business::details();
        $seller = array_filter([
            $b['name'],
            implode(', ', $b['address']),
            $b['email'],
            $b['registration'] !== '' ? I18n::t('footer.reg_no', ['no' => $b['registration']]) : '',
            $b['vat'] !== '' ? I18n::t('footer.vat_no', ['no' => $b['vat']]) : '',
        ], 'strlen');
        if ($seller) {
            $heading(I18n::t('email.order.seller_h'));
            $para(implode('<br>', array_map('e', $seller)));
        }

        $links = [];
        foreach (['/terms' => 'footer.terms', '/returns' => 'footer.returns', '/privacy' => 'footer.privacy'] as $path => $key) {
            $links[] = '<a href="' . e(app_url($path)) . '">' . e(I18n::t($key)) . '</a>';
        }
        $para(e(I18n::t('email.order.policies')) . ' ' . implode(' · ', $links));
        $para(e(I18n::t('email.order.signoff')) . '<br>' . e(I18n::t('email.order.team', ['brand' => I18n::t('site.brand')])));

        return [
            I18n::t('email.order.subject', ['id' => (int)$order['id']]),
            '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.5;color:#16130F;max-width:600px">'
                . implode('', $html) . '</div>',
            trim(implode("\n\n", $text)) . "\n",
        ];
    }

    /** The policy sentences link to "/contact" and the like; an email needs the full address. */
    private static function absoluteLinks(string $html): string
    {
        return str_replace('href="/', 'href="' . app_url('/'), $html);
    }

    /** The text version of a fragment: links as "label (address)", tags dropped. */
    private static function plain(string $html): string
    {
        $html = preg_replace_callback('/<a\s[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/si', static function (array $m): string {
            $url   = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
            $label = trim(strip_tags($m[2]));
            return $label === '' || html_entity_decode($label, ENT_QUOTES, 'UTF-8') === $url ? $url : "$label ($url)";
        }, $html);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    }
}
