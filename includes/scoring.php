<?php

/**
 * scoring.php
 *
 * Personalised ratings.
 *
 * A single editorial score assumes everyone weighs ethics the same way. They
 * don't. Someone focused on climate wants carbon weighted heavily; a vegan
 * wants animal welfare to dominate; someone avoiding processed food cares
 * about nutrition. So the site stores a score per DIMENSION per brand, and the
 * number a reader sees is those dimensions weighted by priorities they set
 * themselves.
 *
 * Two properties matter more than the arithmetic:
 *
 *   1. Unknown is not zero. A brand with no carbon data is not a brand with
 *      bad carbon data. Missing dimensions are excluded from the average and
 *      reported, so a score is never quietly propped up or dragged down by
 *      data we do not have.
 *
 *   2. The reader is told what the score is missing. score_coverage() returns
 *      how much of what they asked for could actually be assessed, so
 *      "8.2/10" never hides the fact that it ignored the one thing they cared
 *      about most.
 *
 * All of this runs server-side. The rules exist once, in PHP. Re-implementing
 * them in JavaScript is how the original site ended up with two disagreeing
 * copies of its rating logic.
 */

require_once(__DIR__ . "/constants.php");

// weights run 0 (ignore) to 5 (critical); 3 is "normal"
const WEIGHT_MIN = 0;
const WEIGHT_MAX = 5;
const WEIGHT_DEFAULT = 3;

// below this fraction of requested weight, a score is flagged as thin
const COVERAGE_LOW = 0.5;

const PRIORITIES_COOKIE = "priorities";

/**
 * The dimensions a brand can be scored on, in display order.
 *
 * "editorial" is our own overall assessment, kept as one voice among several
 * rather than the only one. It is read from brands.rating, not stored in
 * brand_scores.
 */
function score_dimensions()
{
    return [
        "editorial" => [
            "label" => "Our overall view",
            "blurb" => "Our own assessment of the brand, all things considered.",
            "sourced" => "Written by us",
        ],
        "environment" => [
            "label" => "Environment & carbon",
            "blurb" => "Carbon footprint, packaging, and sourcing impact.",
            "sourced" => "Derived from Open Food Facts",
        ],
        "nutrition" => [
            "label" => "Nutrition",
            "blurb" => "Fat, sugar and salt across the brand's range.",
            "sourced" => "Derived from Open Food Facts",
        ],
        "welfare" => [
            "label" => "Diet & animal welfare",
            "blurb" => "Vegan, organic and animal welfare certification.",
            "sourced" => "Derived from Open Food Facts labels",
        ],
        "ownership" => [
            "label" => "Ownership & business size",
            "blurb" => "Independent, employee, women-led or minority-led ownership.",
            "sourced" => "Curated by us",
        ],
    ];
}

/**
 * Dimensions held in brand_scores. "editorial" is excluded because it lives
 * on brands.rating.
 */
function stored_dimensions()
{
    return array_values(array_diff(array_keys(score_dimensions()), ["editorial"]));
}

function is_dimension($key)
{
    return array_key_exists($key, score_dimensions());
}

/**
 * Everything weighted equally. Used until a reader says otherwise.
 */
function default_weights()
{
    return array_fill_keys(array_keys(score_dimensions()), WEIGHT_DEFAULT);
}

/**
 * Parses weights from a query-string-shaped value, e.g. from the cookie.
 *
 * Unknown dimensions are dropped, out-of-range values are clamped, and
 * anything missing falls back to the default. Never trusts the client to
 * supply a sane structure.
 */
function parse_weights($raw)
{
    $weights = default_weights();

    if (!is_string($raw) || $raw === "")
    {
        return $weights;
    }

    $parsed = [];
    parse_str($raw, $parsed);

    foreach ($parsed as $key => $value)
    {
        if (!is_dimension($key) || !is_scalar($value) || !is_numeric($value))
        {
            continue;
        }

        $weights[$key] = max(WEIGHT_MIN, min(WEIGHT_MAX, (int) $value));
    }

    return $weights;
}

/**
 * Serialises weights for the cookie.
 */
function encode_weights($weights)
{
    $clean = [];

    foreach (score_dimensions() as $key => $_)
    {
        if (isset($weights[$key]))
        {
            $clean[$key] = max(WEIGHT_MIN, min(WEIGHT_MAX, (int) $weights[$key]));
        }
    }

    return http_build_query($clean);
}

/**
 * This visitor's weights, from their cookie.
 */
function current_weights()
{
    return parse_weights($_COOKIE[PRIORITIES_COOKIE] ?? null);
}

/**
 * True if the visitor has actually chosen priorities, rather than being shown
 * the neutral default.
 */
function has_chosen_priorities()
{
    return isset($_COOKIE[PRIORITIES_COOKIE]) && $_COOKIE[PRIORITIES_COOKIE] !== "";
}

/**
 * Stores the visitor's weights.
 *
 * A first-party functional cookie holding five small integers. No identifier,
 * nothing personal, nothing shared, so it needs no consent banner under PECR
 * and creates no GDPR duty. Deliberately not a server-side profile.
 */
function save_weights($weights)
{
    setcookie(PRIORITIES_COOKIE, encode_weights($weights), [
        "expires"  => time() + (86400 * 365),
        "path"     => "/",
        "secure"   => is_secure_request(),
        "httponly" => false,
        "samesite" => "Lax",
    ]);
}

function clear_weights()
{
    setcookie(PRIORITIES_COOKIE, "", [
        "expires" => time() - 3600,
        "path"    => "/",
    ]);
}

/**
 * Computes a personalised score.
 *
 * $scores is [dimension => score|null]; $weights is [dimension => 0..5].
 *
 * Returns:
 *   score          float|null  weighted average, null when nothing could be assessed
 *   covered        array       dimensions that contributed
 *   missing        array       dimensions the reader asked for but we lack
 *   weight_used    int         weight actually applied
 *   weight_asked   int         weight the reader requested
 *   coverage       float       weight_used / weight_asked, 0..1
 *   confident      bool        whether coverage clears COVERAGE_LOW
 *
 * A dimension weighted 0 is ignored entirely: the reader said it does not
 * matter, so its absence is not a gap.
 */
function personal_score($scores, $weights)
{
    $numerator = 0.0;
    $weight_used = 0;
    $weight_asked = 0;
    $covered = [];
    $missing = [];

    foreach (score_dimensions() as $key => $_)
    {
        $weight = (int) ($weights[$key] ?? 0);

        if ($weight <= 0)
        {
            continue;
        }

        $weight_asked += $weight;

        $value = $scores[$key] ?? null;

        if ($value === null || $value === "" || !is_numeric($value))
        {
            $missing[] = $key;
            continue;
        }

        $numerator += $weight * (float) $value;
        $weight_used += $weight;
        $covered[] = $key;
    }

    $coverage = $weight_asked > 0 ? $weight_used / $weight_asked : 0.0;

    return [
        "score"        => $weight_used > 0 ? round($numerator / $weight_used, 1) : null,
        "covered"      => $covered,
        "missing"      => $missing,
        "weight_used"  => $weight_used,
        "weight_asked" => $weight_asked,
        "coverage"     => round($coverage, 3),
        "confident"    => $coverage >= COVERAGE_LOW,
    ];
}

/**
 * Per-dimension detail for the "why this score" breakdown, in display order.
 */
function score_breakdown($scores, $weights)
{
    $rows = [];

    foreach (score_dimensions() as $key => $meta)
    {
        $value = $scores[$key] ?? null;
        $has = !($value === null || $value === "" || !is_numeric($value));

        $rows[] = [
            "key"      => $key,
            "label"    => $meta["label"],
            "sourced"  => $meta["sourced"],
            "weight"   => (int) ($weights[$key] ?? 0),
            "score"    => $has ? (float) $value : null,
            "counted"  => $has && (int) ($weights[$key] ?? 0) > 0,
        ];
    }

    return $rows;
}

/**
 * Plain-English label for a weight, for the priorities form.
 */
function weight_label($weight)
{
    switch ((int) $weight)
    {
        case 0: return "Ignore";
        case 1: return "Slight";
        case 2: return "Some";
        case 3: return "Normal";
        case 4: return "High";
        default: return "Critical";
    }
}
