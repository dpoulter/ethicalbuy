<?php

/**
 * import.php
 *
 * Mapping helpers for external data sources.
 *
 * Deliberate design rule: an import NEVER sets a rating and NEVER writes
 * editorial notes. It imports verifiable facts only -- brand name, category,
 * product type, where it is sold, which certifications a source records --
 * and leaves `rating` NULL for a human to decide.
 *
 * That is not timidity, it is the point:
 *
 *   * A rating copied from another publisher is their intellectual property
 *     and, in the UK, their database right.
 *   * Publishing a low ethical score about a named real company is a
 *     defamation risk. It is defensible when it rests on facts you hold and
 *     can show; it is not defensible when it was scraped from someone else.
 *   * Facts with a cited source are neither of those things.
 *
 * Every imported row records where it came from, under what licence, and when,
 * so the site can attribute its sources and re-check them later.
 */

require_once(__DIR__ . "/constants.php");

// Open Food Facts is ODbL 1.0: reuse is allowed with attribution, and derived
// databases must be shared alike. https://opendatacommons.org/licenses/odbl/
const OFF_SOURCE  = "openfoodfacts";
const OFF_LICENCE = "ODbL 1.0";

// Where we fetch from. Overridable so the importer can be pointed at a local
// mirror, a staging copy, or a fixture server in tests.
define("OFF_BASE", rtrim(env("OFF_BASE", "https://world.openfoodfacts.org"), "/"));

// Where the record actually lives. Provenance must cite the canonical page,
// never whichever mirror we happened to read from.
const OFF_CANONICAL = "https://world.openfoodfacts.org";

/**
 * Certification labels worth recording, keyed by Open Food Facts tag.
 *
 * Deliberately a whitelist. OFF label tags are crowd-sourced and noisy, and an
 * unrecognised tag is more likely to be junk than a real certification.
 */
function off_certifications()
{
    return [
        "en:organic"                  => "Organic",
        "en:eu-organic"               => "EU Organic",
        "en:soil-association-organic" => "Soil Association Organic",
        "en:fairtrade"                => "Fairtrade",
        "en:fair-trade"               => "Fairtrade",
        "en:fairtrade-international"  => "Fairtrade International",
        "en:rainforest-alliance"      => "Rainforest Alliance",
        "en:utz-certified"            => "UTZ Certified",
        "en:rspo"                     => "RSPO palm oil",
        "en:palm-oil-free"            => "Palm oil free",
        "en:vegan"                    => "Vegan",
        "en:vegetarian"               => "Vegetarian",
        "en:b-corp"                   => "B Corp",
        "en:cruelty-free"             => "Cruelty free",
        "en:marine-stewardship-council" => "MSC",
        "en:red-tractor"              => "Red Tractor",
        "en:rspca-assured"            => "RSPCA Assured",
    ];
}

/**
 * Turns an Open Food Facts tag such as "en:dark-chocolates" into
 * "Dark Chocolates". Tags without a recognised language prefix are kept whole.
 */
function off_tag_to_name($tag)
{
    $tag = (string) $tag;

    // strip a two-letter language prefix, e.g. "en:" or "fr:"
    if (preg_match('/^[a-z]{2}:(.*)$/', $tag, $m))
    {
        $tag = $m[1];
    }

    $tag = str_replace(["-", "_"], " ", $tag);
    $tag = trim(preg_replace('/\s+/', " ", $tag));

    return $tag === "" ? null : ucfirst($tag);
}

/**
 * Collapses whitespace and trims. Returns null for anything empty.
 */
function clean_value($value, $max = 255)
{
    if (!is_scalar($value))
    {
        return null;
    }

    $value = trim(preg_replace('/\s+/u', " ", (string) $value));

    if ($value === "")
    {
        return null;
    }

    return mb_substr($value, 0, $max);
}

/**
 * Open Food Facts publishes letter grades for nutrition (Nutri-Score, A-E)
 * and environmental impact (Eco-Score / Green-Score, which also uses A+ and
 * F). Map them onto the site's 1-10 scale. Anything else, including
 * "unknown", "not-applicable" and an empty value, returns null so it is
 * recorded as not assessed rather than as a bad score.
 */
function off_grade_map()
{
    return ["a" => 10.0, "b" => 8.0, "c" => 6.0, "d" => 4.0, "e" => 2.0];
}

/**
 * The Eco-Score scale: A+ counts as A (10) and F as 1, the lowest score the
 * schema allows.
 */
function off_eco_grade_map()
{
    return ["a-plus" => 10.0] + off_grade_map() + ["f" => 1.0];
}

/**
 * Display form of a grade: "a-plus" -> "A+", "b" -> "B".
 */
function off_grade_label($grade)
{
    $grade = strtolower(trim((string) $grade));

    return $grade === "a-plus" ? "A+" : strtoupper($grade);
}

/**
 * "A 10, B 8, ..." for a grade map, for method lines and the About page.
 */
function off_grade_map_text($map, $joiner = " ")
{
    return implode(", ", array_map(function ($g, $s) use ($joiner) {
        return off_grade_label($g) . $joiner . str_replace("/10", "", rating_score($s));
    }, array_keys($map), $map));
}

function off_grade_to_score($grade, $map = null)
{
    $grades = $map ?? off_grade_map();

    if (!is_string($grade))
    {
        return null;
    }

    return $grades[strtolower(trim($grade))] ?? null;
}

/**
 * Labels that speak to diet and animal welfare, and what each is worth.
 *
 * A brand with none of these is NOT scored zero -- it is left unassessed,
 * because an absent label means Open Food Facts has no record, not that the
 * brand fails. Only a product carrying at least one recognised label produces
 * a welfare score.
 */
function off_welfare_labels()
{
    return [
        "en:organic" => 1.5, "en:eu-organic" => 1.5, "en:soil-association-organic" => 2.0,
        "en:vegan" => 2.0, "en:vegetarian" => 1.0, "en:cruelty-free" => 2.0,
        "en:rspca-assured" => 2.0, "en:red-tractor" => 1.0,
        "en:marine-stewardship-council" => 1.5, "en:rainforest-alliance" => 1.0,
        "en:fairtrade" => 1.0, "en:fair-trade" => 1.0,
    ];
}

/**
 * Human-readable name for an OFF label tag, e.g. "en:rspca-assured" ->
 * "RSPCA Assured". Display only.
 */
function off_label_name($tag)
{
    $words = explode("-", preg_replace('/^[a-z]{2}:/', "", (string) $tag));
    $upper = ["eu", "rspca"];

    return implode(" ", array_map(function ($w) use ($upper) {
        return in_array($w, $upper, true) ? strtoupper($w) : ucfirst($w);
    }, $words));
}

/**
 * Derives a 1-10 welfare score from a product's labels, or null when the
 * product carries none we recognise.
 *
 * Starts from a neutral 5 and adds credit for each recognised label, capped
 * at 10. Deliberately cannot go below 5: a label's absence is missing
 * evidence, never evidence of harm.
 */
function off_welfare_score($labels)
{
    $known = off_welfare_labels();
    $credit = 0.0;
    $matched = false;

    foreach ((array) $labels as $tag)
    {
        if (is_string($tag) && isset($known[$tag]))
        {
            $credit += $known[$tag];
            $matched = true;
        }
    }

    return $matched ? min(10.0, 5.0 + $credit) : null;
}

/**
 * The recognised welfare labels on a product, as display names.
 */
function off_recognised_welfare_labels($labels)
{
    $known = off_welfare_labels();
    $out = [];

    foreach ((array) $labels as $tag)
    {
        if (is_string($tag) && isset($known[$tag]))
        {
            $out[] = off_label_name($tag);
        }
    }

    return $out;
}

/**
 * A brand's score for one dimension: the mean of what each product
 * contributed, to one decimal. The only place this rounding lives.
 */
function off_mean_points($contributions)
{
    $points = array_map(function ($c) { return (float) $c["points"]; }, $contributions);

    return round(array_sum($points) / count($points), 1);
}

/**
 * One line describing how a dimension score is derived, built from the same
 * tables the maths uses. Null for dimensions the importer does not derive.
 */
function off_dimension_method($dimension, $per_product = false)
{
    $grades = off_grade_map_text(off_grade_map());
    $eco = off_grade_map_text(off_eco_grade_map());

    switch ($dimension)
    {
        case "nutrition":
            return $per_product
                ? "Nutri-Score letter: $grades."
                : "Nutri-Score letter per product: $grades. Brand score is the average.";
        case "environment":
            return $per_product
                ? "Eco-Score (Green-Score) letter: $eco."
                : "Eco-Score (Green-Score) letter per product: $eco. Brand score is the average.";
        case "welfare":
            $credits = implode(", ", array_map(function ($tag, $c) {
                return off_label_name($tag) . " +" . rtrim(rtrim(number_format($c, 1), "0"), ".");
            }, array_keys(off_welfare_labels()), off_welfare_labels()));
            return $per_product
                ? "Starts at 5 and gains credit per recognised label ($credits), capped at 10."
                : "Each product starts at 5 and gains credit per recognised label ($credits), "
                  . "capped at 10. Brand score is the average.";
    }

    return null;
}

/**
 * Maps one Open Food Facts product into the fields the brands table holds.
 *
 * Returns null when the product cannot be used -- no brand name, or not sold
 * in the requested country. $country is an OFF country tag such as
 * "en:united-kingdom"; pass null to accept any.
 *
 * Note that this maps a *product* onto a *brand*. Several products collapse
 * onto one brand, which the importer resolves by first-write-wins plus
 * never overwriting an existing value.
 */
function map_off_product($product, $country = "en:united-kingdom")
{
    if (!is_array($product))
    {
        return null;
    }

    // country filter
    if ($country !== null)
    {
        $countries = $product["countries_tags"] ?? [];
        if (!is_array($countries) || !in_array($country, $countries, true))
        {
            return null;
        }
    }

    // brand name: prefer the human-cased "brands" string over the slug tags
    $brand = null;
    if (!empty($product["brands"]) && is_string($product["brands"]))
    {
        $first = explode(",", $product["brands"])[0];
        $brand = clean_value($first, 150);
    }
    if ($brand === null && !empty($product["brands_tags"][0]))
    {
        $brand = clean_value(off_tag_to_name($product["brands_tags"][0]), 150);
    }
    if ($brand === null)
    {
        return null;
    }

    // categories_tags runs general -> specific, so the ends give us
    // a category and a product type
    $categories = array_values(array_filter(
        (array) ($product["categories_tags"] ?? []),
        "is_string"
    ));
    $category = $categories ? off_tag_to_name($categories[0]) : null;
    $type = count($categories) > 1
        ? off_tag_to_name($categories[count($categories) - 1])
        : null;

    if ($type === null)
    {
        $type = clean_value($product["product_name"] ?? null, 100);
    }

    // where it is sold
    $availability = null;
    if (!empty($product["stores"]) && is_string($product["stores"]))
    {
        $availability = clean_value(explode(",", $product["stores"])[0], 100);
    }
    else if (!empty($product["stores_tags"][0]))
    {
        $availability = clean_value(off_tag_to_name($product["stores_tags"][0]), 100);
    }

    // recognised certifications only
    $known = off_certifications();
    $certifications = [];
    foreach ((array) ($product["labels_tags"] ?? []) as $tag)
    {
        if (is_string($tag) && isset($known[$tag]))
        {
            $certifications[$known[$tag]] = true;
        }
    }
    $certifications = $certifications
        ? clean_value(implode(", ", array_keys($certifications)))
        : null;

    $code = clean_value($product["code"] ?? null, 100);

    // per-dimension scores, so a reader can weight them themselves.
    // "environmental_score_grade" is the newer name for "ecoscore_grade";
    // accept either so a rename upstream does not silently drop the data.
    $env_grade = $product["environmental_score_grade"] ?? $product["ecoscore_grade"] ?? null;
    $nutri_grade = $product["nutriscore_grade"] ?? null;
    $labels = $product["labels_tags"] ?? [];

    $dimension_scores = array_filter([
        "environment" => off_grade_to_score($env_grade, off_eco_grade_map()),
        "nutrition"   => off_grade_to_score($nutri_grade),
        "welfare"     => off_welfare_score($labels),
    ], function ($v) { return $v !== null; });

    // what each score was derived from, kept so the brand page can show it
    $product_url = $code === null ? OFF_CANONICAL : OFF_CANONICAL . "/product/" . rawurlencode($code);
    $product_label = clean_value($product["product_name"] ?? null, 255);
    $contributions = [];

    foreach ($dimension_scores as $dimension => $points)
    {
        $input = $dimension === "welfare"
            ? implode(", ", off_recognised_welfare_labels($labels))
            : off_grade_label($dimension === "nutrition" ? $nutri_grade : $env_grade);

        $contributions[$dimension] = [
            "ref"    => $code,
            "label"  => $product_label,
            "input"  => clean_value($input, 255),
            "points" => $points,
            "url"    => $product_url,
        ];
    }

    return [
        "name"           => $brand,
        "category"       => $category === null ? null : mb_substr($category, 0, 100),
        "type"           => $type === null ? null : mb_substr($type, 0, 100),
        "availability"   => $availability,
        "certifications" => $certifications,

        // provenance: never import a fact without recording where it came from
        "source"         => OFF_SOURCE,
        "source_ref"     => $code,
        "source_url"     => $code === null
            ? OFF_CANONICAL
            : OFF_CANONICAL . "/product/" . rawurlencode($code),
        "source_licence" => OFF_LICENCE,
        "scores"         => $dimension_scores,
        "contributions"  => $contributions,

        // the product itself, for the products table
        "product"        => $code === null ? null : [
            "code"       => $code,
            "name"       => $product_label,
            "category"   => $categories ? mb_substr((string) off_tag_to_name($categories[count($categories) - 1]), 0, 100) : null,
            "labels"     => $certifications,
            "source_url" => $product_url,
            "brand_keys" => off_product_brand_keys($product),
        ],
    ];
}

/**
 * Every brand a product is tagged with, as brand_match_key() values: from the
 * human-cased "brands" string and from the brands_tags slugs.
 */
function off_product_brand_keys($product)
{
    $names = [];

    if (!empty($product["brands"]) && is_string($product["brands"]))
    {
        $names = explode(",", $product["brands"]);
    }

    foreach ((array) ($product["brands_tags"] ?? []) as $tag)
    {
        if (is_string($tag))
        {
            // strip an optional "xx:" language prefix
            // strip the language prefix, e.g. "xx:", "en:", "fr:"
            $names[] = preg_replace('/^[^:]*:/', "", $tag);
        }
    }

    $keys = [];
    foreach ($names as $name)
    {
        $key = brand_match_key($name);
        if ($key !== "")
        {
            $keys[$key] = true;
        }
    }

    return array_keys($keys);
}

/**
 * Collapses many product rows onto one row per brand.
 *
 * First non-empty value wins for each field, so an early product that knows a
 * brand's category is not overwritten by a later one that doesn't.
 */
function collapse_off_products($products, $country = "en:united-kingdom")
{
    $brands = [];
    $samples = [];
    $products_by_brand = [];

    foreach ($products as $product)
    {
        $mapped = map_off_product($product, $country);
        if ($mapped === null)
        {
            continue;
        }

        $key = mb_strtolower($mapped["name"]);

        if ($mapped["product"] !== null)
        {
            $products_by_brand[$key][$mapped["product"]["code"]] = $mapped;
        }

        if (!isset($brands[$key]))
        {
            $brands[$key] = $mapped;

            foreach ($mapped["contributions"] as $dimension => $contribution)
            {
                $samples[$key][$dimension][] = $contribution;
            }

            continue;
        }

        foreach ($mapped as $field => $value)
        {
            if ($field === "scores" || $field === "contributions" || $field === "product")
            {
                continue;
            }

            if (($brands[$key][$field] ?? null) === null && $value !== null)
            {
                $brands[$key][$field] = $value;
            }
        }

        // dimension scores accumulate across every product of the brand
        foreach ($mapped["contributions"] as $dimension => $contribution)
        {
            $samples[$key][$dimension][] = $contribution;
        }
    }

    // a brand's score for a dimension is the mean across its products
    foreach ($brands as $key => $_)
    {
        $averaged = [];

        foreach ($samples[$key] ?? [] as $dimension => $contributions)
        {
            $averaged[$dimension] = off_mean_points($contributions);
        }

        $brands[$key]["scores"] = $averaged;
        // per product, per dimension: what the averages above were taken over
        $brands[$key]["inputs"] = $samples[$key] ?? [];
        // every mapped product seen under this brand, one per barcode
        $brands[$key]["products"] = array_values($products_by_brand[$key] ?? []);
        unset($brands[$key]["contributions"], $brands[$key]["product"]);
    }

    return array_values($brands);
}

// ---------------------------------------------------------------- writing

/**
 * Writes derived dimension scores and the per-product inputs behind them,
 * without ever clobbering a curated one.
 *
 * A score a human set in /admin wins over anything derived, and gets no
 * inputs. Only scores this importer previously wrote are refreshed. For each
 * dimension the score and its inputs are replaced together in one
 * transaction, so the stored inputs always average to the stored score.
 *
 * $inputs is [dimension => [contribution, ...]] from collapse_off_products().
 */
function save_dimension_scores($brand_id, $scores, $source_url, $apply, &$notes, $inputs = [])
{
    foreach ($scores as $dimension => $value)
    {
        $existing = query(
            "SELECT source FROM brand_scores WHERE brand_id = ? AND dimension = ?",
            $brand_id, $dimension
        );

        if ($existing === false)
        {
            $notes[] = "could not read $dimension score";
            return false;
        }

        if (!empty($existing) && ($existing[0]["source"] ?? "") !== OFF_SOURCE)
        {
            $notes[] = "kept curated $dimension";
            continue;
        }

        $rows = $inputs[$dimension] ?? [];

        if (!$apply)
        {
            $notes[] = "$dimension=" . $value . ($rows ? " from " . count($rows) . " products" : "");
            continue;
        }

        if (query("START TRANSACTION") === false)
        {
            $notes[] = "could not start transaction for $dimension";
            return false;
        }

        $ok = query(
            "INSERT INTO brand_scores
                 (brand_id, dimension, score, note, source, source_url, retrieved_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE score = VALUES(score), note = VALUES(note),
                 source = VALUES(source), source_url = VALUES(source_url),
                 retrieved_at = VALUES(retrieved_at)",
            $brand_id, $dimension, $value,
            "Derived from Open Food Facts", OFF_SOURCE, $source_url
        ) !== false;

        $ok = $ok && query(
            "DELETE FROM brand_score_inputs WHERE brand_id = ? AND dimension = ?",
            $brand_id, $dimension
        ) !== false;

        foreach ($rows as $i => $row)
        {
            if (!$ok)
            {
                break;
            }

            $ok = query(
                "INSERT INTO brand_score_inputs
                     (brand_id, dimension, seq, ref, label, input, points, url)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                $brand_id, $dimension, $i + 1,
                $row["ref"], $row["label"], $row["input"], $row["points"], $row["url"]
            ) !== false;
        }

        if (!$ok)
        {
            query("ROLLBACK");
            $notes[] = "could not save $dimension score";
            return false;
        }

        query("COMMIT");
    }

    return true;
}

/**
 * Inputs behind a brand's derived scores, as [dimension => [row, ...]],
 * in the order they were recorded.
 */
function get_brand_score_inputs($brand_id)
{
    $rows = query(
        "SELECT dimension, ref, label, input, points, url
           FROM brand_score_inputs
          WHERE brand_id = ?
          ORDER BY dimension, seq",
        $brand_id
    );

    $out = [];
    foreach ($rows ?: [] as $row)
    {
        $out[$row["dimension"]][] = $row;
    }

    return $out;
}

/**
 * OFF brands_tags slug for a brand name, e.g. "Ben & Jerry's" -> "ben-jerry-s".
 */
function off_brand_tag($name)
{
    $slug = trim((string) $name);
    // Open Food Facts tags drop accents: "Café Verde" -> "cafe-verde"
    if (class_exists("Normalizer"))
    {
        $slug = preg_replace('/\p{Mn}+/u', "", Normalizer::normalize($slug, Normalizer::FORM_D));
    }
    $slug = mb_strtolower($slug);
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', "-", $slug);

    return trim($slug, "-");
}

/**
 * The mapped products (from map_off_product()) that belong to a brand:
 * those with a brand tag exactly equal to the brand's name after
 * brand_match_key() normalisation. Never a fuzzy match. One per barcode.
 */
function off_products_for_brand($brand_name, $mapped_products)
{
    $key = brand_match_key($brand_name);
    $out = [];

    if ($key === "")
    {
        return [];
    }

    foreach ($mapped_products as $m)
    {
        $p = $m["product"] ?? null;

        if ($p !== null && in_array($key, $p["brand_keys"], true) && !isset($out[$p["code"]]))
        {
            $out[$p["code"]] = $m;
        }
    }

    return array_values($out);
}

/**
 * Method line for a single product's score on a dimension.
 */
function off_product_method($dimension)
{
    return off_dimension_method($dimension, true);
}

/**
 * Writes a brand's products and their scores, then recomputes the brand's
 * Open Food Facts derived scores as the mean of its products' scores, all in
 * one transaction so brand and products cannot disagree.
 *
 *   $mapped    products from map_off_product(); only those matching the brand
 *              exactly (off_products_for_brand()) are written
 *   $complete  true when this run fetched every page for the brand; only then
 *              are products missing from the feed removed
 *
 * A product already filed under another brand is left there. A curated brand
 * score is never overwritten. Returns true on success; $notes says what
 * happened (or would happen, on a dry run).
 */
function save_brand_products($brand_id, $brand_name, $mapped, $apply, &$notes, $complete = false, $options = [])
{
    $mine = off_products_for_brand($brand_name, $mapped);
    // the dump's build date, or now for the API
    $retrieved_at = $options["retrieved_at"] ?? date("Y-m-d H:i:s");
    // the dump decides ownership for every product at once, so it may move a
    // product an earlier run filed under another brand
    $move = !empty($options["move"]);

    if (!$apply)
    {
        $notes[] = count($mine) . " products" . ($complete ? "" : " (no removals: partial feed)");
        foreach (off_scores_from_products($mine) as $dimension => $value)
        {
            $notes[] = "$dimension=$value";
        }
        return true;
    }

    if (query("START TRANSACTION") === false)
    {
        $notes[] = "could not start transaction";
        return false;
    }

    $ok = true;
    $kept = [];
    $elsewhere = 0;
    $moved_from = [];

    foreach ($mine as $m)
    {
        $p = $m["product"];

        $owner = query("SELECT id, brand_id FROM products WHERE code = ?", $p["code"]);
        $ok = $owner !== false;
        if (!$ok) { break; }

        if (!empty($owner) && (int) $owner[0]["brand_id"] !== (int) $brand_id)
        {
            if (!$move)
            {
                $elsewhere++;
                continue;
            }

            $moved_from[(int) $owner[0]["brand_id"]] = true;
            $ok = query("UPDATE products SET brand_id = ? WHERE id = ?", $brand_id, $owner[0]["id"]) !== false;
            if (!$ok) { break; }
        }

        $ok = query(
            "INSERT INTO products (brand_id, code, name, name_key, category, labels, source_url, retrieved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE name = VALUES(name), name_key = VALUES(name_key),
                 category = VALUES(category), labels = VALUES(labels),
                 source_url = VALUES(source_url), retrieved_at = VALUES(retrieved_at)",
            $brand_id, $p["code"], $p["name"], search_key($p["name"] ?? ""),
            $p["category"], $p["labels"], $p["source_url"], $retrieved_at
        ) !== false;
        if (!$ok) { break; }

        $id = query("SELECT id FROM products WHERE code = ?", $p["code"]);
        $ok = !empty($id);
        if (!$ok) { break; }
        $id = (int) $id[0]["id"];
        $kept[] = $p["code"];

        $ok = query("DELETE FROM product_scores WHERE product_id = ?", $id) !== false;

        foreach ($m["contributions"] as $dimension => $c)
        {
            if (!$ok) { break; }
            $ok = query(
                "INSERT INTO product_scores (product_id, dimension, score, input) VALUES (?, ?, ?, ?)",
                $id, $dimension, $c["points"], $c["input"]
            ) !== false;
        }
        if (!$ok) { break; }
    }

    $removed = 0;
    if ($ok && $complete)
    {
        $gone = $kept
            ? query("SELECT id FROM products WHERE brand_id = ? AND code NOT IN ("
                    . implode(",", array_fill(0, count($kept), "?")) . ")", $brand_id, ...$kept)
            : query("SELECT id FROM products WHERE brand_id = ?", $brand_id);
        $ok = $gone !== false;

        foreach ($gone ?: [] as $g)
        {
            if (!$ok) { break; }
            $ok = query("DELETE FROM products WHERE id = ?", $g["id"]) !== false;
            $removed++;
        }
    }

    // the brand's derived scores, from every product it now holds, and those
    // of any brand a product was moved away from
    if ($ok)
    {
        $ok = off_recompute_brand_scores($brand_id, $brand_name, $notes, $retrieved_at);
    }
    foreach (array_keys($moved_from) as $other)
    {
        if (!$ok) { break; }
        $name = query("SELECT name FROM brands WHERE id = ?", $other);
        $other_notes = [];
        $ok = !empty($name) && off_recompute_brand_scores($other, $name[0]["name"], $other_notes, $retrieved_at);
    }

    if (!$ok)
    {
        query("ROLLBACK");
        $notes[] = "could not save products";
        return false;
    }

    query("COMMIT");

    $notes[] = count($kept) . " products";
    if ($removed) { $notes[] = "removed $removed no longer listed"; }
    if ($elsewhere) { $notes[] = "$elsewhere already filed under another brand"; }
    if ($moved_from) { $notes[] = "moved products from " . count($moved_from) . " other brand(s)"; }

    return true;
}

/**
 * Recomputes a brand's Open Food Facts derived scores as the mean of its
 * products' scores. Never overwrites a curated score. Call inside the
 * caller's transaction. Returns false on a database error.
 */
function off_recompute_brand_scores($brand_id, $brand_name, &$notes, $retrieved_at)
{
    $rows = query(
        "SELECT ps.dimension, ps.score AS points
           FROM product_scores ps JOIN products p ON p.id = ps.product_id
          WHERE p.brand_id = ?
          ORDER BY p.id",
        $brand_id
    );
    if ($rows === false)
    {
        return false;
    }

    $by_dimension = [];
    foreach ($rows as $r)
    {
        $by_dimension[$r["dimension"]][] = $r;
    }

    foreach ($by_dimension as $dimension => $contributions)
    {
        $existing = query(
            "SELECT source FROM brand_scores WHERE brand_id = ? AND dimension = ?",
            $brand_id, $dimension
        );
        if ($existing === false)
        {
            return false;
        }

        if (!empty($existing) && ($existing[0]["source"] ?? "") !== OFF_SOURCE)
        {
            $notes[] = "kept curated $dimension";
            continue;
        }

        $value = off_mean_points($contributions);
        $ok = query(
            "INSERT INTO brand_scores
                 (brand_id, dimension, score, note, source, source_url, retrieved_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE score = VALUES(score), note = VALUES(note),
                 source = VALUES(source), source_url = VALUES(source_url),
                 retrieved_at = VALUES(retrieved_at)",
            $brand_id, $dimension, $value, "Derived from Open Food Facts", OFF_SOURCE,
            OFF_CANONICAL . "/brand/" . rawurlencode(off_brand_tag($brand_name)), $retrieved_at
        );
        if ($ok === false)
        {
            return false;
        }

        $notes[] = "$dimension=$value from " . count($contributions) . " products";
    }

    return true;
}

/**
 * Brand scores that save_brand_products() would derive from these mapped
 * products (dry runs and tests).
 */
function off_scores_from_products($mapped)
{
    $by_dimension = [];
    foreach ($mapped as $m)
    {
        foreach ($m["contributions"] as $dimension => $c)
        {
            $by_dimension[$dimension][] = $c;
        }
    }

    return array_map("off_mean_points", $by_dimension);
}

// ---------------------------------------------------------------- bulk dump

/**
 * Columns read from the Open Food Facts export, found by header name.
 * https://world.openfoodfacts.org/data
 */
const OFF_DUMP_COLUMNS = [
    "code", "url", "product_name", "brands", "brands_tags", "categories_tags",
    "labels_tags", "stores", "countries_tags", "nutriscore_grade",
    "environmental_score_grade",
];

// lines longer than this are treated as malformed and skipped
const OFF_DUMP_MAX_LINE = 1048576;

/**
 * Streams an Open Food Facts export (tab separated, one header line): the
 * full en.openfoodfacts.org.products.csv.gz, or a filtered subset with the
 * same header names, gzipped or plain. gzopen() reads both, so the file is
 * never decompressed to disk or loaded into memory.
 *
 * Yields one product array per usable row, in the shape the API returns
 * (tag fields as arrays), ready for map_off_product(). The file is untrusted
 * data: lines are split on tabs only (no CSV quoting), rows with the wrong
 * number of fields or no code are skipped and counted in $stats, and nothing
 * from it is ever executed.
 *
 *   $keep          optional function(array $product): bool, applied before yielding
 *   $stats         receives lines, malformed, kept
 *   $must_contain  optional text the raw countries_tags field must contain;
 *                  a fast pre-filter, $keep still decides
 */
function off_dump_rows($path, $keep = null, &$stats = null, $must_contain = null)
{
    $stats = ["lines" => 0, "malformed" => 0, "kept" => 0];

    $handle = @gzopen($path, "rb");
    if ($handle === false)
    {
        throw new RuntimeException("Cannot open dump: $path");
    }

    try
    {
        $header = gzgets($handle);
        if ($header === false)
        {
            throw new RuntimeException("Dump is empty: $path");
        }

        $names = explode("\t", rtrim($header, "\r\n"));
        $index = array_flip($names);
        $missing = array_diff(["code", "brands_tags", "countries_tags"], $names);
        if ($missing)
        {
            throw new RuntimeException("Dump header lacks: " . implode(", ", $missing));
        }

        $width = count($names);

        while (($line = gzgets($handle)) !== false)
        {
            $stats["lines"]++;

            // a line that hit the end of the buffer without a newline is
            // either the last line or longer than we accept
            if (strlen($line) > OFF_DUMP_MAX_LINE)
            {
                $stats["malformed"]++;
                continue;
            }

            $fields = explode("\t", rtrim($line, "\r\n"));
            if (count($fields) !== $width)
            {
                $stats["malformed"]++;
                continue;
            }

            // cheap rejection before building anything: most of the
            // 4.9 million rows are not sold in the country we want
            if ($must_contain !== null && strpos($fields[$index["countries_tags"]], $must_contain) === false)
            {
                continue;
            }

            $code = trim($fields[$index["code"]]);
            if ($code === "" || strlen($code) > 100)
            {
                $stats["malformed"]++;
                continue;
            }

            $product = ["code" => $code];
            foreach (OFF_DUMP_COLUMNS as $column)
            {
                if ($column === "code") { continue; }
                $value = isset($index[$column]) ? $fields[$index[$column]] : "";
                $product[$column] = substr($column, -5) === "_tags"
                    ? ($value === "" ? [] : array_values(array_filter(array_map("trim", explode(",", $value)), "strlen")))
                    : trim($value);
            }

            if ($keep !== null && !$keep($product))
            {
                continue;
            }

            $stats["kept"]++;
            yield $product;
        }
    }
    finally
    {
        gzclose($handle);
    }
}

/**
 * Reads a dump once and returns the mapped products for the brands we hold,
 * as [brand_id => [code => mapped product]].
 *
 * A row is kept when it is sold in $country (null for any) and one of its
 * brand tags, with the language prefix stripped, normalises to one of our
 * brand names (brand_match_key(), as for the API). A product is filed under
 * exactly one brand, because products.code is unique: the first of its own
 * brand tags that we hold. $stats also gets "shared", the number of products
 * tagged with more than one of our brands.
 *
 *   $brands  rows with id and name
 */
function off_dump_products_by_brand($path, $brands, $country, &$stats = null)
{
    $by_key = [];
    foreach ($brands as $b)
    {
        $key = brand_match_key($b["name"]);
        if ($key !== "" && !isset($by_key[$key]))
        {
            $by_key[$key] = (int) $b["id"];
        }
    }

    $keep = function ($p) use ($by_key, $country) {
        if ($country !== null && !in_array($country, $p["countries_tags"], true))
        {
            return false;
        }
        foreach (off_product_brand_keys($p) as $key)
        {
            if (isset($by_key[$key])) { return true; }
        }
        return false;
    };

    $out = [];
    $shared = 0;

    foreach (off_dump_rows($path, $keep, $read, $country) as $product)
    {
        $mapped = map_off_product($product, $country);
        if ($mapped === null || $mapped["product"] === null)
        {
            continue;
        }

        $owners = [];
        foreach ($mapped["product"]["brand_keys"] as $key)
        {
            if (isset($by_key[$key])) { $owners[$by_key[$key]] = true; }
        }
        if (count($owners) > 1) { $shared++; }

        // the first of its own tags that we hold; last row wins for a
        // barcode listed twice
        $brand_id = array_key_first($owners);
        foreach ($out as $id => $_)
        {
            unset($out[$id][$mapped["product"]["code"]]);
        }
        $out[$brand_id][$mapped["product"]["code"]] = $mapped;
    }

    $stats = $read + ["shared" => $shared];

    return $out;
}

/**
 * The date an export was built, from the file's modification time, as a
 * DATETIME string.
 */
function off_dump_date($path)
{
    $time = @filemtime($path);

    return date("Y-m-d H:i:s", $time === false ? time() : $time);
}

/**
 * What saving $mapped for a brand would change, without writing: products
 * added and removed against the database, grade counts per dimension, and
 * the derived score per dimension against the one stored now. Curated
 * brand scores are reported as kept.
 */
function off_plan_brand($brand_id, $mapped)
{
    $current = query("SELECT code FROM products WHERE brand_id = ?", $brand_id);
    $stored = query("SELECT dimension, score, source FROM brand_scores WHERE brand_id = ?", $brand_id);
    $current = array_column($current ?: [], "code");
    $codes = array_map(function ($m) { return $m["product"]["code"]; }, $mapped);

    $grades = [];
    foreach ($mapped as $m)
    {
        foreach ($m["contributions"] as $dimension => $c)
        {
            $label = $c["input"] ?? "";
            $grades[$dimension][$label] = ($grades[$dimension][$label] ?? 0) + 1;
        }
    }

    $existing = [];
    foreach ($stored ?: [] as $row)
    {
        $existing[$row["dimension"]] = $row;
    }

    $scores = [];
    foreach (off_scores_from_products($mapped) as $dimension => $value)
    {
        $have = $existing[$dimension] ?? null;
        $scores[$dimension] = [
            "new"     => $value,
            "old"     => $have === null ? null : (float) $have["score"],
            "curated" => $have !== null && $have["source"] !== OFF_SOURCE,
        ];
    }

    return [
        "products" => count($mapped),
        "added"    => count(array_diff($codes, $current)),
        "removed"  => count(array_diff($current, $codes)),
        "grades"   => $grades,
        "scores"   => $scores,
    ];
}
