<?php

/**
 * seo.php
 *
 * robots.txt, the sitemaps and the metadata in each page's <head>.
 * Indexing is off until SITE_INDEXABLE=1 (see site_indexable()).
 */

// the sitemap protocol's limit per file
const SITEMAP_MAX_URLS = 50000;

// how long a generated sitemap is reused, in seconds
const SITEMAP_TTL = 3600;

/**
 * Absolute URL for a site path, from SITE_URL.
 */
function absolute_url($path)
{
    return SITE_URL . "/" . ltrim($path, "/");
}

/**
 * robots.txt. Disallows everything until the site is indexable.
 */
function robots_txt()
{
    if (!site_indexable())
    {
        return "# Soft launch: not open to search engines yet.\nUser-agent: *\nDisallow: /\n";
    }

    return "User-agent: *\n"
        // search results are endless and add nothing a sitemap does not
        . "Disallow: /search.php?\n"
        . "Allow: /\n\n"
        . "Sitemap: " . absolute_url("/sitemap.xml") . "\n";
}

/**
 * Pages that are not brands or products, for the sitemap.
 */
function sitemap_static_paths()
{
    $paths = ["/", "/search.php", "/about.php", "/sources.php", "/corrections.php",
              "/privacy.php", "/terms.php", "/contact.php", "/priorities.php"];

    // only pages that exist
    return array_values(array_filter($paths, function ($p) {
        return $p === "/" || is_file(__DIR__ . "/../public" . $p);
    }));
}

/**
 * Number of product sitemap files needed, or false on a database error.
 */
function sitemap_product_parts()
{
    $rows = query("SELECT COUNT(*) AS n FROM products p JOIN brand_v b ON b.brand_id = p.brand_id");

    return $rows === false ? false : max(1, (int) ceil(((int) $rows[0]["n"]) / SITEMAP_MAX_URLS));
}

/**
 * Builds one sitemap document. $route is a front controller route; $part is
 * the product file number. Returns the XML, or null if that file does not
 * exist, or false on a database error.
 */
function build_sitemap($route, $part = 1)
{
    $x = function ($url) { return htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, "UTF-8"); };

    if ($route === "sitemap-index")
    {
        $parts = sitemap_product_parts();
        if ($parts === false)
        {
            return false;
        }

        $files = ["/sitemap-pages.xml", "/sitemap-brands.xml"];
        for ($i = 1; $i <= $parts; $i++)
        {
            $files[] = "/sitemap-products-$i.xml";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
             . '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($files as $file)
        {
            $xml .= "  <sitemap><loc>" . $x(absolute_url($file)) . "</loc></sitemap>\n";
        }

        return $xml . "</sitemapindex>\n";
    }

    if ($route === "sitemap-pages")
    {
        $urls = array_map("absolute_url", sitemap_static_paths());
    }
    else if ($route === "sitemap-brands")
    {
        $rows = query("SELECT brand FROM brand_v ORDER BY brand");
        if ($rows === false)
        {
            return false;
        }

        $urls = array_map(function ($r) { return absolute_url(brand_path($r["brand"])); }, $rows);
    }
    else if ($route === "sitemap-products")
    {
        $parts = sitemap_product_parts();
        if ($parts === false)
        {
            return false;
        }
        if ($part < 1 || $part > $parts)
        {
            return null;
        }

        // only products whose brand is listed, so every URL resolves
        $rows = query(
            "SELECT p.code FROM products p JOIN brand_v b ON b.brand_id = p.brand_id
              ORDER BY p.id LIMIT " . SITEMAP_MAX_URLS . " OFFSET " . (($part - 1) * SITEMAP_MAX_URLS)
        );
        if ($rows === false)
        {
            return false;
        }

        $urls = array_map(function ($r) { return absolute_url(product_path($r["code"])); },
            array_filter($rows, function ($r) { return is_product_code($r["code"]); }));
    }
    else
    {
        return null;
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
         . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as $url)
    {
        $xml .= "  <url><loc>" . $x($url) . "</loc></url>\n";
    }

    return $xml . "</urlset>\n";
}

/**
 * A sitemap, reused for up to an hour from a file under CACHE_DIR (default:
 * the system temporary directory), so crawlers cannot make us rebuild it on
 * every request. Same return values as build_sitemap().
 */
function cached_sitemap($route, $part = 1)
{
    $dir = rtrim(env("CACHE_DIR", sys_get_temp_dir()), "/");
    $file = $dir . "/ethicalbuy-" . hash("sha256", SITE_URL . "|" . DATABASE . "|$route|$part") . ".xml";

    if (is_file($file) && filemtime($file) > time() - SITEMAP_TTL)
    {
        $xml = @file_get_contents($file);
        if (is_string($xml) && $xml !== "")
        {
            return $xml;
        }
    }

    $xml = build_sitemap($route, $part);

    if (is_string($xml))
    {
        // write then rename, so a reader never sees half a file
        $tmp = $file . "." . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $xml) !== false)
        {
            @rename($tmp, $file);
        }
    }

    return $xml;
}

/**
 * Site paths for a brand and a product page.
 */
function brand_path($brand)
{
    return "/brand.php?brand=" . rawurlencode((string) $brand);
}

function product_path($code)
{
    return "/product.php?code=" . rawurlencode((string) $code);
}

/**
 * A meta description from free text: one line, at most about 155 characters,
 * cut at a word.
 */
function meta_description($text)
{
    $text = trim(preg_replace('/\s+/u', " ", (string) $text));

    if (mb_strlen($text) <= 155)
    {
        return $text;
    }

    $cut = mb_substr($text, 0, 152);
    $space = mb_strrpos($cut, " ");

    return rtrim(mb_substr($cut, 0, $space === false ? 152 : $space), " ,.;:") . "...";
}

/**
 * The default description, for pages that do not set their own.
 */
const DEFAULT_DESCRIPTION = "Ethical Buy shows who owns the brands in your shopping basket and how they score on ownership, environment, nutrition and animal welfare.";
