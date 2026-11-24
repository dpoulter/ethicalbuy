<?php

/**
 * request.php
 *
 * Facts about the current request that depend on what sits in front of PHP.
 *
 * In production the chain is: visitor -> Cloudflare -> cloudflared (on this
 * machine) -> Caddy -> PHP-FPM. PHP therefore sees every public request
 * arriving from 127.0.0.1, and the visitor's real address is in the
 * CF-Connecting-IP header. That header, and X-Forwarded-Proto, are believed
 * only when TRUST_PROXY=1 and the TCP peer is loopback; anyone can send them
 * from anywhere else.
 *
 * Needs env() from constants.php.
 */

/**
 * True if the TCP peer is this machine.
 */
function peer_is_loopback()
{
    $peer = (string) ($_SERVER["REMOTE_ADDR"] ?? "");

    if ($peer === "::1")
    {
        return true;
    }

    // 127.0.0.0/8, and the IPv4-mapped IPv6 form of it
    if (preg_match('/^(?:::ffff:)?127\.\d{1,3}\.\d{1,3}\.\d{1,3}$/i', $peer))
    {
        return filter_var(preg_replace('/^::ffff:/i', "", $peer), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }

    return false;
}

/**
 * True if proxy headers on this request may be believed: TRUST_PROXY=1 and
 * the request came from loopback, where only our own proxy can be.
 */
function proxy_headers_trusted()
{
    return env("TRUST_PROXY", "0") === "1" && peer_is_loopback();
}

/**
 * The visitor's IP address, validated. The single source of a client IP for
 * logging, rate limiting and the admin throttle.
 *
 * CF-Connecting-IP when proxy headers are trusted and it holds a valid
 * address, otherwise REMOTE_ADDR. "0.0.0.0" if neither is usable (e.g. CLI).
 */
function client_ip()
{
    if (proxy_headers_trusted())
    {
        $forwarded = trim((string) ($_SERVER["HTTP_CF_CONNECTING_IP"] ?? ""));

        if (filter_var($forwarded, FILTER_VALIDATE_IP) !== false)
        {
            return $forwarded;
        }
    }

    $peer = trim((string) ($_SERVER["REMOTE_ADDR"] ?? ""));

    return filter_var($peer, FILTER_VALIDATE_IP) !== false ? $peer : "0.0.0.0";
}

/**
 * The address with the host part removed, for anything we keep: the IPv4 /24
 * or the IPv6 /48, written as a network, e.g. "203.0.113.0/24". Enough to see
 * a pattern of abuse, not enough to identify a household.
 */
function truncated_ip($ip)
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
    {
        $parts = explode(".", $ip);
        return "$parts[0].$parts[1].$parts[2].0/24";
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false)
    {
        $bytes = inet_pton($ip);
        $network = substr($bytes, 0, 6) . str_repeat("\0", 10);
        return inet_ntop($network) . "/48";
    }

    return "unknown";
}

/**
 * True if this request arrived over TLS.
 *
 * HTTPS=on is set by the web server when it terminated TLS itself.
 * X-Forwarded-Proto is believed only from a trusted loopback proxy.
 * Plain HTTP from loopback with TRUST_PROXY off is local development.
 */
function is_secure_request()
{
    if (!empty($_SERVER["HTTPS"]) && strtolower((string) $_SERVER["HTTPS"]) !== "off")
    {
        return true;
    }

    if (proxy_headers_trusted())
    {
        return strtolower(trim((string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ""))) === "https";
    }

    // local development over plain HTTP
    return env("TRUST_PROXY", "0") !== "1" && peer_is_loopback();
}

/**
 * The Host header, lower case, without a port. "" when absent or malformed.
 */
function request_host()
{
    $host = strtolower(trim((string) ($_SERVER["HTTP_HOST"] ?? "")));

    // [::1]:8000 -> ::1, example.net:443 -> example.net
    if (preg_match('/^\[([0-9a-f:.]+)\](?::\d+)?$/', $host, $m))
    {
        return $m[1];
    }

    $host = preg_replace('/:\d+$/', "", $host);

    return preg_match('/^[a-z0-9.-]+$/', $host) ? $host : "";
}

/**
 * True when the site is being used on this machine by its developer: the
 * command line, or a Host of localhost. Debug output is never allowed
 * anywhere else, whatever APP_DEBUG says.
 */
function is_local_host()
{
    if (PHP_SAPI === "cli")
    {
        return true;
    }

    return in_array(request_host(), ["localhost", "127.0.0.1", "::1"], true);
}

/**
 * The decoded path of the request, e.g. "/brand.php", without the query.
 */
function request_path()
{
    $path = parse_url((string) ($_SERVER["REQUEST_URI"] ?? "/"), PHP_URL_PATH);

    return is_string($path) && $path !== "" ? rawurldecode($path) : "/";
}

/**
 * Comma-separated environment list, lower-cased, blanks dropped.
 */
function env_list($name)
{
    return array_values(array_filter(array_map(
        function ($s) { return strtolower(trim($s)); },
        explode(",", (string) env($name, ""))
    ), "strlen"));
}
