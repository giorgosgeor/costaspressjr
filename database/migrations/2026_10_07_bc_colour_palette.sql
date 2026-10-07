-- The supplier's (B&C) colour names, as listed per model in the supplier's
-- colours-and-sizes sheet that database/supplier_prices.php now follows.
-- The shop started on a generic 15-colour palette ("Gray", "Maroon", …);
-- those rows stay, because past orders and saved designs point at them.
--
-- Hex values approximate B&C's swatches closely enough for the swatch chips
-- and the tinted mockups. Fine-tune any of them on /admin/colors.
-- INSERT IGNORE: color_name is UNIQUE, so re-running changes nothing.
INSERT IGNORE INTO available_colors (color_name, color_hex, is_active) VALUES
    ('Sport Grey',       '#A8A9AD', 1),
    ('Heather Grey',     '#BDBDBD', 1),
    ('Dark Grey',        '#4A4A4D', 1),
    ('Ash',              '#D4D4CF', 1),
    ('Navy',             '#1B2033', 1),
    ('Light Blue',       '#A9C8E8', 1),
    ('Sky Blue',         '#8CC8EA', 1),
    ('Denim',            '#4D6B8F', 1),
    ('Atoll',            '#009CB0', 1),
    ('Real Turquoise',   '#00A3D3', 1),
    ('Turquoise',        '#30C3C5', 1),
    ('Meta Turquoise',   '#00AFB2', 1),
    ('Blush Mint',       '#BFE4D2', 1),
    ('Pistachio',        '#BAD48F', 1),
    ('Orchid Green',     '#9CC48E', 1),
    ('Apple Green',      '#77BC3F', 1),
    ('Kelly Green',      '#1C8C4B', 1),
    ('Bottle Green',     '#0C4A2C', 1),
    ('Dark Forest',      '#1E3628', 1),
    ('Camo Green',       '#5A5A3B', 1),
    ('Khaki',            '#A89A6E', 1),
    ('Millennial Khaki', '#C8B78D', 1),
    ('Sand',             '#D9C9A8', 1),
    ('Mastic',           '#D8C8A2', 1),
    ('Natural',          '#F1EADB', 1),
    ('Roasted Coffee',   '#5A3D2B', 1),
    ('Gold',             '#F2A900', 1),
    ('Solar Yellow',     '#FFD200', 1),
    ('Yellow Fizz',      '#F5E85C', 1),
    ('Pure Orange',      '#FF6B14', 1),
    ('Pumpkin Orange',   '#F37B21', 1),
    ('Burgundy',         '#6C1D2D', 1),
    ('Fuchsia',          '#D11A7F', 1),
    ('Meta Fuchsia',     '#C7317F', 1),
    ('Millennial Pink',  '#F1C5C3', 1),
    ('Blush Pink',       '#F5C8CE', 1),
    ('Lotus Pink',       '#E7A5B8', 1),
    ('Urban Purple',     '#5A3A80', 1),
    ('Radiant Purple',   '#7A4E9F', 1);
