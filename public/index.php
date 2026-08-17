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

$weights = current_weights();
$rows = attach_personal_scores($rows, $weights);

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
