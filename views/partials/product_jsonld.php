<?php
/**
 * The product a page sells, for search engines: a schema.org Product with one
 * Offer, so results can show the price and delivery, and Google Shopping can
 * list it. Every fact is one the shop states elsewhere: the delivery page
 * (ACS, Cyprus only, 3–5 working days to print, 2–5 in transit), the returns
 * page, and the fee checkout charges (Pickup::acsFee()).
 *
 * $ld = [
 *   'name', 'description', 'sku',
 *   'image'      => stored image path (web_path() form is fine),
 *   'price'      => single-piece price in EUR, the lowest over colours and sizes,
 *   'highPrice'  => the highest, when it differs (the offer is then a range),
 *   'returnable' => true for pre-made designs (14 days from collection),
 *                   false for custom prints (made for one customer: no returns),
 * ]
 */
$ldEuro  = static fn($v): string => number_format((float)$v, 2, '.', '');
$ldRange = isset($ld['highPrice']) && $ld['highPrice'] > $ld['price'] + 0.004;
$ldOffer = [
    '@type'         => $ldRange ? 'AggregateOffer' : 'Offer',
    'url'           => app_url(I18n::url((string)strtok($_SERVER['REQUEST_URI'] ?? '/', '?'))),
] + ($ldRange
    ? ['lowPrice' => $ldEuro($ld['price']), 'highPrice' => $ldEuro($ld['highPrice'])]
    : ['price' => $ldEuro($ld['price'])]
) + [
    'priceCurrency' => 'EUR',
    'availability'  => 'https://schema.org/InStock',
    'seller'        => ['@type' => 'Organization', 'name' => t('site.brand', false)],
    'hasMerchantReturnPolicy' => ['@type' => 'MerchantReturnPolicy', 'applicableCountry' => 'CY'] + ($ld['returnable']
        ? ['returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow', 'merchantReturnDays' => 14]
        : ['returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted']),
];
if (($ldFee = Pickup::acsFee()) !== null) {
    $ldOffer['shippingDetails'] = [
        '@type'               => 'OfferShippingDetails',
        'shippingRate'        => ['@type' => 'MonetaryAmount', 'value' => number_format($ldFee, 2, '.', ''), 'currency' => 'EUR'],
        'shippingDestination' => ['@type' => 'DefinedRegion', 'addressCountry' => 'CY'],
        'deliveryTime'        => [
            '@type'        => 'ShippingDeliveryTime',
            'handlingTime' => ['@type' => 'QuantitativeValue', 'minValue' => 3, 'maxValue' => 5, 'unitCode' => 'DAY'],
            'transitTime'  => ['@type' => 'QuantitativeValue', 'minValue' => 2, 'maxValue' => 5, 'unitCode' => 'DAY'],
        ],
    ];
}
?>
<?= View::jsonLd([
    '@type'       => 'Product',
    'name'        => (string)$ld['name'],
    'description' => (string)$ld['description'],
    'sku'         => (string)$ld['sku'],
    'image'       => app_url(web_path($ld['image'])),
    'offers'      => $ldOffer,
]) ?>
