<?php

/**
 * import_companies.php
 *
 * Mapping helpers for Companies House.
 *
 * Two rules shape everything here.
 *
 * 1. NEVER guess an ownership link.
 *
 *    "Northwind Foods Group" matches several real registered companies. Wiring
 *    a brand to the wrong one publishes a false statement about a real
 *    business, which is both wrong and exactly the sort of thing that attracts
 *    a defamation claim. So a link is only made automatically when the
 *    normalised name matches exactly and unambiguously. Anything else is
 *    recorded as a candidate for a human to confirm in /admin.
 *
 * 2. NEVER store personal data about individuals.
 *
 *    The persons-with-significant-control register names real people, with
 *    partial dates of birth, nationality and country of residence. Copying
 *    that onto a public consumer site would put us squarely inside UK GDPR for
 *    no product benefit. Only CORPORATE controlling entities are recorded --
 *    those answer "which company owns this company", which is the actual
 *    question. Individual PSCs are discarded on sight.
 *
 * Companies House data is Open Government Licence v3.0: free to reuse with
 * attribution.
 */

require_once(__DIR__ . "/constants.php");

const CH_SOURCE   = "companieshouse";
const CH_LICENCE  = "OGL v3.0";
const CH_CANONICAL = "https://find-and-update.company-information.service.gov.uk";

define("CH_API", rtrim(env("CH_API", "https://api.company-information.service.gov.uk"), "/"));

/**
 * Legal-form suffixes that carry no identifying information.
 * "Acme Ltd" and "Acme Limited" are the same name for matching purposes.
 */
function company_name_suffixes()
{
    return [
        "LIMITED", "LTD", "PLC", "PUBLIC LIMITED COMPANY", "LLP", "LP", "LLC",
        "INCORPORATED", "INC", "CIC", "CIO", "COMPANY", "CO", "THE",
    ];
}

/**
 * Reduces a company name to a comparable form: upper case, no punctuation,
 * no legal-form suffix, single spaces.
 *
 *   "O'Donnell Family Farms Ltd." -> "ODONNELL FAMILY FARMS"
 */
function normalise_company_name($name)
{
    $name = mb_strtoupper(trim((string) $name));

    // "&" and "AND" are used interchangeably in registered names
    $name = str_replace("&", " AND ", $name);

    // drop punctuation, keep alphanumerics and spaces
    $name = preg_replace('/[^A-Z0-9 ]+/u', "", $name);
    $name = trim(preg_replace('/\s+/', " ", $name));

    if ($name === "")
    {
        return "";
    }

    $suffixes = company_name_suffixes();
    $words = explode(" ", $name);

    // strip trailing legal-form words, repeatedly ("ACME CO LIMITED")
    while ($words && in_array(end($words), $suffixes, true))
    {
        array_pop($words);
    }

    // and a leading "THE"
    while ($words && $words[0] === "THE")
    {
        array_shift($words);
    }

    return implode(" ", $words);
}

/**
 * Scores a search result against the name we hold, 0-100.
 *
 * Only 100 -- an exact normalised match -- is ever good enough to link
 * automatically. Lower scores exist to rank the candidate list a human sees.
 */
function score_company_match($our_name, $candidate_name)
{
    $a = normalise_company_name($our_name);
    $b = normalise_company_name($candidate_name);

    if ($a === "" || $b === "")
    {
        return 0;
    }

    if ($a === $b)
    {
        return 100;
    }

    // one name contains the other: plausible, not certain
    if (strpos($b, $a) === 0 || strpos($a, $b) === 0)
    {
        return 80;
    }

    // otherwise fall back to shared words
    $wordsA = array_unique(explode(" ", $a));
    $wordsB = array_unique(explode(" ", $b));
    $shared = count(array_intersect($wordsA, $wordsB));
    $total = count(array_unique(array_merge($wordsA, $wordsB)));

    return $total === 0 ? 0 : (int) round(60 * $shared / $total);
}

/**
 * Maps one Companies House search result into a candidate row.
 */
function map_company_candidate($item, $our_name)
{
    if (!is_array($item) || empty($item["company_number"]))
    {
        return null;
    }

    $title = trim((string) ($item["title"] ?? ""));

    if ($title === "")
    {
        return null;
    }

    return [
        "company_number"  => mb_substr(trim((string) $item["company_number"]), 0, 20),
        "company_name"    => mb_substr($title, 0, 200),
        "company_status"  => mb_substr(trim((string) ($item["company_status"] ?? "")), 0, 50) ?: null,
        "address_snippet" => mb_substr(trim((string) ($item["address_snippet"] ?? "")), 0, 255) ?: null,
        "score"           => score_company_match($our_name, $title),
    ];
}

/**
 * Ranks search results, best first.
 */
function rank_company_candidates($items, $our_name)
{
    $candidates = [];

    foreach ((array) $items as $item)
    {
        $mapped = map_company_candidate($item, $our_name);

        if ($mapped !== null)
        {
            $candidates[] = $mapped;
        }
    }

    usort($candidates, function ($x, $y) {
        if ($x["score"] !== $y["score"])
        {
            return $y["score"] <=> $x["score"];
        }

        // prefer an active company when scores tie
        $activeX = ($x["company_status"] ?? "") === "active" ? 1 : 0;
        $activeY = ($y["company_status"] ?? "") === "active" ? 1 : 0;

        return $activeY <=> $activeX;
    });

    return $candidates;
}

/**
 * Decides whether we may link automatically.
 *
 * Returns [candidate|null, status, reason] where status is one of
 * "confirmed" or "ambiguous". Deliberately strict: a perfect match must also
 * be unique and active.
 */
function choose_company_match($our_name, $candidates)
{
    if (!$candidates)
    {
        return [null, "ambiguous", "no results"];
    }

    $perfect = array_values(array_filter($candidates, function ($c) {
        return $c["score"] === 100;
    }));

    if (!$perfect)
    {
        return [null, "ambiguous", "no exact name match (best "
            . $candidates[0]["score"] . "%: " . $candidates[0]["company_name"] . ")"];
    }

    $active = array_values(array_filter($perfect, function ($c) {
        return ($c["company_status"] ?? "") === "active";
    }));

    if (count($active) === 1)
    {
        return [$active[0], "confirmed", "exact unique active match"];
    }

    if (count($active) > 1)
    {
        return [null, "ambiguous", count($active) . " active companies share that exact name"];
    }

    return [null, "ambiguous", "exact match but no active company (dissolved?)"];
}

/**
 * Maps a company profile response into the columns we store.
 */
function map_company_profile($profile)
{
    if (!is_array($profile) || empty($profile["company_number"]))
    {
        return null;
    }

    $number = mb_substr(trim((string) $profile["company_number"]), 0, 20);

    $created = $profile["date_of_creation"] ?? null;
    if (!is_string($created) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $created))
    {
        $created = null;
    }

    return [
        "company_number"  => $number,
        "company_name"    => mb_substr(trim((string) ($profile["company_name"] ?? "")), 0, 200) ?: null,
        "company_status"  => mb_substr(trim((string) ($profile["company_status"] ?? "")), 0, 50) ?: null,
        "incorporated_on" => $created,
        "source"          => CH_SOURCE,
        "source_url"      => CH_CANONICAL . "/company/" . rawurlencode($number),
        "source_licence"  => CH_LICENCE,
    ];
}

/**
 * Extracts CORPORATE controlling entities from a PSC response.
 *
 * Individual people are dropped: see the note at the top of this file. Ceased
 * entries are dropped too, since they no longer describe who is in control.
 */
function map_corporate_pscs($psc_response)
{
    $items = $psc_response["items"] ?? [];
    $parents = [];

    foreach ((array) $items as $item)
    {
        if (!is_array($item))
        {
            continue;
        }

        $kind = (string) ($item["kind"] ?? "");

        // corporate-entity-... and legal-person-... are organisations.
        // individual-person-... and super-secure-... are people.
        $is_organisation = strpos($kind, "corporate-entity") === 0
            || strpos($kind, "legal-person") === 0;

        if (!$is_organisation)
        {
            continue;
        }

        if (!empty($item["ceased_on"]) || !empty($item["ceased"]))
        {
            continue;
        }

        $name = trim((string) ($item["name"] ?? ""));

        if ($name === "")
        {
            continue;
        }

        $registration = $item["identification"]["registration_number"] ?? null;
        $registration = is_scalar($registration)
            ? mb_substr(trim((string) $registration), 0, 20)
            : null;

        $parents[] = [
            "name"   => mb_substr($name, 0, 200),
            "number" => ($registration === "" ? null : $registration),
        ];
    }

    return $parents;
}
