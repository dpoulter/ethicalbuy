-- Links brand owners to their registered Companies House identity, and records
-- the corporate parent where one is on the PSC register.
--
-- Assumes the `owners` table shape in dev/schema.sql.
--
--   mysql -u root -p ethicalbuy < migrations/003_owner_companies.sql
--
-- Note what is deliberately absent: any column for an individual person.
-- The PSC register names real people with partial dates of birth; that is
-- personal data under UK GDPR and has no place on a public consumer site.
-- Only corporate controlling entities are stored.

ALTER TABLE owners
    ADD COLUMN company_number        VARCHAR(20)  NULL,
    ADD COLUMN company_name          VARCHAR(200) NULL,
    ADD COLUMN company_status        VARCHAR(50)  NULL,
    ADD COLUMN incorporated_on       DATE         NULL,
    ADD COLUMN parent_company_name   VARCHAR(200) NULL,
    ADD COLUMN parent_company_number VARCHAR(20)  NULL,
    -- unmatched | confirmed | ambiguous
    ADD COLUMN match_status          VARCHAR(20)  NOT NULL DEFAULT 'unmatched',
    ADD COLUMN match_note            VARCHAR(255) NULL,
    ADD COLUMN source                VARCHAR(50)  NULL,
    ADD COLUMN source_url            VARCHAR(500) NULL,
    ADD COLUMN source_licence        VARCHAR(100) NULL,
    ADD COLUMN retrieved_at          DATETIME     NULL;

CREATE INDEX idx_owners_company_number ON owners (company_number);
CREATE INDEX idx_owners_match_status   ON owners (match_status);

-- Suggestions for a human to confirm. The importer writes here whenever it is
-- not certain, which is most of the time by design.
CREATE TABLE IF NOT EXISTS owner_company_candidates (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    owner_id        INT          NOT NULL,
    company_number  VARCHAR(20)  NOT NULL,
    company_name    VARCHAR(200) NOT NULL,
    company_status  VARCHAR(50)  NULL,
    address_snippet VARCHAR(255) NULL,
    score           TINYINT      NOT NULL DEFAULT 0,
    retrieved_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_owner_candidate (owner_id, company_number),
    KEY idx_candidate_owner (owner_id),
    CONSTRAINT fk_candidate_owner FOREIGN KEY (owner_id)
        REFERENCES owners (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Surface the owner's registered identity to the public brand page.
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
