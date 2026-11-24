<?php

require_once(__DIR__ . "/../includes/config.php");

// Caddy sends every missing path here; anything that is not a route has
// already been answered with a 404 by enforce_known_path()
$route = PHP_SAPI === "cli" ? "home" : front_controller_route(request_path());

if ($route !== "home")
{
    show_error_page(404);
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
    "categories" => $categories,
    "chosen" => has_chosen_priorities(),
]);
