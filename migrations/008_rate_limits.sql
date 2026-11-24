-- Counters for rate limiting and the admin login throttle.
--
--   mysql -u root -p ethicalbuy < migrations/008_rate_limits.sql
--
-- One row per (bucket, visitor, minute). The visitor is a keyed SHA-256 of
-- the IP address, never the address itself. Rows are short-lived: the site
-- deletes anything older than two days as it goes, and
-- bin/purge-messages.php clears them too.
--
-- Grants, matching dev/setup.sh:
--
--   GRANT SELECT, INSERT, UPDATE, DELETE ON ethicalbuy.rate_limits TO 'ethicalbuy'@'localhost';

CREATE TABLE IF NOT EXISTS rate_limits (
    -- what is being counted: browse, contact, admin_fail, admin_lock
    bucket       VARCHAR(32)  NOT NULL,
    -- hash_hmac('sha256', client IP, key), hex
    subject      CHAR(64)     NOT NULL,
    -- unix time of the start of the minute (or, for a lock, when it began)
    window_start INT UNSIGNED NOT NULL,
    hits         INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (bucket, subject, window_start),
    KEY idx_rate_limits_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=ascii;
