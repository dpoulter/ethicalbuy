-- The per-product inputs behind each derived dimension score, so the brand
-- page can show how a number such as "Nutrition 7.5" was reached.
--
--   mysql -u root -p ethicalbuy < migrations/005_brand_score_inputs.sql
--
-- One row per product per dimension. A derived score in brand_scores is the
-- mean of its rows' points, rounded to one decimal; the importer writes the
-- score and its inputs in one transaction so the two always agree.
--
-- Curated (hand-entered) scores have no rows here. A derived score with no
-- rows was imported before this table existed; run the importer with
-- --refresh-scores --apply to backfill.
--
-- Grants, matching dev/setup.sh:
--
--   GRANT SELECT                 ON ethicalbuy.brand_score_inputs TO 'ethicalbuy'@'localhost';
--   GRANT SELECT, INSERT, DELETE ON ethicalbuy.brand_score_inputs TO 'ethicalbuy_import'@'localhost';

CREATE TABLE IF NOT EXISTS brand_score_inputs (
    brand_id   INT          NOT NULL,
    -- environment | nutrition | welfare
    dimension  VARCHAR(30)  NOT NULL,
    -- order the products were read in, for stable display
    seq        INT          NOT NULL,
    -- Open Food Facts product code
    ref        VARCHAR(100) NULL,
    -- product name, as published by Open Food Facts
    label      VARCHAR(255) NULL,
    -- what fed the score: a letter grade, or the recognised labels
    input      VARCHAR(255) NULL,
    -- the 1-10 value this product contributed
    points     DECIMAL(3,1) NOT NULL,
    url        VARCHAR(500) NULL,
    PRIMARY KEY (brand_id, dimension, seq),
    CONSTRAINT fk_brand_score_inputs_brand FOREIGN KEY (brand_id)
        REFERENCES brands (id) ON DELETE CASCADE,
    CONSTRAINT ck_brand_score_inputs_range CHECK (points >= 1 AND points <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
