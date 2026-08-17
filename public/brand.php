<?php

require_once(__DIR__ . "/../includes/config.php");

$name = input_string($_GET, "brand");

if ($name === "")
{
    apologize("No brand was specified.");
}

$brand = get_brand($name);

if ($brand === false)
{
    apologize("We could not load that brand just now. Please try again shortly.");
}

if ($brand === null)
{
    http_response_code(404);
    apologize("We don't have a rating for that brand yet.");
}

$weights = current_weights();

// the reader's own rating, plus the per-dimension detail behind it
$stored = get_brand_scores($brand["brand_id"]);
$values = brand_score_values($brand, $stored);

// other brands in the same category, scored the same way so the comparison
// is like for like
$alternatives = get_brands_in_category($brand["category"], $brand["brand"]);
$alternatives = attach_personal_scores($alternatives === false ? [] : $alternatives, $weights);

// best first by the reader's own measure, unscored last
usort($alternatives, function ($a, $b) {
    $x = $a["personal"]["score"];
    $y = $b["personal"]["score"];

    if ($x === null && $y === null) { return strcasecmp($a["brand"], $b["brand"]); }
    if ($x === null) { return 1; }
    if ($y === null) { return -1; }

    return $y <=> $x;
});

render("brand.php", [
    "title" => $brand["brand"],
    "brand" => $brand,
    "alternatives" => $alternatives,
    "weights" => $weights,
    "personal" => personal_score($values, $weights),
    "breakdown" => score_breakdown($values, $weights),
    "scores" => $stored,
    "chosen" => has_chosen_priorities(),
]);
