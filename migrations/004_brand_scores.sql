-- Per-dimension scores, so a rating can be computed from the reader's own
-- priorities rather than handed down as a single editorial verdict.
--
--   mysql -u root -p ethicalbuy < migrations/004_brand_scores.sql
--
-- One row per brand per dimension. An absent row means "we don't know", which
-- is deliberately different from a score of zero: the scoring engine excludes
-- unknowns and tells the reader how much of their priority list it could
-- actually assess.
--
-- The editorial 1-10 in brands.rating is NOT copied here. It is injected as
-- the "editorial" dimension at read time, so there is still one place to edit
-- it and no chance of the two drifting apart.

CREATE TABLE IF NOT EXISTS brand_scores (
    brand_id     INT          NOT NULL,
    -- environment | nutrition | welfare | ownership
    dimension    VARCHAR(30)  NOT NULL,
    -- 1-10, same scale as the editorial rating
    score        DECIMAL(3,1) NOT NULL,
    -- why this score, shown to the reader
    note         VARCHAR(255) NULL,
    source       VARCHAR(50)  NULL,
    source_url   VARCHAR(500) NULL,
    retrieved_at DATETIME     NULL,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
                                       ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (brand_id, dimension),
    KEY idx_brand_scores_dimension (dimension),
    CONSTRAINT fk_brand_scores_brand FOREIGN KEY (brand_id)
        REFERENCES brands (id) ON DELETE CASCADE,
    CONSTRAINT ck_brand_scores_range CHECK (score >= 1 AND score <= 10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expose the brand id so scores can be joined to what the public site reads.
CREATE OR REPLACE VIEW brand_v AS
SELECT
    b.id    AS brand_id,
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
    o.company_number        AS owner_company_number,
    o.company_name          AS owner_company_name,
    o.company_status        AS owner_company_status,
    o.parent_company_name   AS owner_parent_name,
    o.parent_company_number AS owner_parent_number,
    o.source_url            AS owner_source_url,
    b.rating AS rating
FROM brands b
LEFT JOIN categories c ON c.id = b.category_id
LEFT JOIN owners     o ON o.id = b.owner_id;
