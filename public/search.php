<?php

require_once(__DIR__ . "/../includes/config.php");

// searching is a read, so it uses GET: results stay linkable and re-runnable
$filters = [
    "term"         => input_string($_GET, "search_string"),
    "field"        => input_string($_GET, "field", "brand"),
    "min_rating"   => input_string($_GET, "min_rating"),
    "availability" => input_string($_GET, "availability"),
    "sort"         => input_string($_GET, "sort", "brand"),
];

// normalise anything the request invented, so the form redisplays valid values
if (!in_array($filters["field"], SEARCH_FIELDS, true))
{
    $filters["field"] = "brand";
}
if (!isset(SEARCH_SORTS[$filters["sort"]]))
{
    $filters["sort"] = "brand";
}
if (!is_numeric($filters["min_rating"]))
{
    $filters["min_rating"] = "";
}

$weights = current_weights();

// an empty search with no filters is a valid "show me everything" browse
$submitted = isset($_GET["submitted"]);
$results = [];

if ($submitted)
{
    $results = search_brands($filters);

    if ($results === false)
    {
        apologize("We could not run that search just now. Please try again shortly.");
    }

    $results = attach_personal_scores($results, $weights);

    // personalised ranking is computed per reader, so it happens here rather
    // than in SQL. Brands we could not score for these priorities sort last.
    if ($filters["sort"] === "personal")
    {
        usort($results, function ($a, $b) {
            $x = $a["personal"]["score"];
            $y = $b["personal"]["score"];

            if ($x === null && $y === null) { return strcasecmp($a["brand"], $b["brand"]); }
            if ($x === null) { return 1; }
            if ($y === null) { return -1; }

            return $y <=> $x;
        });
    }
}

render("search.php", [
    "title" => $submitted ? "Search Results" : "Product Search",
    "filters" => $filters,
    "submitted" => $submitted,
    "results" => $results,
    "availability_options" => get_availability_options(),
    "weights" => $weights,
    "chosen" => has_chosen_priorities(),
]);
