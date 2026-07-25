-- The application uses hyphenated placement values everywhere ('left-sleeve',
-- 'right-sleeve') — see custom_design_uploads.view_placement and the validViews
-- lists in the controllers. But cart_item_uploads.placement and
-- order_item_uploads.placement were defined with UNDERSCORE enum members
-- ('left_sleeve','right_sleeve'). Under STRICT_TRANS_TABLES, inserting a
-- hyphenated sleeve value raises "Data truncated for column 'placement'",
-- which aborts the whole checkout transaction. Align the enums to the
-- hyphenated values the app actually writes. Both tables are expected to be
-- empty of sleeve rows at migration time; 'front'/'back' values are unaffected.
ALTER TABLE cart_item_uploads
    MODIFY placement ENUM('front','back','left-sleeve','right-sleeve') NOT NULL DEFAULT 'front';
ALTER TABLE order_item_uploads
    MODIFY placement ENUM('front','back','left-sleeve','right-sleeve') NOT NULL DEFAULT 'front';
