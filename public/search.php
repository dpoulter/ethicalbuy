<?php

require_once(__DIR__ . "/../includes/config.php");

enforce_browse_limit();

// searching is a read, so it uses GET: results stay linkable and re-runnable.
// "q" is the query; "search_string" is its older name and still works.
$term = input_string($_GET, "q", input_string($_GET, "search_string"));

// nobody needs more than this to find a brand; it also bounds the work
$term = mb_substr($term, 0, SEARCH_TERM_MAX);

$filters = [
    "term"         => $term,
    "field"        => input_string($_GET, "field", "brand"),
    "min_rating"   => input_string($_GET, "min_rating"),
    "availability" => input_string($_GET, "availability"),
    "sort"         => input_string($_GET, "sort", "relevance"),
];

// normalise anything the request invented, so the form redisplays valid values
if (!in_array($filters["field"], SEARCH_FIELDS, true))
{
    $filters["field"] = "brand";
}
if (!isset(SEARCH_SORTS[$filters["sort"]]))
{
    $filters["sort"] = "relevance";
}
if (!is_numeric($filters["min_rating"]))
{
    $filters["min_rating"] = "";
}

$weights = current_weights();

// an empty search with no filters is a valid "show me every brand" browse
$submitted = isset($_GET["submitted"]) || isset($_GET["q"]) || isset($_GET["search_string"]);
$results = [];
$products = [];
$product_total = 0;
$product_page = 1;
$product_pages = 1;

if ($submitted)
{
    // brands: the SQL applies the filters; the term is matched here on
    // normalised text so accents, apostrophes and "&" do not matter
    $results = search_brands(array_merge($filters, ["term" => ""]));

    if ($results === false)
    {
        apologize("We could not run that search just now. Please try again shortly.");
    }

    $results = attach_personal_scores($results, $weights);

    if ($filters["sort"] === "relevance" || $filters["sort"] === "personal")
    {
        // name match first (when there is a term), then the reader's score
        $results = rank_brand_results($results, $term, $filters["field"]);
    }
    else if ($term !== "")
    {
        // other sorts keep the SQL order, filtered by the same matching
        $keep = array_column(rank_brand_results($results, $term, $filters["field"]), "brand_id");
        $results = array_values(array_filter($results, function ($r) use ($keep) {
            return in_array($r["brand_id"], $keep, true);
        }));
    }

    // products: name matches only, never the whole catalogue
    if ($term !== "")
    {
        $found = search_products($term);

        if ($found === false)
        {
            apologize("We could not run that search just now. Please try again shortly.");
        }

        [$candidates, $product_total] = $found;
        [$products, $product_page, $product_pages] = paginate(
            rank_product_results($candidates, $weights),
            page_number(input_string($_GET, "page", "1"))
        );
    }
}

render("search.php", [
    "title" => $submitted ? "Search Results" : "Search",
    "description" => "Search Ethical Buy for a brand or product to see who owns it and how it scores.",
    "canonical" => "/search.php",
    // result pages are endless variations of one page
    "noindex" => $submitted,
    "filters" => $filters,
    "submitted" => $submitted,
    "results" => $results,
    "products" => $products,
    "brand_images" => brand_images(array_column($results, "brand_id")),
    "product_total" => $product_total,
    "product_page" => $product_page,
    "product_pages" => $product_pages,
    "availability_options" => get_availability_options(),
    "weights" => $weights,
    "chosen" => has_chosen_priorities(),
]);
