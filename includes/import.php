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
    ];
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

    foreach ($products as $product)
    {
        $mapped = map_off_product($product, $country);
        if ($mapped === null)
        {
            continue;
        }

        $key = mb_strtolower($mapped["name"]);

        if (!isset($brands[$key]))
        {
            $brands[$key] = $mapped;
            continue;
        }

        foreach ($mapped as $field => $value)
        {
            if (($brands[$key][$field] ?? null) === null && $value !== null)
            {
                $brands[$key][$field] = $value;
            }
        }
    }

    return array_values($brands);
}
