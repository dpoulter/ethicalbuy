-- Local development seed data.
--
-- Every brand and company below is INVENTED. Do not treat any of it as a real
-- ethical assessment, and do not ship it. Real companies are deliberately
-- avoided so that no fabricated rating can be mistaken for a real one.
--
-- The rows are chosen to exercise the app's edge cases:
--   * every rating band, plus unrated (NULL) brands
--   * a brand with no category and a brand with no owner
--   * apostrophes, double quotes and non-ASCII characters in names
--   * a category with a single brand, and one with many

INSERT INTO owners (name) VALUES
    ('Northwind Foods Group'),
    ('Meridian Consumer Holdings'),
    ('Bramblewood Co-operative'),
    ('Halcyon Brands International'),
    ("O'Donnell Family Farms"),
    ('Kestrel & Fen Ltd');

INSERT INTO categories (name) VALUES
    ('Dairy'),
    ('Fruit & Vegetables'),
    ('Store Cupboard'),
    ("Baby's Food"),
    ('Household');

INSERT INTO brands (name, category_id, owner_id, type, notes, availability, rating) VALUES
-- Dairy
    ('Valley Fresh',
     (SELECT id FROM categories WHERE name = 'Dairy'),
     (SELECT id FROM owners WHERE name = 'Bramblewood Co-operative'),
     'Milk',
     'Co-operatively owned. Publishes farm-level welfare audits and pays above the regional milk price.',
     'Supermarkets', 9.5),

    ('Creamery Gold',
     (SELECT id FROM categories WHERE name = 'Dairy'),
     (SELECT id FROM owners WHERE name = 'Northwind Foods Group'),
     'Butter',
     'Parent group has faced repeated criticism over dairy sourcing and has not published a supplier list.',
     'Supermarkets', 3.0),

    ('Pastures "Best"',
     (SELECT id FROM categories WHERE name = 'Dairy'),
     (SELECT id FROM owners WHERE name = 'Meridian Consumer Holdings'),
     'Cheese',
     'Mixed picture: good animal welfare scores, but the parent group scores poorly on tax transparency.',
     'Supermarkets', 5.5),

    ('Highfield Dairy',
     (SELECT id FROM categories WHERE name = 'Dairy'),
     (SELECT id FROM owners WHERE name = "O'Donnell Family Farms"),
     'Yoghurt',
     'Family owned and single-site. We have not completed an assessment yet.',
     'Independents', NULL),

-- Fruit & Vegetables
    ('Greenfields Organic',
     (SELECT id FROM categories WHERE name = 'Fruit & Vegetables'),
     (SELECT id FROM owners WHERE name = 'Bramblewood Co-operative'),
     'Mixed produce',
     'Certified organic, seasonal UK sourcing, returns a fixed share of profit to growers.',
     'Supermarkets', 10.0),

    ('Sunburst Citrus',
     (SELECT id FROM categories WHERE name = 'Fruit & Vegetables'),
     (SELECT id FROM owners WHERE name = 'Halcyon Brands International'),
     'Citrus',
     'Long-running disputes over pay and conditions at contracted packhouses.',
     'Supermarkets', 2.0),

    ('Rootstock & Vine',
     (SELECT id FROM categories WHERE name = 'Fruit & Vegetables'),
     (SELECT id FROM owners WHERE name = 'Kestrel & Fen Ltd'),
     'Salad',
     'Small supplier, improving water stewardship, limited public reporting.',
     'Independents', 7.0),

    ('Café Verde',
     (SELECT id FROM categories WHERE name = 'Fruit & Vegetables'),
     (SELECT id FROM owners WHERE name = 'Kestrel & Fen Ltd'),
     'Avocados',
     'Non-ASCII name on purpose, to prove UTF-8 handling end to end. Sourcing under review.',
     'Online only', 6.0),

-- Store Cupboard
    ('Amber Mill',
     (SELECT id FROM categories WHERE name = 'Store Cupboard'),
     (SELECT id FROM owners WHERE name = 'Northwind Foods Group'),
     'Flour',
     'Parent group lobbies against packaging reform; the brand itself has cut plastic use substantially.',
     'Supermarkets', 4.0),

    ('Saltmarsh Pantry',
     (SELECT id FROM categories WHERE name = 'Store Cupboard'),
     (SELECT id FROM owners WHERE name = 'Meridian Consumer Holdings'),
     'Tinned goods',
     'Reasonable supply chain disclosure. No living-wage commitment beyond the UK.',
     'Supermarkets', 7.5),

    ('Ironbridge Preserves',
     (SELECT id FROM categories WHERE name = 'Store Cupboard'),
     NULL,
     'Jams',
     'Ownership currently unclear following a 2024 restructure. Left blank rather than guessed.',
     'Independents', 8.0),

-- Baby's Food (apostrophe in the category name, on purpose)
    ('Little Meadow',
     (SELECT id FROM categories WHERE name = "Baby's Food"),
     (SELECT id FROM owners WHERE name = 'Bramblewood Co-operative'),
     'Purees',
     'Complies with marketing codes for infant food and publishes ingredient origins.',
     'Supermarkets', 9.0),

    ("Baby's Own",
     (SELECT id FROM categories WHERE name = "Baby's Food"),
     (SELECT id FROM owners WHERE name = 'Halcyon Brands International'),
     'Formula',
     'Parent group repeatedly criticised over infant formula marketing in lower-income countries.',
     'Supermarkets', 1.0),

-- Household
    ('Brightwash',
     (SELECT id FROM categories WHERE name = 'Household'),
     (SELECT id FROM owners WHERE name = 'Meridian Consumer Holdings'),
     'Detergent',
     'Concentrated refills widely available. Animal testing policy is unclear for non-EU markets.',
     'Supermarkets', 6.5),

-- Deliberately uncategorised, to prove NULL categories are not dropped
    ('Unsorted Sundries',
     NULL,
     (SELECT id FROM owners WHERE name = 'Kestrel & Fen Ltd'),
     'Assorted',
     'No category assigned. Should appear under "Uncategorised" rather than vanishing.',
     'Independents', 5.0),

    ('Quiet Harbour',
     NULL,
     NULL,
     'Assorted',
     'No category, no owner and no rating: the emptiest row the UI has to survive.',
     NULL, NULL);

-- Dimension scores. Deliberately incomplete: several brands are scored on
-- only some dimensions, and two on none at all, so the coverage warnings and
-- the "not assessed" path are exercised rather than assumed.
INSERT INTO brand_scores (brand_id, dimension, score, note, source) VALUES
    ((SELECT id FROM brands WHERE name='Valley Fresh'), 'environment', 8.0, 'Local sourcing, returnable glass.', 'curated'),
    ((SELECT id FROM brands WHERE name='Valley Fresh'), 'welfare', 9.5, 'Pasture-based, audited welfare.', 'curated'),
    ((SELECT id FROM brands WHERE name='Valley Fresh'), 'nutrition', 6.0, 'Whole milk range.', 'curated'),
    ((SELECT id FROM brands WHERE name='Valley Fresh'), 'ownership', 10.0, 'Co-operatively owned.', 'curated'),

    ((SELECT id FROM brands WHERE name='Greenfields Organic'), 'environment', 10.0, 'Certified organic, seasonal.', 'curated'),
    ((SELECT id FROM brands WHERE name='Greenfields Organic'), 'welfare', 9.0, 'Organic standards.', 'curated'),
    ((SELECT id FROM brands WHERE name='Greenfields Organic'), 'nutrition', 9.0, 'Unprocessed produce.', 'curated'),
    ((SELECT id FROM brands WHERE name='Greenfields Organic'), 'ownership', 9.0, 'Co-operative, profit share to growers.', 'curated'),

    -- strong on diet, weak on carbon: ranks very differently by priority
    ((SELECT id FROM brands WHERE name='Café Verde'), 'welfare', 9.0, 'Vegan range, Rainforest Alliance.', 'curated'),
    ((SELECT id FROM brands WHERE name='Café Verde'), 'environment', 3.0, 'Air-freighted, high water use.', 'curated'),
    ((SELECT id FROM brands WHERE name='Café Verde'), 'nutrition', 8.0, 'Whole fruit.', 'curated'),

    -- the mirror image: low welfare, good carbon
    ((SELECT id FROM brands WHERE name='Amber Mill'), 'environment', 8.5, 'Low-impact milling, plastic reduced.', 'curated'),
    ((SELECT id FROM brands WHERE name='Amber Mill'), 'welfare', 3.0, 'No welfare certification.', 'curated'),
    ((SELECT id FROM brands WHERE name='Amber Mill'), 'nutrition', 7.0, 'Wholegrain range.', 'curated'),
    ((SELECT id FROM brands WHERE name='Amber Mill'), 'ownership', 2.0, 'Owned by a large group.', 'curated'),

    ((SELECT id FROM brands WHERE name='Creamery Gold'), 'environment', 3.0, 'High-intensity dairy.', 'curated'),
    ((SELECT id FROM brands WHERE name='Creamery Gold'), 'nutrition', 2.0, 'High saturated fat.', 'curated'),
    ((SELECT id FROM brands WHERE name='Creamery Gold'), 'ownership', 2.0, 'Large listed parent.', 'curated'),

    ((SELECT id FROM brands WHERE name='Little Meadow'), 'welfare', 8.0, 'Organic ingredients.', 'curated'),
    ((SELECT id FROM brands WHERE name='Little Meadow'), 'nutrition', 9.0, 'No added sugar or salt.', 'curated'),
    ((SELECT id FROM brands WHERE name='Little Meadow'), 'ownership', 9.0, 'Co-operatively owned.', 'curated'),

    ((SELECT id FROM brands WHERE name='Brightwash'), 'environment', 7.0, 'Concentrated refills.', 'curated'),
    ((SELECT id FROM brands WHERE name='Brightwash'), 'welfare', 4.0, 'Animal testing policy unclear.', 'curated'),

    ((SELECT id FROM brands WHERE name='Rootstock & Vine'), 'environment', 7.0, 'Improving water stewardship.', 'curated'),
    ((SELECT id FROM brands WHERE name='Rootstock & Vine'), 'ownership', 8.0, 'Small independent supplier.', 'curated'),

    ((SELECT id FROM brands WHERE name='Saltmarsh Pantry'), 'nutrition', 6.0, 'Mixed range, some high salt.', 'curated'),
    ((SELECT id FROM brands WHERE name='Saltmarsh Pantry'), 'ownership', 4.0, 'Mid-size group.', 'curated'),

    ((SELECT id FROM brands WHERE name='Baby''s Own'), 'nutrition', 5.0, 'Meets compositional standards.', 'curated'),
    ((SELECT id FROM brands WHERE name='Baby''s Own'), 'ownership', 1.0, 'Large multinational parent.', 'curated'),

    ((SELECT id FROM brands WHERE name='Ironbridge Preserves'), 'ownership', 7.0, 'Independent, ownership under review.', 'curated'),
    ((SELECT id FROM brands WHERE name='Sunburst Citrus'), 'ownership', 2.0, 'Large multinational parent.', 'curated');
