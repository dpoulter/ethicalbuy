<?php
/**
 * Throwaway volume check for products: loads N synthetic products into the
 * DEVELOPMENT database (as root, via the mysql client), then times the
 * product list and search queries and prints their EXPLAIN plans.
 *
 *   php dev/bench-products.php [N=15000]
 *
 * Creates brands named "Bench Brand nn" and removes them first, so it can be
 * re-run. Never point this at the live database.
 */

if (PHP_SAPI !== "cli") { exit; }

putenv("DB_NAME=" . (getenv("DB_NAME") ?: "ethicalbuy"));
putenv("DB_USER=" . (getenv("DB_USER") ?: "ethicalbuy"));
putenv("DB_PASSWORD=" . (getenv("DB_PASSWORD") ?: "ethicalbuy_dev"));
putenv("APP_DEBUG=0");
require_once(__DIR__ . "/../includes/config.php");

$n = (int) ($argv[1] ?? 15000);
$words = ["oat", "soya", "almond", "chocolate", "biscuit", "drink", "milk", "dark", "organic", "crème",
          "brûlée", "yoghurt", "vanilla", "strawberry", "cookie", "dough", "bar", "crisps", "salted", "lightly",
          "cheese", "cheddar", "mature", "bread", "seeded", "wholemeal", "orange", "juice", "smooth", "bits"];
$grades = [10, 8, 6, 4, 2];

// 60 brands; the first holds 2,000 products, like a large UK brand
$sql = "DELETE FROM brands WHERE name LIKE 'Bench Brand %';\n";
for ($b = 1; $b <= 60; $b++) {
    $sql .= sprintf("INSERT INTO brands (name, rating) VALUES ('Bench Brand %02d', %s);\n", $b, $b % 3 ? $b % 10 + 1 : "NULL");
}
mt_srand(42);
$rows = []; $scores = [];
for ($i = 1; $i <= $n; $i++) {
    $brand = $i <= 2000 ? 1 : 2 + ($i % 59);
    $name = ucfirst(implode(" ", array_map(function () use ($words) { return $words[mt_rand(0, count($words) - 1)]; }, range(1, mt_rand(2, 5)))));
    $rows[] = sprintf("((SELECT id FROM brands WHERE name = 'Bench Brand %02d'), 'B%07d', '%s', '%s', NOW())",
        $brand, $i, addslashes($name), addslashes(search_key($name)));
    foreach (["nutrition", "environment", "welfare"] as $k => $dim) {
        if (mt_rand(0, 9) < 7 - $k * 2) {
            $scores[] = sprintf("((SELECT id FROM products WHERE code = 'B%07d'), '%s', %d, 'X')", $i, $dim, $grades[mt_rand(0, 4)]);
        }
    }
}
foreach (array_chunk($rows, 1000) as $chunk) {
    $sql .= "INSERT INTO products (brand_id, code, name, name_key, retrieved_at) VALUES " . implode(",", $chunk) . ";\n";
}
foreach (array_chunk($scores, 1000) as $chunk) {
    $sql .= "INSERT INTO product_scores (product_id, dimension, score, input) VALUES " . implode(",", $chunk) . ";\n";
}
$sql .= "ANALYZE TABLE products, product_scores;\n";
$tmp = tempnam(sys_get_temp_dir(), "bench");
file_put_contents($tmp, $sql);
$t = microtime(true);
passthru("mysql " . escapeshellarg(getenv("DB_NAME")) . " < " . escapeshellarg($tmp) . " > /dev/null", $rc);
unlink($tmp);
if ($rc !== 0) { fwrite(STDERR, "load failed\n"); exit(1); }
printf("loaded %d products, %d scores in %.1fs\n", $n, count($scores), microtime(true) - $t);
printf("products in database: %d\n\n", query("SELECT COUNT(*) n FROM products")[0]["n"]);

function timed($label, $fn, $runs = 5) {
    $fn();  // warm
    $t = microtime(true);
    for ($i = 0; $i < $runs; $i++) { $r = $fn(); }
    printf("  %-58s %7.1f ms\n", $label, (microtime(true) - $t) / $runs * 1000);
    return $r;
}
function explain($sql, ...$params) {
    foreach (query("EXPLAIN " . $sql, ...$params) as $r) {
        printf("    %-6s %-8s type=%-8s key=%-28s rows=%s %s\n", $r["table"], $r["select_type"], $r["type"],
            $r["key"] ?? "-", $r["rows"], $r["Extra"] ?? "");
    }
}

$weights = default_weights();
$big = (int) query("SELECT id FROM brands WHERE name = 'Bench Brand 01'")[0]["id"];

echo "brand page product list (brand with 2,000 products)\n";
explain("SELECT id, code, name, labels FROM products WHERE brand_id = ? ORDER BY name", $big);
timed("get_brand_products()", function () use ($big) { return get_brand_products($big); });
timed("  + score, sort, page (whole brand-page products step)", function () use ($big, $weights) {
    $p = attach_product_personal_scores(get_brand_products($big), ["editorial" => 7], $weights);
    sort_by_personal_score($p); return paginate($p, 1);
});

echo "\nproduct search\n";
foreach (["oat", "chocolate biscuit", "creme brulee", "xyzzy", "ba"] as $term) {
    $key = search_key($term);
    $r = timed("search_products(\"$term\") + rank", function () use ($term, $weights) {
        [$rows, $total] = search_products($term);
        return [count(rank_product_results($rows, $weights)), $total];
    });
    printf("    -> %d matched, %d ranked\n", $r[1], $r[0]);
}
echo "\n  EXPLAIN fulltext (\"oat\"):\n";
explain("SELECT p.id FROM products p JOIN brands b ON b.id = p.brand_id WHERE MATCH(p.name_key) AGAINST (? IN BOOLEAN MODE) LIMIT 1000", "+oat*");
echo "  EXPLAIN LIKE fallback (\"ba\"):\n";
explain("SELECT p.id FROM products p JOIN brands b ON b.id = p.brand_id WHERE p.name_key LIKE ? LIMIT 1000", "%ba%");
echo "  EXPLAIN product scores for 1,000 ids:\n";
$ids = array_column(query("SELECT id FROM products LIMIT 1000"), "id");
explain("SELECT product_id, dimension, score, input FROM product_scores WHERE product_id IN (" . implode(",", array_fill(0, count($ids), "?")) . ")", ...$ids);

if (in_array("--keep", $argv, true)) { exit; }
passthru("mysql " . escapeshellarg(getenv("DB_NAME")) . " -e \"DELETE FROM brands WHERE name LIKE 'Bench Brand %'\"");
echo "\nremoved bench data\n";
