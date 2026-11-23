-- Product photos, hotlinked from Open Food Facts.
--
--   mysql -u root -p ethicalbuy < migrations/007_product_images.sql
--
-- We store only the URL of Open Food Facts' small product image; visitors'
-- browsers load it directly from Open Food Facts. No image is ever
-- downloaded, copied, resized or cached by this site or its importer.
-- Photos are by Open Food Facts contributors under CC BY-SA 4.0, credited
-- on the product page.
--
-- A brand's photo is not stored: it is the image of the brand's most
-- scanned product that has one (see brand_images() in includes/products.php).
--
-- No new grants: products is already readable by the site and writable by
-- the importer.

ALTER TABLE products
    -- Open Food Facts image_small_url; only https://images.openfoodfacts.org/
    -- or https://images.openfoodfacts.net/ URLs are kept, anything else NULL
    ADD COLUMN image_url VARCHAR(500) NULL AFTER source_url,
    -- Open Food Facts unique_scans_n: how many people have scanned it
    ADD COLUMN scans INT NULL AFTER image_url,
    ADD KEY idx_products_brand_scans (brand_id, scans);
