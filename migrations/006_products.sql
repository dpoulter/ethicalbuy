-- Products as a level below brands, imported from Open Food Facts.
--
--   mysql -u root -p ethicalbuy < migrations/006_products.sql
--
-- Each product belongs to one brand and carries its own scores on the
-- dimensions Open Food Facts can inform (nutrition, environment, welfare).
-- A brand's derived score for a dimension is the mean of its products'
-- scores; the importer writes products, product scores and the brand's
-- derived scores in one transaction so they cannot disagree.
--
-- brand_score_inputs (005) is superseded: the brand page reads its working
-- from product_scores and only falls back to brand_score_inputs for a brand
-- with no products yet. It can be dropped once every brand has products.
--
-- Grants, matching dev/setup.sh:
--
--   GRANT SELECT ON ethicalbuy.products       TO 'ethicalbuy'@'localhost';
--   GRANT SELECT ON ethicalbuy.product_scores TO 'ethicalbuy'@'localhost';
--   GRANT SELECT, INSERT, UPDATE, DELETE ON ethicalbuy.products       TO 'ethicalbuy_import'@'localhost';
--   GRANT SELECT, INSERT, UPDATE, DELETE ON ethicalbuy.product_scores TO 'ethicalbuy_import'@'localhost';

CREATE TABLE IF NOT EXISTS products (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    brand_id     INT          NOT NULL,
    -- Open Food Facts barcode
    code         VARCHAR(100) NOT NULL,
    -- as published by Open Food Facts
    name         VARCHAR(255) NULL,
    -- lower case, no accents, apostrophes or punctuation, "&" and "and"
    -- dropped: what search matches against (see search_key())
    name_key     VARCHAR(255) NOT NULL DEFAULT '',
    category     VARCHAR(100) NULL,
    -- recognised certification names, comma separated
    labels       VARCHAR(255) NULL,
    source_url   VARCHAR(500) NULL,
    retrieved_at DATETIME     NULL,
    UNIQUE KEY uq_products_code (code),
    KEY idx_products_brand (brand_id),
    KEY idx_products_name_key (name_key(100)),
    FULLTEXT KEY ft_products_name_key (name_key),
    CONSTRAINT fk_products_brand FOREIGN KEY (brand_id)
        REFERENCES brands (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_scores (
    product_id INT          NOT NULL,
    -- environment | nutrition | welfare
    dimension  VARCHAR(30)  NOT NULL,
    score      DECIMAL(3,1) NOT NULL,
    -- what produced it: a letter grade, or the recognised labels
    input      VARCHAR(255) NULL,
    PRIMARY KEY (product_id, dimension),
    KEY idx_product_scores_dimension (dimension),
    CONSTRAINT fk_product_scores_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON DELETE CASCADE,
    CONSTRAINT ck_product_scores_range CHECK (score >= 1 AND score <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
