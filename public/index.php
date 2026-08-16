<?php

require_once(__DIR__ . "/../includes/config.php");

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

// group brands by category
$categories = [];
foreach ($rows as $row)
{
    $category = ($row["category"] === null || $row["category"] === "")
        ? "Uncategorised"
        : $row["category"];

    $categories[$category][] = [
        "brand" => $row["brand"],
        "type" => $row["type"],
        "notes" => $row["notes"],
        "owner" => $row["owner"],
        "availability" => $row["availability"],

        // decided server-side so PHP and JS can't disagree about colours
        "ratingClass" => rating_class($row["rating"]),
    ];
}

render("category_form.php", ["title" => "Categories", "categories" => $categories]);
