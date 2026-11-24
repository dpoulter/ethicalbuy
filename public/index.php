<?php

require_once(__DIR__ . "/../includes/config.php");

// Caddy sends every missing path here; anything that is not a route has
// already been answered with a 404 by enforce_known_path()
$route = PHP_SAPI === "cli" ? "home" : front_controller_route(request_path());

if ($route === "robots")
{
    header("Content-Type: text/plain; charset=UTF-8");
    header("Cache-Control: public, max-age=3600");
    echo robots_txt();
    exit;
}

enforce_browse_limit();

if ($route !== "home")
{
    $part = preg_match('#^/sitemap-products-(\d+)\.xml$#', request_path(), $m) ? (int) $m[1] : 1;
    $xml = cached_sitemap($route, $part);

    if ($xml === false)
    {
        show_error_page(503);
    }
    if ($xml === null)
    {
        show_error_page(404);
    }

    header("Content-Type: application/xml; charset=UTF-8");
    header("Cache-Control: public, max-age=3600");
    echo $xml;
    exit;
}

$rows = get_categories();

// handle failure before rendering anything, so apologize() owns the whole page
if ($rows === false)
{
    apologize("We could not load product categories just now. Please try again shortly.");
}

if (!$rows)
{
    apologize("No brand categories found.");
}

$weights = current_weights();
$rows = attach_personal_scores($rows, $weights);
$images = brand_images(array_column($rows, "brand_id"));

// group brands by category
$categories = [];
foreach ($rows as $row)
{
    $category = ($row["category"] === null || $row["category"] === "")
        ? "Uncategorised"
        : $row["category"];

    $categories[$category][] = [
        "brand" => $row["brand"],
        // hotlinked from Open Food Facts; only their image servers are allowed
        "image" => $images[(int) $row["brand_id"]] ?? null,
        "type" => $row["type"],
        "notes" => $row["notes"],
        "owner" => $row["owner"],
        "availability" => $row["availability"],

        // scored server-side so PHP and JS can't disagree, and so the
        // personalised weighting exists in exactly one implementation
        "ratingClass" => rating_class($row["personal"]["score"]),
        "ratingScore" => $row["personal"]["score"] === null
            ? "No data for your priorities"
            : rating_score($row["personal"]["score"]),
        "ratingLabel" => rating_class($row["personal"]["score"]) === ""
            ? ""
            : rating_label($row["personal"]["score"]),
        "editorial"   => rating_score($row["rating"]),
        "thin"        => !$row["personal"]["confident"],
    ];
}

render("category_form.php", [
    "title" => "Categories",
    "canonical" => "/",
    "categories" => $categories,
    "chosen" => has_chosen_priorities(),
]);
