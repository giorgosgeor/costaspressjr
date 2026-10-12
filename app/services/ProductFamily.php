<?php

/**
 * Garment families (t-shirts, hoodies, …), worked out from a product's name.
 * There is no category column, and adding one would mean a migration plus
 * admin UI for a list of under twenty garments; the names say it already.
 *
 * Each family in PAGES has its own landing page under /shop/ (/shop/hoodies):
 * the product picker showing just that family, with its own heading,
 * introduction, title and description (seo.family.<family>.* in app/lang).
 * A family without an entry here (caps, other) is listed under "All" only.
 */
class ProductFamily
{
    /** Family => its page's address under /shop/. */
    public const PAGES = [
        'tshirt'     => 't-shirts',
        'longsleeve' => 'long-sleeves',
        'tank'       => 'tank-tops',
        'vneck'      => 'v-necks',
        'polo'       => 'polos',
        'hoodie'     => 'hoodies',
    ];

    /** The family a product name belongs to. Order matters: a "V-Neck T-Shirt" is a v-neck. */
    public static function of(string $name): string
    {
        $n = strtolower($name);
        if (str_contains($n, 'hood') || str_contains($n, 'sweat')) return 'hoodie';
        if (str_contains($n, 'polo'))                              return 'polo';
        if (str_contains($n, 'tank'))                              return 'tank';
        if (str_contains($n, 'v-neck'))                            return 'vneck';
        if (str_contains($n, 'long sleeve'))                       return 'longsleeve';
        if (str_contains($n, 'cap'))                               return 'cap';
        if (str_contains($n, 'shirt'))                             return 'tshirt';
        return 'other';
    }

    /** The family a /shop/<slug> address names, or null when none does. */
    public static function fromSlug(string $slug): ?string
    {
        $family = array_search($slug, self::PAGES, true);
        return $family === false ? null : $family;
    }

    /** A family's landing page, or null for a family without one. */
    public static function path(string $family): ?string
    {
        return isset(self::PAGES[$family]) ? '/shop/' . self::PAGES[$family] : null;
    }
}
