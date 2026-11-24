<?php

/**
 * headers.php
 *
 * Browser security headers, set from PHP for every page so they hold even
 * if the web server's configuration changes. Also the session cookie
 * settings and fingerprinted asset URLs.
 */

// Open Food Facts' image servers: product photos are hotlinked from here
const CSP_IMAGE_HOSTS = ["https://images.openfoodfacts.org", "https://images.openfoodfacts.net"];

// Cloudflare Turnstile, allowed only while TURNSTILE_* are set
const CSP_TURNSTILE_HOST = "https://challenges.cloudflare.com";

/**
 * True when advertising is switched on (ADS_ENABLED=1). Off by default.
 * Nothing in the site shows ads yet; this is the one switch a later change
 * should hang ad code, its CSP hosts and its privacy wording on.
 */
function ads_enabled()
{
    return env("ADS_ENABLED", "0") === "1";
}

/**
 * Extra CSP sources for advertising, as [directive => [sources]].
 *
 * EXTENSION POINT. Empty while ads are off, and empty today even when on,
 * because no ad provider has been chosen. A change that adds ads lists the
 * provider's hosts here (e.g. "script-src" => ["https://pagead2.googlesyndication.com"])
 * and nowhere else.
 */
function csp_ads_sources()
{
    if (!ads_enabled())
    {
        return [];
    }

    return [];
}

/**
 * The Content-Security-Policy as [directive => [sources]]. Built here, in
 * one place, so a feature that needs a third party adds it to its own
 * conditional block rather than loosening the base policy.
 */
function csp_directives()
{
    $csp = [
        "default-src" => ["'self'"],
        "img-src" => array_merge(["'self'", "data:"], CSP_IMAGE_HOSTS),
        "style-src" => ["'self'"],
        "script-src" => ["'self'"],
        "frame-ancestors" => ["'none'"],
        "base-uri" => ["'self'"],
        "form-action" => ["'self'"],
        "object-src" => ["'none'"],
    ];

    if (turnstile_enabled())
    {
        $csp["script-src"][] = CSP_TURNSTILE_HOST;
        $csp["frame-src"] = [CSP_TURNSTILE_HOST];
    }

    foreach (csp_ads_sources() as $directive => $sources)
    {
        $csp[$directive] = array_values(array_unique(array_merge($csp[$directive] ?? ["'self'"], $sources)));
    }

    return $csp;
}

/**
 * The policy as a header value.
 */
function csp_header()
{
    $parts = [];

    foreach (csp_directives() as $directive => $sources)
    {
        $parts[] = $directive . " " . implode(" ", $sources);
    }

    return implode("; ", $parts);
}

/**
 * Sends the security headers. Called once per request from config.php,
 * before any output.
 */
function send_security_headers()
{
    if (PHP_SAPI === "cli" || headers_sent())
    {
        return;
    }

    header("Content-Security-Policy: " . csp_header());
    header("Strict-Transport-Security: max-age=31536000");
    header("X-Content-Type-Options: nosniff");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Permissions-Policy: accelerometer=(), autoplay=(), camera=(), display-capture=(), "
        . "geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), "
        . "publickey-credentials-get=(), serial=(), usb=(), interest-cohort=(), browsing-topics=()");
    header("Cross-Origin-Opener-Policy: same-origin");
    header("X-Frame-Options: DENY");

    // indexing is controlled by SITE_INDEXABLE; see robots_header()
    if (!site_indexable())
    {
        header("X-Robots-Tag: noindex, nofollow");
    }

    header_remove("X-Powered-By");
}

/**
 * True when search engines may index the site (SITE_INDEXABLE=1).
 * Defaults to off, for a soft launch.
 */
function site_indexable()
{
    return env("SITE_INDEXABLE", "0") === "1";
}

/**
 * Starts the session if it is not running, with safe cookie settings.
 * Only pages with a form need one, so most visitors never get the cookie.
 */
function ensure_session()
{
    if (session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === "cli")
    {
        return;
    }

    ini_set("session.use_strict_mode", "1");
    ini_set("session.use_only_cookies", "1");
    ini_set("session.cookie_httponly", "1");
    // we send our own Cache-Control below; PHP's default adds Pragma and
    // Expires noise and would replace it
    ini_set("session.cache_limiter", "");

    session_set_cookie_params([
        "lifetime" => 0,
        "path" => "/",
        "secure" => is_secure_request(),
        "httponly" => true,
        "samesite" => "Lax",
    ]);

    session_start();

    // a page holding a per-session token must never be cached
    if (!headers_sent())
    {
        header("Cache-Control: no-store");
    }
}

/**
 * URL of a file under public/ with a content hash appended, so it can be
 * cached for a long time and still change the moment the file does.
 */
function asset_url($path)
{
    static $cache = [];

    if (!isset($cache[$path]))
    {
        $file = realpath(__DIR__ . "/../public" . $path);
        $public = realpath(__DIR__ . "/../public");

        $cache[$path] = ($file !== false && strpos($file, $public . DIRECTORY_SEPARATOR) === 0)
            ? $path . "?v=" . substr(hash_file("sha256", $file), 0, 12)
            : $path;
    }

    return $cache[$path];
}
