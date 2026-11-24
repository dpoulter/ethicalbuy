<?php

/**
 * errors.php
 *
 * Plain error pages, and the safety nets that make sure a visitor sees one
 * of them instead of a stack trace, a SQL error or a file path.
 */

/**
 * Title and default wording for each error page we send.
 */
function error_page_text($code)
{
    $pages = [
        403 => ["Not allowed", "You don't have permission to see this page."],
        404 => ["Page not found", "We couldn't find that page. It may have moved, or the address may be mistyped."],
        429 => ["Too many requests", "You've made a lot of requests in a short time. Please wait a little and try again."],
        500 => ["Something went wrong", "Something went wrong on our side. Please try again in a moment."],
        503 => ["Down for maintenance", "Ethical Buy is unavailable for a short while. Please try again soon."],
    ];

    return $pages[$code] ?? $pages[500];
}

/**
 * Sends a complete error page with the given status and stops.
 *
 * Throws away anything already buffered, so a half-rendered page never
 * reaches the visitor. $message replaces the default wording; it is escaped
 * and must never carry internal detail.
 */
function show_error_page($code, $message = null, $retry_after = null)
{
    $code = (int) $code;
    [$title, $default] = error_page_text($code);

    while (ob_get_level() > 0)
    {
        ob_end_clean();
    }

    if (!headers_sent())
    {
        http_response_code($code);
        header("Cache-Control: no-store");

        if ($retry_after !== null)
        {
            header("Retry-After: " . max(1, (int) $retry_after));
        }

        // an error page is never worth indexing
        header("X-Robots-Tag: noindex, nofollow");
    }

    render("error.php", [
        "title" => $title,
        "heading" => $title,
        "message" => $message ?? $default,
        "code" => $code,
        "noindex" => true,
    ]);
    exit;
}

/**
 * Logs an uncaught exception and shows the 500 page.
 */
function handle_uncaught($exception)
{
    error_log("Uncaught " . get_class($exception) . ": " . $exception->getMessage()
        . " at " . $exception->getFile() . ":" . $exception->getLine());

    show_error_page(500);
}

/**
 * Catches fatal errors at shutdown and replaces the partial page with the
 * 500 page. The error itself has already been logged by PHP.
 */
function handle_fatal()
{
    $error = error_get_last();
    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    if ($error === null || !($error["type"] & $fatal) || PHP_SAPI === "cli")
    {
        return;
    }

    show_error_page(500);
}

/**
 * Installs the safety nets for a web request.
 */
function install_error_handling()
{
    ini_set("display_errors", DEBUG ? "1" : "0");
    ini_set("display_startup_errors", DEBUG ? "1" : "0");
    ini_set("log_errors", "1");
    // never put file paths or arguments in anything that could be shown
    ini_set("html_errors", "0");
    error_reporting(E_ALL);

    if (PHP_SAPI === "cli")
    {
        return;
    }

    // buffer the page so a late failure can still be replaced cleanly
    ob_start();

    if (!DEBUG)
    {
        set_exception_handler("handle_uncaught");
        register_shutdown_function("handle_fatal");
    }
}

/**
 * The front controller's routes, served from public/index.php: the home
 * page plus robots.txt and the sitemaps. Caddy's php_fastcgi sends every
 * missing path to index.php, so this list is also what decides 404s.
 */
function front_controller_route($path)
{
    if ($path === "/" || $path === "/index.php")
    {
        return "home";
    }

    if ($path === "/robots.txt")
    {
        return "robots";
    }

    if ($path === "/sitemap.xml")
    {
        return "sitemap-index";
    }

    if ($path === "/sitemap-brands.xml")
    {
        return "sitemap-brands";
    }

    if (preg_match('#^/sitemap-products-([1-9][0-9]{0,3})\.xml$#', $path))
    {
        return "sitemap-products";
    }

    return null;
}

/**
 * Sends a real 404 for any path that does not belong to the script that is
 * running. Without this, /nope.php is answered by index.php with a 200.
 */
function enforce_known_path()
{
    if (PHP_SAPI === "cli")
    {
        return;
    }

    $script = (string) ($_SERVER["SCRIPT_NAME"] ?? "");
    $path = request_path();

    if ($path === $script)
    {
        return;
    }

    // /admin/ and /admin are the directory index
    if (substr($script, -10) === "/index.php")
    {
        $dir = substr($script, 0, -10);

        if ($path === $dir . "/" || ($dir !== "" && $path === $dir))
        {
            if ($script !== "/index.php" || $path === "/")
            {
                return;
            }
        }
    }

    if ($script === "/index.php" && front_controller_route($path) !== null)
    {
        return;
    }

    show_error_page(404);
}
