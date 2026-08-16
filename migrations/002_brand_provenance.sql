-- Records where each brand's facts came from, so the site can attribute its
-- sources, satisfy share-alike licences such as ODbL, and re-check stale rows.
--
-- Assumes the `brands` table shape in dev/schema.sql. If your production
-- schema differs, adapt the column names -- the intent is what matters:
-- every imported fact carries a source, a licence and a retrieval date.
--
--   mysql -u root -p ethicalbuy < migrations/002_brand_provenance.sql

ALTER TABLE brands
    ADD COLUMN certifications VARCHAR(255) NULL AFTER availability,
    ADD COLUMN source         VARCHAR(50)  NULL AFTER certifications,
    ADD COLUMN source_ref     VARCHAR(100) NULL AFTER source,
    ADD COLUMN source_url     VARCHAR(500) NULL AFTER source_ref,
    ADD COLUMN source_licence VARCHAR(100) NULL AFTER source_url,
    ADD COLUMN retrieved_at   DATETIME     NULL AFTER source_licence;

CREATE INDEX idx_brands_source ON brands (source);

-- Expose the new fields to the application.
CREATE OR REPLACE VIEW brand_v AS
SELECT
    b.name  AS brand,
    c.name  AS category,
    b.type  AS type,
    o.name  AS owner,
    b.notes AS notes,
    b.availability   AS availability,
    b.certifications AS certifications,
    b.source         AS source,
    b.source_url     AS source_url,
    b.source_licence AS source_licence,
    b.retrieved_at   AS retrieved_at,
    b.rating AS rating
FROM brands b
LEFT JOIN categories c ON c.id = b.category_id
LEFT JOIN owners     o ON o.id = b.owner_id;
