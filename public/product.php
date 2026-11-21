<?php

require_once(__DIR__ . "/../includes/config.php");

$code = input_string($_GET, "code");

if ($code === "")
{
    apologize("No product was specified.");
}

$product = get_product($code);

if ($product === false)
{
    apologize("We could not load that product just now. Please try again shortly.");
}

if ($product === null)
{
    http_response_code(404);
    apologize("We don't have that product.");
}

$weights = current_weights();

// a product's own Open Food Facts scores, plus the brand's editorial view
// and ownership, scored exactly as a brand is
$brand_stored = get_brand_scores($product["brand_id"]);
$brand_values = brand_score_values($product, $brand_stored);
$product_scores = get_scores_for_products([$product["id"]])[(int) $product["id"]] ?? [];
$values = product_score_values($product_scores, $brand_values);

render("product.php", [
    "title" => $product["name"] !== null && $product["name"] !== "" ? $product["name"] : "Product " . $product["code"],
    "product" => $product,
    "personal" => personal_score($values, $weights),
    "breakdown" => score_breakdown($values, $weights),
    "derivations" => product_derivations($product_scores, $product["brand"]),
    "chosen" => has_chosen_priorities(),
]);
