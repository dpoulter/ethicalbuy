<?php
/**
 * Reading products for the public site. Requires migration 006.
 *
 * Products come only from Open Food Facts (see includes/import.php). A
 * product's own scores cover the dimensions Open Food Facts can inform; the
 * editorial view and ownership are the brand's, shown on the product page as
 * inherited.
 */

// products per page on the brand page and in search results
const PRODUCTS_PER_PAGE = 25;

// search ranks at most this many name matches by reader score
const PRODUCT_SEARCH_CANDIDATES = 1000;

const PRODUCT_DIMENSIONS = ["environment", "nutrition", "welfare"];

require_once(__DIR__ . "/import.php");

/**
 * "a, b" -> "t.a, t.b", so a join cannot make a column ambiguous.
 */
function prefixed_columns($table, $columns)
{
    return implode(", ", array_map(function ($c) use ($table) {
        return $table . "." . trim($c);
    }, explode(",", $columns)));
}

/**
 * One product with its brand's public fields, or null if unknown, false on
 * a database error.
 */
function get_product($code)
{
    $rows = query(
        "SELECT p.id, p.code, p.name, p.category AS product_category, p.labels,
                p.source_url AS product_url, p.retrieved_at AS product_retrieved_at,
                " . prefixed_columns("brand_v", BRAND_DETAIL_COLUMNS) . "
           FROM products p
           JOIN brand_v ON brand_v.brand_id = p.brand_id
          WHERE p.code = ?
          LIMIT 1",
        (string) $code
    );

    if ($rows === false)
    {
        return false;
    }

    return $rows[0] ?? null;
}

/**
 * [product_id => [dimension => ["score" => float, "input" => string]]]
 */
function get_scores_for_products($product_ids)
{
    $ids = array_values(array_unique(array_map("intval", $product_ids)));
    if (!$ids)
    {
        return [];
    }

    $rows = query(
        "SELECT product_id, dimension, score, input FROM product_scores
          WHERE product_id IN (" . implode(",", array_fill(0, count($ids), "?")) . ")",
        ...$ids
    );

    $out = [];
    foreach ($rows ?: [] as $r)
    {
        $out[(int) $r["product_id"]][$r["dimension"]] = [
            "score" => (float) $r["score"],
            "input" => $r["input"],
        ];
    }

    return $out;
}

/**
 * Score values for a product, in the shape personal_score() takes: its own
 * Open Food Facts dimensions plus the brand's editorial rating and ownership.
 */
function product_score_values($product_scores, $brand_values)
{
    $values = [
        "editorial" => $brand_values["editorial"] ?? null,
        "ownership" => $brand_values["ownership"] ?? null,
    ];

    foreach (PRODUCT_DIMENSIONS as $dimension)
    {
        $values[$dimension] = isset($product_scores[$dimension])
            ? $product_scores[$dimension]["score"]
            : null;
    }

    return $values;
}

/**
 * All of a brand's products with their scores, for the brand page.
 */
function get_brand_products($brand_id)
{
    $rows = query(
        "SELECT id, code, name, labels FROM products WHERE brand_id = ? ORDER BY name",
        (int) $brand_id
    );

    if ($rows === false)
    {
        return false;
    }

    $scores = get_scores_for_products(array_column($rows, "id"));
    foreach ($rows as $i => $row)
    {
        $rows[$i]["scores"] = $scores[(int) $row["id"]] ?? [];
    }

    return $rows;
}

/**
 * Adds "values" and "personal" to each product, scored like a brand.
 * $brand_values maps brand_id => brand score values, or pass one array of
 * values to use for every product.
 */
function attach_product_personal_scores($products, $brand_values, $weights)
{
    foreach ($products as $i => $p)
    {
        $bv = isset($p["brand_id"], $brand_values[$p["brand_id"]]) && is_array($brand_values[$p["brand_id"]])
            ? $brand_values[$p["brand_id"]]
            : $brand_values;
        $products[$i]["values"] = product_score_values($p["scores"], $bv);
        $products[$i]["personal"] = personal_score($products[$i]["values"], $weights);
    }

    return $products;
}

/**
 * Best personal score first, unscored last, then by name.
 */
function sort_by_personal_score(&$rows, $name_key = "name")
{
    usort($rows, function ($a, $b) use ($name_key) {
        $x = $a["personal"]["score"];
        $y = $b["personal"]["score"];

        if ($x === null && $y === null) { return strcasecmp((string) $a[$name_key], (string) $b[$name_key]); }
        if ($x === null) { return 1; }
        if ($y === null) { return -1; }

        return $y <=> $x ?: strcasecmp((string) $a[$name_key], (string) $b[$name_key]);
    });
}

/**
 * Slices one page out of $rows. Returns [rows, page, pages].
 */
function paginate($rows, $page, $per_page = PRODUCTS_PER_PAGE)
{
    $pages = max(1, (int) ceil(count($rows) / $per_page));
    $page = min(max(1, (int) $page), $pages);

    return [array_slice($rows, ($page - 1) * $per_page, $per_page), $page, $pages];
}

/**
 * How each of a brand's dimension scores was reached, for score_card.php.
 * Derived scores are explained from the brand's products; a brand with no
 * products yet falls back to the evidence rows of migration 005.
 */
function brand_derivations($stored_scores, $products, $legacy_inputs)
{
    $out = ["editorial" => ["kind" => "hand"]];

    foreach ($stored_scores as $dimension => $stored)
    {
        if (($stored["source"] ?? "") !== OFF_SOURCE)
        {
            // a hand edit in /admin supersedes any derived evidence
            $out[$dimension] = ["kind" => "hand"];
            continue;
        }

        $rows = [];
        foreach ($products ?: [] as $p)
        {
            if (isset($p["scores"][$dimension]))
            {
                $rows[] = [
                    "label"  => $p["name"] !== null && $p["name"] !== "" ? $p["name"] : "Product " . $p["code"],
                    "href"   => "product.php?code=" . rawurlencode($p["code"]),
                    "input"  => $p["scores"][$dimension]["input"],
                    "points" => $p["scores"][$dimension]["score"],
                ];
            }
        }

        if (!$rows && empty($products))
        {
            foreach ($legacy_inputs[$dimension] ?? [] as $in)
            {
                $rows[] = [
                    "label"  => trim((string) ($in["label"] ?? "")) !== "" ? $in["label"] : "Product " . ($in["ref"] ?? ""),
                    "url"    => $in["url"] ?? null,
                    "input"  => $in["input"] ?? null,
                    "points" => $in["points"],
                ];
            }
        }

        $out[$dimension] = $rows
            ? ["kind" => "list", "method" => off_dimension_method($dimension), "rows" => $rows]
            : ["kind" => "not_recorded", "source_url" => $stored["source_url"] ?? null];
    }

    return $out;
}

/**
 * How each of a product's dimension scores was reached.
 */
function product_derivations($product_scores, $brand_name)
{
    $out = [
        "editorial" => ["kind" => "inherited", "brand" => $brand_name],
        "ownership" => ["kind" => "inherited", "brand" => $brand_name],
    ];

    foreach ($product_scores as $dimension => $s)
    {
        $out[$dimension] = [
            "kind"   => "single",
            "method" => off_product_method($dimension),
            "input"  => $s["input"],
            "points" => $s["score"],
        ];
    }

    return $out;
}
