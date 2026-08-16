<?php

require_once(__DIR__ . "/../includes/config.php");

// searching is a read, so it uses GET: results stay linkable and re-runnable
$search_string = trim($_GET["search_string"] ?? "");

// no search submitted yet -- show the form
if (!isset($_GET["search_string"]))
{
    render("search_form.php", ["title" => "Product Search"]);
    exit;
}

if ($search_string === "")
{
    apologize("You haven't entered a search string.");
}

$results = search_brands($search_string);

if ($results === false)
{
    apologize("We could not run that search just now. Please try again shortly.");
}

render("search_results.php", [
    "title" => "Search Results",
    "results" => $results,
    "search_string" => $search_string,
]);
