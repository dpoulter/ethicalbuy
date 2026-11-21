<?php

/**
 * Imports UK brand facts from Open Food Facts.
 *
 *   php bin/import-openfoodfacts.php --category=en:chocolates --contact=you@example.com
 *   php bin/import-openfoodfacts.php --category=en:chocolates --contact=you@example.com --apply
 *
 * Dry run unless --apply is passed: it prints exactly what it would change and
 * touches nothing.
 *
 * Guarantees, enforced below:
 *   * never writes a rating -- new brands arrive as "Not rated" for a human
 *   * never writes editorial notes
 *   * never overwrites a value that is already present, so your edits win
 *   * records source, URL, licence and retrieval time on every row it touches
 *
 *   php bin/import-openfoodfacts.php --refresh-scores --contact=you@example.com [--apply]
 *
 * re-fetches products for brands that already hold Open Food Facts scores and
 * rewrites only those derived scores and the per-product inputs behind them.
 * It never touches ratings, notes, owners, brand fields or curated scores.
 *
 * Open Food Facts is ODbL 1.0. You may reuse it commercially with attribution,
 * and a derived database must be shared alike. The public site credits it via
 * the source fields this writes. For a large import prefer their bulk export
 * over paging this API: https://world.openfoodfacts.org/data
 */

if (PHP_SAPI !== "cli")
{
    http_response_code(404);
    exit;
}

require_once(__DIR__ . "/../includes/constants.php");
require_once(__DIR__ . "/../includes/functions.php");
require_once(__DIR__ . "/../includes/import.php");

error_reporting(E_ALL);
ini_set("display_errors", "1");

// ---------------------------------------------------------------- options

$options = getopt("", [
    "category::", "brand::", "country::", "contact::", "pages::",
    "limit::", "delay::", "apply", "dump", "help", "refresh-scores",
]);

if (isset($options["help"]))
{
    fwrite(STDOUT, <<<TXT
Usage: php bin/import-openfoodfacts.php --contact=you@example.com [options]

  --contact=EMAIL   Required. Open Food Facts asks that clients identify
                    themselves; anonymous scrapers get blocked.
  --category=TAG    OFF category tag, e.g. en:chocolates
  --brand=NAME      Import a single brand instead of a category
  --country=TAG     Default en:united-kingdom. Use "any" to disable.
  --pages=N         Pages to fetch (default 1, 100 products per page)
  --limit=N         Stop after N brands
  --delay=SECONDS   Pause between requests (default 6, be polite)
  --dump            Print the first raw product and exit. Use this on the
                    first run to confirm the API's field names.
  --refresh-scores  Re-fetch products for every brand that already has an
                    Open Food Facts score and rewrite only those scores and
                    their per-product inputs. --pages, --limit, --delay and
                    --country apply per brand. Nothing else is touched.
  --apply           Actually write. Without it, this is a dry run.

TXT);
    exit(0);
}

$contact = trim((string) ($options["contact"] ?? getenv("IMPORT_CONTACT") ?: ""));
if ($contact === "")
{
    fwrite(STDERR, "Refusing to run without --contact=EMAIL.\n");
    fwrite(STDERR, "Open Food Facts asks clients to identify themselves, and\n");
    fwrite(STDERR, "unidentified clients get rate-limited or blocked.\n");
    exit(1);
}

$category = trim((string) ($options["category"] ?? ""));
$brand    = trim((string) ($options["brand"] ?? ""));
$country  = trim((string) ($options["country"] ?? "en:united-kingdom"));
$country  = ($country === "any" || $country === "") ? null : $country;
$pages    = max(1, (int) ($options["pages"] ?? 1));
$limit    = (int) ($options["limit"] ?? 0);
$delay    = max(0, (int) ($options["delay"] ?? 6));
$apply    = isset($options["apply"]);
$dump     = isset($options["dump"]);
$refresh  = isset($options["refresh-scores"]);

if (!$refresh && $category === "" && $brand === "")
{
    fwrite(STDERR, "Give --category=TAG or --brand=NAME (or --help).\n");
    exit(1);
}

$agent = "EthicalBuy-import/0.1 (+" . SITE_URL . "; " . $contact . ")";

// ---------------------------------------------------------------- fetching

/**
 * GET with retries. Rate limiting (429) and server errors are retried with a
 * growing pause; anything else fails at once.
 */
function http_get_json($url, $agent, $attempts = 3, $backoff = 10)
{
    for ($try = 1; ; $try++)
    {
        [$data, $error, $retryable] = http_get_json_once($url, $agent);

        if ($error === null || !$retryable || $try >= $attempts)
        {
            return [$data, $error];
        }

        fwrite(STDERR, "  $error, retrying in " . ($backoff * $try) . "s\n");
        sleep($backoff * $try);
    }
}

function http_get_json_once($url, $agent)
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT      => $agent,
        CURLOPT_HTTPHEADER     => ["Accept: application/json"],
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false)
    {
        return [null, "network error: $error", true];
    }

    if ($status !== 200)
    {
        return [null, "HTTP $status", $status === 429 || $status >= 500];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded))
    {
        return [null, "response was not JSON", false];
    }

    return [$decoded, null, false];
}

function build_search_url($category, $brand, $page)
{
    // ask only for the fields we map, to keep the response small
    $fields = "code,product_name,brands,brands_tags,categories_tags," .
              "countries_tags,labels_tags,stores,stores_tags," .
              // both spellings: "environmental_score_grade" is the newer name
              "ecoscore_grade,environmental_score_grade,nutriscore_grade";

    $params = [
        "fields"    => $fields,
        "page_size" => "100",
        "page"      => (string) $page,
        "json"      => "1",
    ];

    if ($category !== "") { $params["categories_tags"] = $category; }
    if ($brand !== "")    { $params["brands_tags"] = $brand; }

    return OFF_BASE . "/api/v2/search?" . http_build_query($params);
}

// ---------------------------------------------------------------- writing

/**
 * Finds a category id by name, creating it when applying.
 *
 * Returns [id, created, error]. A failure to create the category is reported
 * rather than swallowed: silently filing a brand as uncategorised would look
 * like a successful import and quietly lose data.
 */
function resolve_category($name, $apply)
{
    if ($name === null)
    {
        return [null, false, null];
    }

    $rows = query("SELECT id FROM categories WHERE name = ? LIMIT 1", $name);

    if ($rows === false)
    {
        return [null, false, "could not read categories"];
    }

    if (!empty($rows))
    {
        return [(int) $rows[0]["id"], false, null];
    }

    if (!$apply)
    {
        return [null, true, null];
    }

    if (query("INSERT INTO categories (name) VALUES (?)", $name) === false)
    {
        return [null, false, "could not create category \"$name\" (check INSERT privilege)"];
    }

    $rows = query("SELECT id FROM categories WHERE name = ? LIMIT 1", $name);

    if (empty($rows))
    {
        return [null, false, "created category \"$name\" but could not read it back"];
    }

    return [(int) $rows[0]["id"], true, null];
}

/**
 * Inserts a brand, or fills in only the blanks on an existing one.
 * Returns one of: "created", "filled", "unchanged", "failed".
 */
function upsert_brand($b, $apply, &$notes)
{
    $existing = query(
        "SELECT id, category_id, type, availability, certifications
           FROM brands WHERE name = ? LIMIT 1",
        $b["name"]
    );

    if ($existing === false)
    {
        return "failed";
    }

    [$category_id, $new_category, $category_error] = resolve_category($b["category"], $apply);

    // don't write a brand we know would be filed wrongly
    if ($category_error !== null)
    {
        $notes[] = $category_error;
        return "failed";
    }

    if ($new_category)
    {
        $notes[] = "new category: " . $b["category"];
    }

    // ---- new brand
    if (empty($existing))
    {
        if (!$apply)
        {
            return "created";
        }

        $ok = query(
            "INSERT INTO brands
                 (name, category_id, type, availability, certifications,
                  source, source_ref, source_url, source_licence, retrieved_at, rating)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)",
            $b["name"], $category_id, $b["type"], $b["availability"], $b["certifications"],
            $b["source"], $b["source_ref"], $b["source_url"], $b["source_licence"]
        );

        if ($ok === false)
        {
            return "failed";
        }

        $fresh = query("SELECT id FROM brands WHERE name = ? LIMIT 1", $b["name"]);
        $new_id = empty($fresh) ? null : (int) $fresh[0]["id"];

        if ($new_id !== null && !empty($b["scores"]))
        {
            save_dimension_scores($new_id, $b["scores"], $b["source_url"], $apply, $notes, $b["inputs"] ?? []);
        }

        return "created";
    }

    // ---- existing brand: only ever fill blanks
    $row = $existing[0];

    // dimension scores are refreshed even when the brand's own fields are
    // already filled in, since they carry their own provenance
    if (!empty($b["scores"]))
    {
        save_dimension_scores((int) $row["id"], $b["scores"], $b["source_url"], $apply, $notes, $b["inputs"] ?? []);
    }

    $sets = [];
    $params = [];

    $fillable = [
        "category_id"    => $category_id,
        "type"           => $b["type"],
        "availability"   => $b["availability"],
        "certifications" => $b["certifications"],
    ];

    foreach ($fillable as $column => $value)
    {
        $current = $row[$column] ?? null;

        if ($value !== null && ($current === null || $current === ""))
        {
            $sets[] = "$column = ?";
            $params[] = $value;
        }
    }

    if (!$sets)
    {
        return $notes ? "filled" : "unchanged";
    }

    if (!$apply)
    {
        $notes[] = "would fill: " . implode(", ", array_map(
            function ($s) { return rtrim($s, " =?"); }, $sets));
        return "filled";
    }

    // provenance is refreshed whenever we actually contribute something
    $sets[] = "source = ?";          $params[] = $b["source"];
    $sets[] = "source_ref = ?";      $params[] = $b["source_ref"];
    $sets[] = "source_url = ?";      $params[] = $b["source_url"];
    $sets[] = "source_licence = ?";  $params[] = $b["source_licence"];
    $sets[] = "retrieved_at = NOW()";

    $params[] = $row["id"];

    $ok = query("UPDATE brands SET " . implode(", ", $sets) . " WHERE id = ?", ...$params);

    return $ok === false ? "failed" : "filled";
}

// ---------------------------------------------------------------- run

fwrite(STDOUT, ($apply ? "APPLYING" : "DRY RUN (pass --apply to write)") . "\n");
fwrite(STDOUT, "source:  Open Food Facts (" . OFF_LICENCE . ")\n");
fwrite(STDOUT, "country: " . ($country ?? "any") . "\n\n");

/**
 * Fetches up to $pages pages of products. Exits on a fetch error unless
 * $soft, in which case it returns null.
 */
function fetch_products($category, $brand, $pages, $delay, $agent, $dump, $soft = false)
{
    $products = [];

    for ($page = 1; $page <= $pages; $page++)
    {
        $url = build_search_url($category, $brand, $page);
        fwrite(STDOUT, "GET $url\n");

        [$data, $error] = http_get_json($url, $agent);

        if ($error !== null)
        {
            fwrite(STDERR, "  failed: $error\n");
            if ($soft)
            {
                return null;
            }
            exit(1);
        }

        $batch = $data["products"] ?? [];
        fwrite(STDOUT, "  " . count($batch) . " products\n");

        if ($dump)
        {
            fwrite(STDOUT, "\n--- first raw product ---\n");
            fwrite(STDOUT, json_encode($batch[0] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
            exit(0);
        }

        $products = array_merge($products, $batch);

        if (count($batch) < 100)
        {
            break;
        }

        if ($page < $pages && $delay > 0)
        {
            sleep($delay);
        }
    }

    return $products;
}

if ($refresh)
{
    // only brands that already carry an OFF-derived score
    $targets = query(
        "SELECT DISTINCT b.id, b.name
           FROM brands b
           JOIN brand_scores s ON s.brand_id = b.id AND s.source = ?
          ORDER BY b.name",
        OFF_SOURCE
    );

    if ($targets === false)
    {
        fwrite(STDERR, "Could not read brands with Open Food Facts scores.\n");
        exit(1);
    }

    if ($limit > 0)
    {
        $targets = array_slice($targets, 0, $limit);
    }

    fwrite(STDOUT, count($targets) . " brands with Open Food Facts scores\n\n");

    $stats = ["refreshed" => 0, "not found" => 0, "failed" => 0];

    foreach ($targets as $i => $t)
    {
        if ($i > 0 && $delay > 0)
        {
            sleep($delay);
        }

        $products = fetch_products("", off_brand_tag($t["name"]), $pages, $delay, $agent, false, true);
        $notes = [];

        if ($products === null)
        {
            $stats["failed"]++;
            fwrite(STDOUT, "  failed    {$t["name"]}\n");
            continue;
        }

        // the search may return other brands sharing a product; keep ours
        $match = null;
        foreach (collapse_off_products($products, $country) as $b)
        {
            if (mb_strtolower($b["name"]) === mb_strtolower($t["name"]))
            {
                $match = $b;
                break;
            }
        }

        if ($match === null || empty($match["scores"]))
        {
            $stats["not found"]++;
            fwrite(STDOUT, "  not found {$t["name"]}  (existing scores left as they are)\n");
            continue;
        }

        $ok = save_dimension_scores((int) $t["id"], $match["scores"], $match["source_url"],
            $apply, $notes, $match["inputs"]);

        $stats[$ok ? "refreshed" : "failed"]++;
        fwrite(STDOUT, sprintf("  %-9s %s%s\n", $ok ? "refreshed" : "failed", $t["name"],
            $notes ? "  (" . implode("; ", $notes) . ")" : ""));
    }

    fwrite(STDOUT, "\n");
    foreach ($stats as $key => $count)
    {
        fwrite(STDOUT, sprintf("  %-9s %d\n", $key, $count));
    }

    if (!$apply)
    {
        fwrite(STDOUT, "\nNothing was written. Re-run with --apply.\n");
    }

    exit($stats["failed"] > 0 ? 1 : 0);
}

$products = fetch_products($category, $brand, $pages, $delay, $agent, $dump);

$brands = collapse_off_products($products, $country);

if ($limit > 0)
{
    $brands = array_slice($brands, 0, $limit);
}

fwrite(STDOUT, "\n" . count($products) . " products -> " . count($brands) . " brands\n\n");

$stats = ["created" => 0, "filled" => 0, "unchanged" => 0, "failed" => 0];

foreach ($brands as $b)
{
    $notes = [];
    $result = upsert_brand($b, $apply, $notes);
    $stats[$result]++;

    if ($result !== "unchanged")
    {
        fwrite(STDOUT, sprintf("  %-9s %s%s\n",
            $result,
            $b["name"],
            $notes ? "  (" . implode("; ", $notes) . ")" : ""
        ));
    }
}

fwrite(STDOUT, "\n");
foreach ($stats as $key => $count)
{
    fwrite(STDOUT, sprintf("  %-9s %d\n", $key, $count));
}

if (!$apply)
{
    fwrite(STDOUT, "\nNothing was written. Re-run with --apply.\n");
}
else
{
    fwrite(STDOUT, "\nImported brands are unrated. Rate them in /admin.\n");
}

exit($stats["failed"] > 0 ? 1 : 0);
