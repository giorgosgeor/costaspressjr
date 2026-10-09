<?php

/**
 * Who runs the shop, from .env: shown on the contact page, in the footer and
 * in the Terms of Service and Privacy Policy.
 *
 * EU law requires a web shop to name the trader: legal name, a geographic
 * address and an email address (E-Commerce Directive, art. 5; the GDPR asks
 * the same of a privacy policy), plus the company registration and VAT
 * numbers where there are any. ProductionCheck refuses to start without the
 * first three, and anything left empty is simply not shown, so nothing
 * invented reaches a customer (the contact page used to show a made-up phone
 * number).
 *
 * Multi-line values (address, opening hours) separate lines with "|".
 */
final class Business {
    /** The .env keys production can't do without. */
    public const REQUIRED = ['BUSINESS_NAME', 'BUSINESS_ADDRESS', 'BUSINESS_EMAIL'];

    /** Legal name of the person or company that sells, e.g. "Costas Press Ltd". */
    public static function name(): string {
        return self::get('BUSINESS_NAME');
    }

    /** @return string[] the address, one line per entry */
    public static function address(): array {
        return self::lines(self::get('BUSINESS_ADDRESS'));
    }

    /** The public email for customers; the contact form's inbox if unset. */
    public static function email(): string {
        $email = self::get('BUSINESS_EMAIL') ?: self::get('CONTACT_EMAIL');
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    public static function phone(): string {
        return self::get('BUSINESS_PHONE');
    }

    /** @return string[] opening hours, one line per entry */
    public static function hours(): array {
        return self::lines(self::get('BUSINESS_HOURS'));
    }

    /** Company registration number, e.g. "HE 123456"; empty for a sole trader without one. */
    public static function registration(): string {
        return self::get('BUSINESS_REG_NO');
    }

    public static function vat(): string {
        return self::get('BUSINESS_VAT_NO');
    }

    /** "tel:" form of the phone number: digits and a leading +. */
    public static function phoneHref(): string {
        return 'tel:' . preg_replace('/[^\d+]/', '', self::phone());
    }

    /**
     * Everything at once, for templates (the controller passes it in).
     * @return array{name:string,address:string[],email:string,phone:string,phoneHref:string,hours:string[],registration:string,vat:string}
     */
    public static function details(): array {
        return [
            'name'         => self::name(),
            'address'      => self::address(),
            'email'        => self::email(),
            'phone'        => self::phone(),
            'phoneHref'    => self::phoneHref(),
            'hours'        => self::hours(),
            'registration' => self::registration(),
            'vat'          => self::vat(),
        ];
    }

    /** @return string[] the REQUIRED keys that are empty */
    public static function missing(): array {
        return array_values(array_filter(self::REQUIRED, static fn($key) => match ($key) {
            'BUSINESS_EMAIL' => self::email() === '',
            default          => self::get($key) === '',
        }));
    }

    private static function get(string $key): string {
        return trim((string)Env::get($key, ''));
    }

    private static function lines(string $value): array {
        return array_values(array_filter(array_map('trim', explode('|', $value)), 'strlen'));
    }
}
