<?php

/**
 * ratelimit.php
 *
 * Per-visitor counters in the rate_limits table (migration 008), used for
 * the browse and contact limits and the admin login throttle. PHP sessions
 * are deliberately not used: a client that drops its cookie would reset
 * them.
 *
 * Counts are kept per minute and summed over the span being limited, so
 * "3 an hour" is a true rolling hour to the nearest minute. The visitor is
 * stored as a keyed hash of their IP address, never the address.
 *
 * Every function fails open if the database is unavailable: a broken
 * counter must not lock everyone out. The pages that need the database
 * will fail on their own in that case.
 */

const RATE_GRANULARITY = 60;

/**
 * Limits, from the environment, with the defaults in docs/public-deployment.md.
 */
function rate_setting($name, $default)
{
    $value = env($name, (string) $default);

    return ctype_digit($value) && (int) $value > 0 ? (int) $value : (int) $default;
}

/**
 * The stored form of a visitor: HMAC-SHA256 of their IP. The key is
 * IP_HASH_KEY, or failing that one derived from the database password, so
 * the hashes cannot be reversed by trying every address without it.
 */
function rate_subject($ip = null)
{
    $key = env("IP_HASH_KEY", "");

    if ($key === "")
    {
        $key = hash("sha256", "ethicalbuy-rate|" . PASSWORD);
    }

    return hash_hmac("sha256", $ip ?? client_ip(), $key);
}

/**
 * Records one hit in $bucket for this visitor. Returns false on error.
 */
function rate_hit($bucket, $ip = null, $now = null)
{
    $now = $now ?? time();
    $window = $now - ($now % RATE_GRANULARITY);

    $ok = query(
        "INSERT INTO rate_limits (bucket, subject, window_start, hits) VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE hits = hits + 1",
        $bucket, rate_subject($ip), $window
    ) !== false;

    // tidy up now and then rather than needing a cron job
    if ($ok && random_int(1, 200) === 1)
    {
        query("DELETE FROM rate_limits WHERE window_start < ?", $now - 2 * 86400);
    }

    return $ok;
}

/**
 * Hits in $bucket for this visitor over the last $span seconds, and how many
 * seconds until the oldest of them drops out of the span.
 * Returns [count, seconds_until_one_expires].
 */
function rate_count($bucket, $span, $ip = null, $now = null)
{
    $now = $now ?? time();
    $current = $now - ($now % RATE_GRANULARITY);
    // windows that started inside the span; for a span of one minute that
    // is the current minute only
    $from = $current - $span + RATE_GRANULARITY;

    $rows = query(
        "SELECT COALESCE(SUM(hits), 0) AS n, MIN(window_start) AS oldest
           FROM rate_limits
          WHERE bucket = ? AND subject = ? AND window_start >= ?",
        $bucket, rate_subject($ip), $from
    );

    if ($rows === false || !$rows)
    {
        return [0, 0];
    }

    $oldest = $rows[0]["oldest"] === null ? $current : (int) $rows[0]["oldest"];

    return [(int) $rows[0]["n"], max(1, $oldest + $span - $now)];
}

/**
 * Seconds this visitor must wait before another hit in $bucket is allowed
 * under each [limit, span] rule, or 0 if they may go ahead now.
 */
function rate_wait($bucket, $rules, $ip = null)
{
    $wait = 0;

    foreach ($rules as [$limit, $span])
    {
        [$count, $retry] = rate_count($bucket, $span, $ip);

        if ($count >= $limit)
        {
            $wait = max($wait, $retry);
        }
    }

    return $wait;
}

/**
 * Counts this request against the browse limit and answers 429 with
 * Retry-After once the visitor is over it. For search and the listing,
 * brand and product pages: generous enough for any reader, low enough that
 * a scraper cannot hammer the database.
 */
function enforce_browse_limit()
{
    if (PHP_SAPI === "cli")
    {
        return;
    }

    $limit = rate_setting("RATE_BROWSE_PER_MINUTE", 120);
    $wait = rate_wait("browse", [[$limit, 60]]);

    if ($wait > 0)
    {
        show_error_page(429, null, $wait);
    }

    rate_hit("browse");
}
