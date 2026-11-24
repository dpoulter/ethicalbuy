<?php

require_once(__DIR__ . "/../includes/config.php");

enforce_browse_limit();

$code = input_string($_GET, "code");

if ($code === "")
{
    http_response_code(404);
    apologize("No product was specified.");
}

// barcodes and Open Food Facts codes only; anything else is not a product
if (!is_product_code($code))
{
    show_error_page(404);
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

$product_title = $product["name"] !== null && $product["name"] !== "" ? $product["name"] : "Product " . $product["code"];

render("product.php", [
    "canonical" => product_path($product["code"]),
    "description" => $product_title . " by " . $product["brand"]
        . ". See how it scores on environment, nutrition, animal welfare and who owns the brand.",
    "og_image" => $product["image_url"] ?? null,
    "title" => $product["name"] !== null && $product["name"] !== "" ? $product["name"] : "Product " . $product["code"],
    "product" => $product,
    "personal" => personal_score($values, $weights),
    "breakdown" => score_breakdown($values, $weights),
    "derivations" => product_derivations($product_scores, $product["brand"],
        brand_derivations($brand_stored, [], [])),
    "chosen" => has_chosen_priorities(),
]);
