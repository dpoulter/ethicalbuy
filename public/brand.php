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

// other brands in the same category, so a poor rating comes with alternatives
$alternatives = get_brands_in_category($brand["category"], $brand["brand"]);

render("brand.php", [
    "title" => $brand["brand"],
    "brand" => $brand,
    "alternatives" => $alternatives === false ? [] : $alternatives,
]);
