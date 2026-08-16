-- Local development schema.
--
-- This is NOT a copy of production. It is a plausible reconstruction of the
-- tables behind brand_v, inferred from the columns the app reads, so the site
-- can be run and tested locally. If your production schema differs, replace
-- this file with a dump of the real one:
--
--   mysqldump --no-data ethicalbuy > dev/schema.sql
--
-- What matters to the application is only that a relation named brand_v
-- exposes: brand, category, type, owner, notes, availability, rating.

DROP VIEW IF EXISTS brand_v;
DROP TABLE IF EXISTS brands;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS owners;

CREATE TABLE owners (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    UNIQUE KEY uq_owners_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE brands (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(150)  NOT NULL,
    category_id  INT           NULL,
    owner_id     INT           NULL,
    type         VARCHAR(100)  NULL,
    notes        TEXT          NULL,
    availability VARCHAR(100)  NULL,
    -- 1..10, or NULL when the brand has not been assessed yet
    rating       DECIMAL(3,1)  NULL,
    updated_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
                                        ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_brands_name (name),
    KEY idx_brands_category (category_id),
    KEY idx_brands_rating (rating),
    CONSTRAINT fk_brands_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_brands_owner FOREIGN KEY (owner_id)
        REFERENCES owners (id) ON DELETE SET NULL,
    CONSTRAINT ck_brands_rating CHECK (rating IS NULL OR (rating >= 1 AND rating <= 10))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The only relation the application reads.
CREATE VIEW brand_v AS
SELECT
    b.name  AS brand,
    c.name  AS category,
    b.type  AS type,
    o.name  AS owner,
    b.notes AS notes,
    b.availability AS availability,
    b.rating AS rating
FROM brands b
LEFT JOIN categories c ON c.id = b.category_id
LEFT JOIN owners     o ON o.id = b.owner_id;
