<?php
/**
 * Smoke test.
 *
 *   php tests/smoke.php
 *
 * Covers the helpers, the search SQL builder's whitelisting, admin validation,
 * and every template rendered with hostile input. Exits non-zero on failure,
 * so it can be wired straight into CI.
 *
 * Most of it runs without a database; the validate_brand section needs the
 * local development database from dev/setup.sh.
 */

// Point at the local development database created by dev/setup.sh.
// Override by exporting DB_* before running.
putenv("DB_NAME=" . (getenv("DB_NAME") ?: "ethicalbuy"));
putenv("DB_USER=" . (getenv("DB_USER") ?: "ethicalbuy"));
putenv("DB_PASSWORD=" . (getenv("DB_PASSWORD") ?: "ethicalbuy_dev"));
putenv("APP_DEBUG=0");

require_once(__DIR__ . "/../includes/config.php");

$fails = 0;
function check($label, $cond) {
    global $fails;
    if (!$cond) { $fails++; echo "FAIL: $label\n"; } else { echo "ok:   $label\n"; }
}
function section($name) { echo "\n-- $name --\n"; }

section("rating_class");
check("rating 1 -> danger",      rating_class(1) === "danger");
check("rating 4 -> warning",     rating_class(4) === "warning");
check("rating 6 -> secondary",   rating_class(6) === "secondary");
check("rating 8 -> primary",     rating_class(8) === "primary");
check("rating 10 -> success",    rating_class(10) === "success");
check("NULL rating -> no class", rating_class(null) === "");
check("string '7' -> primary",   rating_class("7") === "primary");

section("rating_score / rating_label");
check("score 8 -> '8/10'",        rating_score(8) === "8/10");
check("score '8' -> '8/10'",      rating_score("8") === "8/10");
check("score 7.5 -> '7.5/10'",    rating_score(7.5) === "7.5/10");
check("score null -> Not rated",  rating_score(null) === "Not rated");
check("label 9 -> Excellent",     rating_label(9) === "Excellent");
check("label 4 -> Poor",          rating_label(4) === "Poor");
check("label null -> Not rated",  rating_label(null) === "Not rated");
// every band except "not rated" must map back to itself
foreach (rating_bands() as $band) {
    if ($band["class"] === "") continue;
    check("band {$band['label']} is reachable", in_array($band["class"], array_map("rating_class", [1,4,6,8,10]), true));
}

section("like_escape");
check("like_escape %", like_escape("50%") === "50\\%");
check("like_escape _", like_escape("a_b") === "a\\_b");

section("input_string");
check("array input rejected", input_string(["f" => ["x"]], "f", "brand") === "brand");
check("string input trimmed", input_string(["f" => "  hi "], "f") === "hi");
check("missing key -> default", input_string([], "f", "brand") === "brand");

section("build_brand_search whitelists");
[$sql, $params] = build_brand_search(["term" => "milk", "field" => "brand", "sort" => "rating_desc"]);
check("term is bound, not inlined", strpos($sql, "milk") === false && $params[0] === "%milk%");
check("valid field used", strpos($sql, "UPPER(brand) LIKE") !== false);
check("valid sort used", strpos($sql, "rating IS NULL, rating DESC") !== false);

[$sql2, $p2] = build_brand_search(["term" => "x", "field" => "brand) OR 1=1 -- ", "sort" => "; DROP TABLE brand_v"]);
check("evil field rejected", strpos($sql2, "OR 1=1") === false);
check("evil field falls back to brand", strpos($sql2, "UPPER(brand) LIKE") !== false);
check("evil sort rejected", strpos($sql2, "DROP TABLE") === false);
check("evil sort falls back", substr($sql2, -strlen("ORDER BY brand ASC")) === "ORDER BY brand ASC");

[$sql3, $p3] = build_brand_search(["min_rating" => "7 OR 1=1", "availability" => "Tesco'"]);
check("non-numeric min_rating ignored", strpos($sql3, "rating >=") === false);
check("availability is bound", in_array("Tesco'", $p3, true) && strpos($sql3, "Tesco") === false);

[$sql4, $p4] = build_brand_search(["min_rating" => "7", "max_rating" => "9"]);
check("numeric min_rating bound as float", in_array(7.0, $p4, true));
check("numeric max_rating bound as float", in_array(9.0, $p4, true));

[$sql5, $p5] = build_brand_search([]);
check("no filters -> no WHERE", strpos($sql5, "WHERE") === false);
check("no filters -> no params", $p5 === []);

section("CSRF");
$t = csrf_token();
check("token is 64 hex chars", preg_match('/^[0-9a-f]{64}$/', $t) === 1);
check("token is stable in a session", csrf_token() === $t);
check("valid token accepted", csrf_valid($t));
check("wrong token rejected", !csrf_valid(str_repeat("a", 64)));
check("empty token rejected", !csrf_valid(""));
check("array token rejected", !csrf_valid([$t]));
check("csrf_field escapes + embeds", strpos(csrf_field(), $t) !== false);

// ---------- template rendering ----------
$evil = '<script>alert("xss")</script>';
$evilRow = [
    "brand" => $evil, "category" => "Baby's \"Food\"", "type" => $evil,
    "owner" => $evil, "notes" => "</script><script>alert(2)</script>",
    "availability" => $evil, "rating" => null,
];
$goodRow = [
    "brand" => "Clean Co", "category" => "Dairy", "type" => "Milk",
    "owner" => "Someone", "notes" => "fine", "availability" => "Shops", "rating" => 9,
];

function renderTo($template, $values) {
    ob_start();
    render($template, $values);
    return ob_get_clean();
}
function structureChecks($label, $out) {
    check("$label: no raw script payload", strpos($out, '<script>alert') === false);
    check("$label: single doctype", substr_count(strtolower($out), '<!doctype') === 1);
    check("$label: single <body", substr_count(strtolower($out), '<body') === 1);
    check("$label: single </html>", substr_count(strtolower($out), '</html>') === 1);
    check("$label: balanced divs", substr_count($out, '<div') === substr_count($out, '</div>'));
    check("$label: balanced uls", substr_count($out, '<ul') === substr_count($out, '</ul>'));
}

section("search.php template");
$out = renderTo("search.php", [
    "title" => "Search Results",
    "filters" => ["term" => $evil, "field" => "brand", "min_rating" => "7",
                  "availability" => "Tesco", "sort" => "rating_desc"],
    "submitted" => true,
    "results" => [$evilRow, $goodRow],
    "availability_options" => ["Tesco", "Co-op & \"friends\""],
]);
structureChecks("search", $out);
check("search: payload escaped", strpos($out, '&lt;script&gt;') !== false);
check("search: rating 9 badge shown", strpos($out, '9/10') !== false);
check("search: not-rated shown", strpos($out, 'Not rated') !== false);
check("search: legend rendered", strpos($out, 'What do the colours mean?') !== false);
check("search: sticky sort selected", preg_match('/value="rating_desc" selected/', $out) === 1);
check("search: sticky min_rating selected", preg_match('/value="7" selected/', $out) === 1);
check("search: brand links urlencoded", strpos($out, 'brand.php?brand=%3Cscript%3E') !== false);
check("search: table-success for rating 9", strpos($out, 'table-success') !== false);

$empty = renderTo("search.php", [
    "title" => "Product Search",
    "filters" => ["term" => "", "field" => "brand", "min_rating" => "",
                  "availability" => "", "sort" => "brand"],
    "submitted" => false, "results" => [], "availability_options" => [],
]);
structureChecks("search(unsubmitted)", $empty);
check("search: no results block before submit", strpos($empty, 'No brands matched') === false
    && strpos($empty, '<table') === false);

section("brand.php template");
$out = renderTo("brand.php", [
    "title" => $evil, "brand" => $evilRow,
    "alternatives" => [$goodRow, array_merge($evilRow, ["brand" => "Other'Co", "rating" => 3])],
]);
structureChecks("brand", $out);
check("brand: title escaped in <title>", strpos($out, '<title>&lt;script&gt;') !== false);
check("brand: notes payload escaped", strpos($out, '&lt;/script&gt;') !== false);
check("brand: alternative link urlencoded", strpos($out, 'brand.php?brand=Other%27Co') !== false);
check("brand: breadcrumb category link urlencoded", strpos($out, 'search_string=Baby%27s+%22Food%22') !== false);
check("brand: alternative rating badge", strpos($out, '3/10') !== false);

section("contact.php template");
$out = renderTo("contact.php", [
    "title" => "Contact",
    "values" => ["name" => $evil, "email" => 'a"b@example.com', "message" => $evil],
    "errors" => ["name" => "Bad name", 0 => "Session expired"],
    "sent" => false,
]);
structureChecks("contact", $out);
check("contact: csrf field present", strpos($out, 'name="csrf_token"') !== false);
check("contact: field error shown", strpos($out, 'Bad name') !== false);
check("contact: general error shown", strpos($out, 'Session expired') !== false);
check("contact: invalid class applied", strpos($out, 'is-invalid') !== false);
check("contact: textarea value escaped", strpos($out, '&lt;script&gt;alert(&quot;xss&quot;)') !== false);
check("contact: email attr escaped", strpos($out, 'a&quot;b@example.com') !== false);

$sentOut = renderTo("contact.php", [
    "title" => "Contact", "values" => ["name" => "", "email" => "", "message" => ""],
    "errors" => [], "sent" => true,
]);
check("contact: success message on sent", strpos($sentOut, 'has been sent') !== false);

section("about.php template");
$out = renderTo("about.php", ["title" => "About"]);
structureChecks("about", $out);
check("about: legend bands rendered", substr_count($out, 'list-group-item') >= count(rating_bands()));

section("category_form.php template");
$out = renderTo("category_form.php", [
    "title" => "Categories",
    "categories" => [
        "Baby's \"Food\"" => [[
            "brand" => $evil, "type" => "x", "notes" => "</script><script>alert(2)</script>",
            "owner" => "y", "availability" => "z",
            "ratingClass" => "danger", "ratingScore" => "2/10", "ratingLabel" => "Avoid",
        ]],
        "Dairy" => [[
            "brand" => "Clean Co", "type" => "Milk", "notes" => null,
            "owner" => "Someone", "availability" => "Shops",
            "ratingClass" => "", "ratingScore" => "Not rated", "ratingLabel" => "",
        ]],
    ],
]);
structureChecks("categories", $out);
preg_match('~<script type="application/json" id="brandsData">(.*?)</script>~s', $out, $bd);
check("categories: data block found", !empty($bd[1]));
check("categories: no </script> break-out in data", strpos($bd[1], '</script>') === false);
$json = json_decode($bd[1], true);
check("categories: data parses", is_array($json));
preg_match_all('/<option value="([^"]*)"/', $out, $m);
foreach ($m[1] as $optValue) {
    $decoded = html_entity_decode($optValue, ENT_QUOTES, "UTF-8");
    check("option '$decoded' matches a JSON key", isset($json[$decoded]));
}
check("categories: rating passed to JS", strpos($bd[1], 'ratingScore') !== false);

section("is_secure_request");
$_SERVER["REMOTE_ADDR"] = "203.0.113.9";
unset($_SERVER["HTTPS"], $_SERVER["HTTP_X_FORWARDED_PROTO"]);
putenv("TRUST_PROXY=0");
check("plain HTTP from remote is insecure", !is_secure_request());
$_SERVER["HTTPS"] = "on";
check("HTTPS=on is secure", is_secure_request());
$_SERVER["HTTPS"] = "off";
check("HTTPS=off is insecure", !is_secure_request());
unset($_SERVER["HTTPS"]);
$_SERVER["HTTP_X_FORWARDED_PROTO"] = "https";
check("forged X-Forwarded-Proto ignored when TRUST_PROXY=0", !is_secure_request());
putenv("TRUST_PROXY=1");
check("X-Forwarded-Proto honoured when TRUST_PROXY=1", is_secure_request());
putenv("TRUST_PROXY=0");
unset($_SERVER["HTTP_X_FORWARDED_PROTO"]);
$_SERVER["REMOTE_ADDR"] = "127.0.0.1";
check("localhost allowed for development", is_secure_request());

section("basic_auth_credentials");
unset($_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"], $_SERVER["HTTP_AUTHORIZATION"]);
check("no header -> [null, null]", basic_auth_credentials() === [null, null]);
$_SERVER["HTTP_AUTHORIZATION"] = "Basic " . base64_encode("dale:pa:ss word");
check("header parsed, password may contain ':'",
    basic_auth_credentials() === ["dale", "pa:ss word"]);
$_SERVER["HTTP_AUTHORIZATION"] = "Bearer sometoken";
check("non-Basic scheme rejected", basic_auth_credentials() === [null, null]);
$_SERVER["HTTP_AUTHORIZATION"] = "Basic !!!notbase64!!!";
check("malformed base64 rejected", basic_auth_credentials() === [null, null]);
$_SERVER["PHP_AUTH_USER"] = "direct";
$_SERVER["PHP_AUTH_PW"] = "pw";
check("PHP_AUTH_USER preferred", basic_auth_credentials() === ["direct", "pw"]);
unset($_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"], $_SERVER["HTTP_AUTHORIZATION"]);

section("validate_brand (against the real dev database)");
check("empty name rejected", isset(validate_brand(["name" => ""])["name"]));
check("long name rejected", isset(validate_brand(["name" => str_repeat("a", 151)])["name"]));
check("valid new brand passes", validate_brand(["name" => "A Brand Not In The Seed", "rating" => "7"]) === []);
check("existing seed name rejected", isset(validate_brand(["name" => "Valley Fresh"])["name"]));
$vf = query("SELECT id FROM brands WHERE name = ?", "Valley Fresh");
check("a brand may keep its own name",
    !isset(validate_brand(["name" => "Valley Fresh"], (int) $vf[0]["id"])["name"]));
check("blank rating allowed", !isset(validate_brand(["name" => "Zzz New", "rating" => ""])["rating"]));
check("rating 0 rejected", isset(validate_brand(["name" => "Zzz New", "rating" => "0"])["rating"]));
check("rating 11 rejected", isset(validate_brand(["name" => "Zzz New", "rating" => "11"])["rating"]));
check("rating 'abc' rejected", isset(validate_brand(["name" => "Zzz New", "rating" => "abc"])["rating"]));
check("rating 7.5 allowed", !isset(validate_brand(["name" => "Zzz New", "rating" => "7.5"])["rating"]));
check("long notes rejected", isset(validate_brand(["name" => "Zzz New", "notes" => str_repeat("x", 5001)])["notes"]));

section("brand_params");
$p = brand_params(["name" => "  Trimmed  ", "rating" => "", "category_id" => "", "notes" => " n "]);
check("name trimmed", $p["name"] === "Trimmed");
check("blank rating -> NULL", $p["rating"] === null);
check("blank category -> NULL", $p["category_id"] === null);
check("notes trimmed", $p["notes"] === "n");

section("admin templates");
$adminRows = [[
    "id" => 1, "name" => $evil, "type" => $evil, "availability" => $evil,
    "rating" => 2, "updated_at" => "2026-08-16 10:00:00",
    "category" => "Baby's \"Food\"", "owner" => $evil,
]];
$out = renderTo("admin/list.php", [
    "title" => "Admin", "brands" => $adminRows, "search" => $evil, "flash" => "Brand created.",
]);
structureChecks("admin list", $out);
check("admin list: flash shown", strpos($out, 'Brand created.') !== false);
check("admin list: edit link", strpos($out, '/admin/edit.php?id=1') !== false);
check("admin list: delete link", strpos($out, '/admin/delete.php?id=1') !== false);
check("admin list: public link urlencoded", strpos($out, 'brand.php?brand=%3Cscript%3E') !== false);

$out = renderTo("admin/form.php", [
    "title" => "Edit Brand", "id" => 7,
    "values" => ["name" => $evil, "category_id" => "3", "owner_id" => "",
                 "type" => $evil, "notes" => $evil, "availability" => $evil, "rating" => "8"],
    "errors" => ["name" => "Taken", 0 => "Session expired"],
    "categories" => [["id" => 3, "name" => "Baby's \"Food\""]],
    "owners" => [["id" => 1, "name" => "O'Donnell Family Farms"]],
]);
structureChecks("admin form", $out);
check("admin form: csrf present", strpos($out, 'name="csrf_token"') !== false);
check("admin form: posts to id", strpos($out, 'action="/admin/edit.php?id=7"') !== false);
check("admin form: category preselected", preg_match('/value="3"\s*selected/', $out) === 1);
check("admin form: field error shown", strpos($out, 'Taken') !== false);
check("admin form: general error shown", strpos($out, 'Session expired') !== false);

$out = renderTo("admin/delete.php", [
    "title" => "Delete Brand",
    "brand" => ["id" => 7, "name" => $evil, "type" => $evil, "notes" => $evil, "rating" => null],
    "errors" => [],
]);
structureChecks("admin delete", $out);
check("admin delete: is a POST form", strpos($out, 'method="post"') !== false);
check("admin delete: csrf present", strpos($out, 'name="csrf_token"') !== false);
check("admin delete: id carried in hidden field", strpos($out, 'name="id" value="7"') !== false);

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURE(S)\n";
exit($fails === 0 ? 0 : 1);
