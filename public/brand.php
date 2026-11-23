<?php

require_once(__DIR__ . "/../includes/config.php");
require_once(__DIR__ . "/../includes/import.php");

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

// products table: optional name filter and page number
$product_filter = input_string($_GET, "pq");
$product_page = (int) input_string($_GET, "pp", "1");

// the reader's own rating, plus the per-dimension detail behind it
$stored = get_brand_scores($brand["brand_id"]);
$values = brand_score_values($brand, $stored);

// the brand's products, scored the same way as the brand
$brand_products = get_brand_products($brand["brand_id"]);
if ($brand_products === false)
{
    apologize("We could not load that brand's products just now. Please try again shortly.");
}

// each product scored like the brand: its own Open Food Facts dimensions
// plus the brand's editorial view and ownership
$listed = attach_product_personal_scores($brand_products, $values, $weights);
if ($product_filter !== "")
{
    $needle = search_key($product_filter);
    $listed = array_values(array_filter($listed, function ($p) use ($needle) {
        return $needle === "" || strpos(search_key((string) $p["name"]), $needle) !== false;
    }));
}
sort_by_personal_score($listed);
[$product_rows, $product_page, $product_pages] = paginate($listed, $product_page);

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
    "derivations" => brand_derivations($stored, $brand_products, get_brand_score_inputs($brand["brand_id"])),
    "chosen" => has_chosen_priorities(),
    "product_total" => count($brand_products),
    "product_matches" => count($listed),
    "product_rows" => $product_rows,
    "product_page" => $product_page,
    "product_pages" => $product_pages,
    "product_filter" => $product_filter,
    "brand_image" => brand_images([$brand["brand_id"]])[(int) $brand["brand_id"]] ?? null,
    "alt_images" => brand_images(array_column($alternatives, "brand_id")),
]);
