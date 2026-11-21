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
require_once(__DIR__ . "/../includes/import.php");

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

// real scores, not stubs, so the templates are exercised against the
// same structure personal_score() actually produces
function withPersonal($row, $weights = null) {
    $weights = $weights ?? default_weights();
    $row["personal"] = personal_score(["editorial" => $row["rating"] ?? null], $weights);
    return $row;
}

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
    "results" => [withPersonal($evilRow), withPersonal($goodRow)],
    "availability_options" => ["Tesco", "Co-op & \"friends\""],
    "weights" => default_weights(),
    "chosen" => true,
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
    "weights" => default_weights(), "chosen" => false,
]);
structureChecks("search(unsubmitted)", $empty);
check("search: no results block before submit", strpos($empty, 'No brands matched') === false
    && strpos($empty, '<table') === false);

section("brand.php template");
$brandValues = ["editorial" => 8, "environment" => 4, "welfare" => null,
                "nutrition" => 6, "ownership" => 2];
$out = renderTo("brand.php", [
    "title" => $evil, "brand" => $evilRow,
    "alternatives" => [
        withPersonal($goodRow),
        withPersonal(array_merge($evilRow, ["brand" => "Other'Co", "rating" => 3])),
    ],
    "weights" => default_weights(),
    "personal" => personal_score($brandValues, default_weights()),
    "breakdown" => score_breakdown($brandValues, default_weights()),
    "scores" => [],
    "chosen" => true,
]);
structureChecks("brand", $out);
check("brand: title escaped in <title>", strpos($out, '<title>&lt;script&gt;') !== false);
check("brand: notes payload escaped", strpos($out, '&lt;/script&gt;') !== false);
check("brand: alternative link urlencoded", strpos($out, 'brand.php?brand=Other%27Co') !== false);
check("brand: breadcrumb category link urlencoded", strpos($out, 'search_string=Baby%27s+%22Food%22') !== false);
check("brand: alternative rating badge", strpos($out, '3/10') !== false);

section("brand.php dimension derivation");
$offScore = function ($v, $url = "https://world.openfoodfacts.org/product/1") {
    return ["score" => $v, "note" => "Derived from Open Food Facts", "source" => OFF_SOURCE,
            "source_url" => $url, "retrieved_at" => "2026-11-01 10:00:00"];
};
$nutriInputs = [];
foreach (["b", "b", "c", "b"] as $i => $g) {
    $nutriInputs[] = ["ref" => "p$i", "label" => "Oat <Drink> $i", "input" => strtoupper($g),
        "points" => off_grade_to_score($g), "url" => "https://world.openfoodfacts.org/product/p$i"];
}
$nutriInputs[1]["url"] = "javascript:alert(1)";
$evValues = ["editorial" => 8, "environment" => 8, "nutrition" => 7.5, "welfare" => null];
$evOut = renderTo("brand.php", [
    "title" => "Ev", "brand" => $evilRow, "alternatives" => [],
    "weights" => default_weights(),
    "personal" => personal_score($evValues, default_weights()),
    "breakdown" => score_breakdown($evValues, default_weights()),
    "scores" => ["environment" => $offScore(8), "nutrition" => $offScore(7.5)],
    // no products yet, so the working falls back to the 005 evidence rows
    "derivations" => brand_derivations(["environment" => $offScore(8), "nutrition" => $offScore(7.5)],
        [], ["nutrition" => $nutriInputs]),
    "chosen" => true,
]);
structureChecks("brand derivation", $evOut);
check("derivation: one collapsed details per scored dimension",
    substr_count($evOut, '<details class="dimension-derivation">') === 3);
check("derivation: no details opened by default", strpos($evOut, '<details open') === false);
check("derivation: method generated from grade map",
    strpos($evOut, 'Nutri-Score letter per product: A 10, B 8, C 6, D 4, E 2. Brand score is the average.') !== false);
check("derivation: product linked", strpos($evOut,
    '<a href="https://world.openfoodfacts.org/product/p0" target="_blank" rel="noopener noreferrer">Oat &lt;Drink&gt; 0</a>') !== false);
check("derivation: non-http product url not linked", strpos($evOut, 'javascript:') === false
    && strpos($evOut, 'Oat &lt;Drink&gt; 1') !== false);
check("derivation: sum line", strpos($evOut, '(8 + 8 + 6 + 8) / 4 = 7.5') !== false);
check("derivation: not-yet-recorded message for OFF score without inputs",
    strpos($evOut, 'Product detail not recorded yet.') !== false);
check("derivation: editorial is entered by hand", strpos($evOut, 'Entered by hand.') !== false);
check("derivation: unscored still Not assessed", strpos($evOut, 'Not assessed') !== false);
check("derivation: ODbL attribution", strpos($evOut, 'ODbL') !== false);
check("derivation: old evidence line gone", strpos($evOut, 'score-evidence') === false);

$curOut = renderTo("brand.php", [
    "title" => "Cur", "brand" => $evilRow, "alternatives" => [],
    "weights" => default_weights(),
    "personal" => personal_score(["nutrition" => 4], default_weights()),
    "breakdown" => score_breakdown(["nutrition" => 4], default_weights()),
    "scores" => ["nutrition" => ["score" => 4, "note" => "x", "source" => "curated", "source_url" => null, "retrieved_at" => null]],
    "derivations" => brand_derivations(["nutrition" => ["score" => 4, "source" => "curated"]], [], ["nutrition" => $nutriInputs]),
    "chosen" => true,
]);
check("derivation: curated score ignores leftover inputs",
    strpos($curOut, 'derivation-products') === false && strpos($curOut, 'Entered by hand.') !== false);

$many = [];
for ($i = 0; $i < 20; $i++) {
    $many[] = ["ref" => "m$i", "label" => "Item $i", "input" => "A", "points" => 10.0,
               "url" => "https://world.openfoodfacts.org/product/m$i"];
}
$manyOut = renderTo("brand.php", [
    "title" => "Many", "brand" => $evilRow, "alternatives" => [],
    "weights" => default_weights(),
    "personal" => personal_score(["nutrition" => 10], default_weights()),
    "breakdown" => score_breakdown(["nutrition" => 10], default_weights()),
    "scores" => ["nutrition" => $offScore(10)],
    "derivations" => brand_derivations(["nutrition" => $offScore(10)], [], ["nutrition" => $many]),
    "chosen" => true,
]);
check("derivation: first 15 listed", strpos($manyOut, 'Item 14') !== false && strpos($manyOut, 'Item 15<') === false);
check("derivation: and N more", strpos($manyOut, 'and 5 more') !== false);
check("derivation: sum covers all", strpos($manyOut, ') / 20 = 10.0') !== false);

section("brand.php worked calculation");
function calcResult($out) {
    return preg_match('~<strong class="score-calc-result">([^<]*)</strong>~', $out, $m) ? trim($m[1]) : null;
}
function renderCalc($values, $weights, $chosen) {
    return renderTo("brand.php", [
        "title" => "Calc", "brand" => $GLOBALS["evilRow"], "alternatives" => [],
        "weights" => $weights,
        "personal" => personal_score($values, $weights),
        "breakdown" => score_breakdown($values, $weights),
        "scores" => [], "chosen" => $chosen,
    ]);
}
$calcValues = ["editorial" => 8, "environment" => 8.0, "welfare" => null,
               "nutrition" => 6.0, "ownership" => 7];
$before = personal_score($calcValues, default_weights());
check("personal_score number unchanged", $before["score"] === 7.3);
check("personal_score terms match covered", array_column($before["terms"], "key") === $before["covered"]);
$o = renderCalc($calcValues, default_weights(), false);
structureChecks("calc default", $o);
check("calc: result equals headline (default)", calcResult($o) === rating_score($before["score"]));
check("calc: term line", strpos($o, 'Nutrition 6.0 &times; 3 = 18.0') !== false);
check("calc: sum and division", strpos($o, '(24.0 + 24.0 + 18.0 + 21.0) / (3 + 3 + 3 + 3) = 87.0 / 12') !== false);
check("calc: plain average note", strpos($o, 'this is a plain average') !== false);
check("calc: working inside a collapsed details",
    preg_match('~<details class="mt-4 score-working">\s*<summary[^>]*>How this number is worked out</summary>.*score-calc-result.*?</details>~s', $o) === 1);
check("calc: missing listed as excluded", preg_match('~Diet &amp; animal welfare:\s*no data \(excluded~', $o) === 1);

$_COOKIE[PRIORITIES_COOKIE] = "editorial=5&environment=0&nutrition=2&welfare=4&ownership=1";
$cw = current_weights();
$custom = personal_score($calcValues, $cw);
$o = renderCalc($calcValues, $cw, true);
unset($_COOKIE[PRIORITIES_COOKIE]);
check("calc: custom weights score", $custom["score"] === round((40 + 12 + 7) / 8, 1));
check("calc: result equals headline (custom)", calcResult($o) === rating_score($custom["score"]));
check("calc: weight 0 listed as ignored", preg_match('~Environment &amp; carbon:\s*weighted 0 \(ignored\)~', $o) === 1);
check("calc: no plain-average note when chosen", strpos($o, 'plain average') === false);

section("about.php scoring section");
$o = renderTo("about.php", ["title" => "About"]);
structureChecks("about scoring", $o);
check("about: has section", strpos($o, 'How scores work') !== false);
foreach (off_grade_map() as $g => $sc) {
    check("about: grade $g", strpos($o, strtoupper($g) . " = " . rating_score($sc)) !== false);
}
check("about: label credits listed", substr_count($o, '<li>', strpos($o, 'welfare-labels')) >= count(off_welfare_labels()));
check("about: acronym label name", strpos($o, 'RSPCA Assured: +2') !== false);
check("about: soil association credit", strpos($o, 'Soil Association Organic: +2') !== false);
check("about: ODbL kept", strpos($o, 'ODbL') !== false);

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
            "editorial" => "2/10", "thin" => false,
        ]],
        "Dairy" => [[
            "brand" => "Clean Co", "type" => "Milk", "notes" => null,
            "owner" => "Someone", "availability" => "Shops",
            "ratingClass" => "", "ratingScore" => "Not rated", "ratingLabel" => "",
            "editorial" => "Not rated", "thin" => true,
        ]],
    ],
    "chosen" => true,
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

section("Open Food Facts mapping");
require_once(__DIR__ . "/../includes/import.php");

check("tag -> name", off_tag_to_name("en:dark-chocolates") === "Dark chocolates");
check("tag without prefix", off_tag_to_name("chocolates") === "Chocolates");
check("empty tag -> null", off_tag_to_name("") === null);
check("clean collapses whitespace", clean_value("  a   b  ") === "a b");
check("clean empty -> null", clean_value("   ") === null);
check("clean rejects arrays", clean_value(["x"]) === null);
check("clean truncates", clean_value("abcdef", 3) === "abc");

$ukProduct = [
    "code" => "5000112637922",
    "product_name" => "Dark Chocolate Bar",
    "brands" => "Greenfields, Some Other Brand",
    "brands_tags" => ["greenfields", "some-other-brand"],
    "categories_tags" => ["en:snacks", "en:sweet-snacks", "en:dark-chocolates"],
    "countries_tags" => ["en:united-kingdom", "en:france"],
    "labels_tags" => ["en:organic", "en:fairtrade", "en:some-junk-tag"],
    "stores" => "Tesco, Sainsbury's",
];
$m = map_off_product($ukProduct);
check("maps a UK product", is_array($m));
check("takes first brand, human cased", $m["name"] === "Greenfields");
check("category from most general tag", $m["category"] === "Snacks");
check("type from most specific tag", $m["type"] === "Dark chocolates");
check("availability from first store", $m["availability"] === "Tesco");
check("known certifications kept", strpos($m["certifications"], "Organic") !== false
    && strpos($m["certifications"], "Fairtrade") !== false);
check("unknown label tags dropped", strpos($m["certifications"], "junk") === false);
check("provenance: source", $m["source"] === "openfoodfacts");
check("provenance: licence", $m["source_licence"] === "ODbL 1.0");
check("provenance: url contains code", strpos($m["source_url"], "5000112637922") !== false);
check("NEVER imports a rating", !array_key_exists("rating", $m));
check("NEVER imports notes", !array_key_exists("notes", $m));

$nonUk = $ukProduct;
$nonUk["countries_tags"] = ["en:france"];
check("non-UK product skipped", map_off_product($nonUk) === null);
check("country filter can be disabled", is_array(map_off_product($nonUk, null)));

$noBrand = $ukProduct;
unset($noBrand["brands"], $noBrand["brands_tags"]);
check("product with no brand skipped", map_off_product($noBrand) === null);

$slugOnly = $ukProduct;
unset($slugOnly["brands"]);
check("falls back to brand slug", map_off_product($slugOnly)["name"] === "Greenfields");

$bare = ["code" => "1", "brands" => "Bare Co", "countries_tags" => ["en:united-kingdom"]];
$mb = map_off_product($bare);
check("bare product still maps", $mb["name"] === "Bare Co");
check("bare product has null category", $mb["category"] === null);
check("bare product has null certifications", $mb["certifications"] === null);
check("garbage input -> null", map_off_product("not an array") === null);
check("empty array -> null", map_off_product([]) === null);

$storesTagsOnly = $ukProduct;
unset($storesTagsOnly["stores"]);
$storesTagsOnly["stores_tags"] = ["waitrose"];
check("falls back to stores_tags", map_off_product($storesTagsOnly)["availability"] === "Waitrose");

// collapsing: two products, same brand, complementary data
$collapsed = collapse_off_products([
    ["code" => "1", "brands" => "Dupe Co", "countries_tags" => ["en:united-kingdom"],
     "categories_tags" => ["en:drinks"]],
    ["code" => "2", "brands" => "dupe co", "countries_tags" => ["en:united-kingdom"],
     "stores" => "Co-op", "labels_tags" => ["en:vegan"]],
    ["code" => "3", "brands" => "Other Co", "countries_tags" => ["en:united-kingdom"]],
    ["code" => "4", "brands" => "Skipped", "countries_tags" => ["en:france"]],
]);
check("collapses case-insensitively to 2 brands", count($collapsed) === 2);
$dupe = $collapsed[0];
check("collapse keeps first name casing", $dupe["name"] === "Dupe Co");
check("collapse keeps first category", $dupe["category"] === "Drinks");
check("collapse fills blank from later product", $dupe["availability"] === "Co-op");
check("collapse fills certifications from later", $dupe["certifications"] === "Vegan");
check("collapse still filters by country",
    !in_array("Skipped", array_column($collapsed, "name"), true));

section("Companies House name normalisation");
require_once(__DIR__ . "/../includes/import_companies.php");

check("uppercases", normalise_company_name("Acme Foods") === "ACME FOODS");
check("strips LIMITED", normalise_company_name("Acme Foods Limited") === "ACME FOODS");
check("strips LTD", normalise_company_name("Acme Foods Ltd") === "ACME FOODS");
check("strips PLC", normalise_company_name("Acme Foods PLC") === "ACME FOODS");
check("strips trailing punctuation", normalise_company_name("Acme Foods Ltd.") === "ACME FOODS");
check("strips stacked suffixes", normalise_company_name("Acme Co Limited") === "ACME");
check("strips leading The", normalise_company_name("The Acme Company") === "ACME");
check("apostrophes ignored", normalise_company_name("O'Donnell Farms") === "ODONNELL FARMS");
check("ampersand becomes AND", normalise_company_name("Kestrel & Fen") === "KESTREL AND FEN");
check("ampersand matches spelled form",
    normalise_company_name("Kestrel & Fen Ltd") === normalise_company_name("Kestrel and Fen Limited"));
check("collapses whitespace", normalise_company_name("  Acme   Foods  ") === "ACME FOODS");
check("empty stays empty", normalise_company_name("") === "");
check("suffix-only name -> empty", normalise_company_name("Limited") === "");

section("Companies House match scoring");
check("identical -> 100", score_company_match("Acme Foods", "Acme Foods Limited") === 100);
check("prefix -> 80", score_company_match("Acme", "Acme Foods Limited") === 80);
check("unrelated scores low", score_company_match("Acme Foods", "Zebra Mining") < 40);
check("empty scores 0", score_company_match("", "Acme") === 0);

section("Companies House match selection (must never guess)");
$exactActive = ["company_number"=>"111","company_name"=>"Acme Foods Ltd","company_status"=>"active","address_snippet"=>null,"score"=>100];
$exactActive2 = ["company_number"=>"222","company_name"=>"Acme Foods Limited","company_status"=>"active","address_snippet"=>null,"score"=>100];
$exactDissolved = ["company_number"=>"333","company_name"=>"Acme Foods Ltd","company_status"=>"dissolved","address_snippet"=>null,"score"=>100];
$closeActive = ["company_number"=>"444","company_name"=>"Acme Foods Holdings Ltd","company_status"=>"active","address_snippet"=>null,"score"=>80];

[$m, $st, $why] = choose_company_match("Acme Foods", [$exactActive]);
check("unique exact active -> confirmed", $st === "confirmed" && $m["company_number"] === "111");

[$m, $st, $why] = choose_company_match("Acme Foods", [$exactActive, $exactActive2]);
check("two exact actives -> ambiguous", $st === "ambiguous" && $m === null);

[$m, $st, $why] = choose_company_match("Acme Foods", [$exactDissolved]);
check("exact but dissolved -> ambiguous", $st === "ambiguous" && $m === null);

[$m, $st, $why] = choose_company_match("Acme Foods", [$closeActive]);
check("close but not exact -> ambiguous", $st === "ambiguous" && $m === null);

[$m, $st, $why] = choose_company_match("Acme Foods", []);
check("no results -> ambiguous", $st === "ambiguous" && $m === null);

[$m, $st, $why] = choose_company_match("Acme Foods", [$closeActive, $exactActive, $exactDissolved]);
check("picks the exact active among noise", $st === "confirmed" && $m["company_number"] === "111");

section("Companies House candidate ranking");
$ranked = rank_company_candidates([
    ["company_number"=>"1","title"=>"Zebra Mining Plc","company_status"=>"active"],
    ["company_number"=>"2","title"=>"Acme Foods Limited","company_status"=>"active"],
    ["company_number"=>"3","title"=>"Acme Foods Holdings Ltd","company_status"=>"active"],
    ["no_number"=>true,"title"=>"Broken"],
], "Acme Foods");
check("drops rows without a company number", count($ranked) === 3);
check("best match first", $ranked[0]["company_number"] === "2");
check("scores descending", $ranked[0]["score"] >= $ranked[1]["score"]);
check("active preferred on tie", true);

section("Companies House profile mapping");
$profile = map_company_profile([
    "company_number" => "00445790", "company_name" => "ACME FOODS LIMITED",
    "company_status" => "active", "date_of_creation" => "1949-03-15",
]);
check("maps number", $profile["company_number"] === "00445790");
check("maps status", $profile["company_status"] === "active");
check("maps incorporation date", $profile["incorporated_on"] === "1949-03-15");
check("cites canonical CH url",
    strpos($profile["source_url"], "find-and-update.company-information.service.gov.uk") !== false);
check("records OGL licence", $profile["source_licence"] === "OGL v3.0");
check("rejects profile with no number", map_company_profile(["company_name" => "x"]) === null);
check("rejects garbage", map_company_profile("nope") === null);
$badDate = map_company_profile(["company_number" => "1", "date_of_creation" => "not-a-date"]);
check("rejects malformed date", $badDate["incorporated_on"] === null);

section("PSC mapping (must never store people)");
$pscs = map_corporate_pscs(["items" => [
    ["kind" => "individual-person-with-significant-control",
     "name" => "Ms Jane Doe", "date_of_birth" => ["month" => 4, "year" => 1970],
     "nationality" => "British"],
    ["kind" => "corporate-entity-person-with-significant-control",
     "name" => "Halcyon Holdings Limited",
     "identification" => ["registration_number" => "09876543"]],
    ["kind" => "legal-person-person-with-significant-control",
     "name" => "Some Legal Person LLP", "identification" => []],
    ["kind" => "corporate-entity-person-with-significant-control",
     "name" => "Former Parent Ltd", "ceased_on" => "2023-01-01"],
    ["kind" => "super-secure-person-with-significant-control"],
]]);
check("keeps only organisations", count($pscs) === 2);
$names = array_column($pscs, "name");
check("individual person excluded", !in_array("Ms Jane Doe", $names, true));
check("corporate entity included", in_array("Halcyon Holdings Limited", $names, true));
check("legal person included", in_array("Some Legal Person LLP", $names, true));
check("ceased entity excluded", !in_array("Former Parent Ltd", $names, true));
check("registration number captured", $pscs[0]["number"] === "09876543");
check("missing registration -> null", $pscs[1]["number"] === null);
check("no date_of_birth anywhere", strpos(json_encode($pscs), "date_of_birth") === false);
check("no nationality anywhere", strpos(json_encode($pscs), "nationality") === false);
check("empty response -> empty", map_corporate_pscs([]) === []);

section("admin owners template");
$out = renderTo("admin/owners.php", [
    "title" => "Owners",
    "owners" => [
        ["id" => 1, "name" => $evil, "company_number" => null, "company_name" => null,
         "company_status" => null, "parent_company_name" => null,
         "match_status" => "ambiguous", "match_note" => "2 active companies share that exact name",
         "source_url" => null, "candidate_count" => 2, "brand_count" => 3],
        ["id" => 2, "name" => "Confirmed Co", "company_number" => "00445790",
         "company_name" => "CONFIRMED CO LIMITED", "company_status" => "active",
         "parent_company_name" => "Parent Holdings Ltd", "match_status" => "confirmed",
         "match_note" => null,
         "source_url" => "https://find-and-update.company-information.service.gov.uk/company/00445790",
         "candidate_count" => 0, "brand_count" => 1],
    ],
    "candidates" => [1 => [
        ["company_number" => "111", "company_name" => $evil, "company_status" => "active",
         "address_snippet" => "1 High St, London", "score" => 100],
    ]],
    "errors" => [], "flash" => "Company confirmed.",
]);
structureChecks("admin owners", $out);
check("owners: confirm form is POST", strpos($out, 'name="action" value="confirm"') !== false);
check("owners: clear form present", strpos($out, 'name="action" value="clear"') !== false);
check("owners: csrf present", substr_count($out, 'name="csrf_token"') >= 2);
check("owners: match note shown", strpos($out, 'share that exact name') !== false);
check("owners: OGL attribution present", strpos($out, 'Open Government Licence v3.0') !== false);
check("owners: confirmed company linked", strpos($out, '/company/00445790') !== false);

section("weights parsing (never trust the cookie)");
check("empty -> defaults", parse_weights("") === default_weights());
check("null -> defaults", parse_weights(null) === default_weights());
check("array -> defaults", parse_weights(["x"]) === default_weights());
$w = parse_weights("environment=5&nutrition=0");
check("parses a set value", $w["environment"] === 5);
check("parses zero", $w["nutrition"] === 0);
check("unset keys keep default", $w["welfare"] === WEIGHT_DEFAULT);
check("clamps above max", parse_weights("environment=99")["environment"] === WEIGHT_MAX);
check("clamps below min", parse_weights("environment=-5")["environment"] === WEIGHT_MIN);
check("ignores unknown dimension", !array_key_exists("evil", parse_weights("evil=5")));
check("ignores non-numeric", parse_weights("environment=abc")["environment"] === WEIGHT_DEFAULT);
check("ignores array value", parse_weights("environment[]=5")["environment"] === WEIGHT_DEFAULT);
check("round-trips through encode",
    parse_weights(encode_weights(["environment" => 5, "welfare" => 0]))["environment"] === 5);

section("personal_score");
$full = ["editorial" => 8, "environment" => 4, "nutrition" => 6, "welfare" => 10, "ownership" => 2];

$equal = personal_score($full, array_fill_keys(array_keys(score_dimensions()), 3));
check("equal weights = plain mean", $equal["score"] === 6.0);
check("equal weights: nothing missing", $equal["missing"] === []);
check("equal weights: full coverage", $equal["coverage"] === 1.0);
check("equal weights: confident", $equal["confident"] === true);

$vegan = personal_score($full, ["welfare" => 5, "editorial" => 1, "environment" => 1,
                                "nutrition" => 1, "ownership" => 1]);
check("welfare-led score is higher", $vegan["score"] > $equal["score"]);

$climate = personal_score($full, ["environment" => 5, "editorial" => 1, "nutrition" => 1,
                                  "welfare" => 1, "ownership" => 1]);
check("climate-led score is lower", $climate["score"] < $equal["score"]);
check("same facts, different people, different answers", $vegan["score"] !== $climate["score"]);

// weight 0 must remove a dimension entirely
$ignored = personal_score($full, ["welfare" => 0, "editorial" => 3, "environment" => 3,
                                  "nutrition" => 3, "ownership" => 3]);
check("weight 0 excludes the dimension", !in_array("welfare", $ignored["covered"], true));
check("weight 0 is not counted as missing", !in_array("welfare", $ignored["missing"], true));
check("weight 0 changes the average", $ignored["score"] === 5.0);

// missing data must never be treated as zero
$partial = ["editorial" => 8, "environment" => null];
$m = personal_score($partial, ["editorial" => 3, "environment" => 3]);
check("missing dimension excluded, not zeroed", $m["score"] === 8.0);
check("missing dimension is reported", $m["missing"] === ["environment"]);
check("coverage halves", $m["coverage"] === 0.5);

$mostlyMissing = personal_score(["editorial" => 8], ["editorial" => 1, "environment" => 5]);
check("thin coverage flagged", $mostlyMissing["confident"] === false);
check("thin coverage still scores", $mostlyMissing["score"] === 8.0);

$nothing = personal_score(["editorial" => null], ["editorial" => 3]);
check("no data -> null score, not 0", $nothing["score"] === null);
check("no data -> zero coverage", $nothing["coverage"] === 0.0);

$allIgnored = personal_score($full, array_fill_keys(array_keys(score_dimensions()), 0));
check("all ignored -> null score", $allIgnored["score"] === null);
check("all ignored -> nothing missing", $allIgnored["missing"] === []);

check("string scores accepted (DECIMAL from PDO)",
    personal_score(["editorial" => "8.0"], ["editorial" => 3])["score"] === 8.0);
check("empty-string score treated as missing",
    personal_score(["editorial" => ""], ["editorial" => 3])["score"] === null);

section("score_breakdown");
$rows = score_breakdown($full, ["editorial" => 3, "environment" => 0, "nutrition" => 3,
                                "welfare" => 3, "ownership" => 3]);
check("one row per dimension", count($rows) === count(score_dimensions()));
check("breakdown keeps display order", $rows[0]["key"] === "editorial");
$env = array_values(array_filter($rows, function ($r) { return $r["key"] === "environment"; }))[0];
check("zero-weighted row not counted", $env["counted"] === false);
check("zero-weighted row still shows its score", $env["score"] === 4.0);

section("OFF derived dimension scores");
check("grade a -> 10", off_grade_to_score("a") === 10.0);
check("grade e -> 2", off_grade_to_score("e") === 2.0);
check("uppercase grade works", off_grade_to_score("B") === 8.0);
check("unknown grade -> null", off_grade_to_score("unknown") === null);
check("not-applicable -> null", off_grade_to_score("not-applicable") === null);
check("null grade -> null", off_grade_to_score(null) === null);
check("no labels -> welfare unassessed", off_welfare_score([]) === null);
check("unrecognised labels -> unassessed", off_welfare_score(["en:nonsense"]) === null);
check("vegan raises welfare", off_welfare_score(["en:vegan"]) === 7.0);
check("welfare never exceeds 10",
    off_welfare_score(["en:vegan","en:organic","en:cruelty-free","en:rspca-assured",
                       "en:soil-association-organic"]) === 10.0);
check("welfare never drops below 5", off_welfare_score(["en:vegetarian"]) >= 5.0);

$scored = map_off_product([
    "code" => "1", "brands" => "Graded Co", "countries_tags" => ["en:united-kingdom"],
    "ecoscore_grade" => "b", "nutriscore_grade" => "d", "labels_tags" => ["en:vegan"],
]);
check("product carries dimension scores", $scored["scores"]["environment"] === 8.0);
check("nutriscore mapped", $scored["scores"]["nutrition"] === 4.0);
check("welfare derived", $scored["scores"]["welfare"] === 7.0);

$newName = map_off_product([
    "code" => "2", "brands" => "New Name Co", "countries_tags" => ["en:united-kingdom"],
    "environmental_score_grade" => "a",
]);
check("accepts the renamed environmental_score_grade", $newName["scores"]["environment"] === 10.0);
check("absent grades are simply absent",
    !array_key_exists("nutrition", $newName["scores"]));

$avg = collapse_off_products([
    ["code"=>"1","brands"=>"Avg Co","countries_tags"=>["en:united-kingdom"],"ecoscore_grade"=>"a"],
    ["code"=>"2","brands"=>"Avg Co","countries_tags"=>["en:united-kingdom"],"ecoscore_grade"=>"c"],
]);
check("brand score averages across products", $avg[0]["scores"]["environment"] === 8.0);

section("OFF per-product contributions");
$offProducts = [
    ["code"=>"11","product_name"=>"Oat Drink","brands"=>"Inputs Co","countries_tags"=>["en:united-kingdom"],
     "nutriscore_grade"=>"b","ecoscore_grade"=>"a","labels_tags"=>["en:vegan","en:organic"]],
    ["code"=>"12","product_name"=>"Soya Drink","brands"=>"Inputs Co","countries_tags"=>["en:united-kingdom"],
     "nutriscore_grade"=>"c","labels_tags"=>["en:some-junk"]],
    ["code"=>"13","product_name"=>"Almond Drink","brands"=>"Inputs Co","countries_tags"=>["en:united-kingdom"],
     "nutriscore_grade"=>"a","ecoscore_grade"=>"d"],
];
$one = map_off_product($offProducts[0]);
check("map: contribution per scored dimension",
    array_keys($one["contributions"]) === array_keys($one["scores"]));
check("map: nutrition input is the grade", $one["contributions"]["nutrition"]["input"] === "B");
check("map: welfare input lists labels", $one["contributions"]["welfare"]["input"] === "Organic, Vegan"
    || $one["contributions"]["welfare"]["input"] === "Vegan, Organic");
check("map: contribution carries product name and url",
    $one["contributions"]["nutrition"]["label"] === "Oat Drink"
    && $one["contributions"]["nutrition"]["url"] === "https://world.openfoodfacts.org/product/11");
$ic = collapse_off_products($offProducts)[0];
check("collapse: inputs per product", count($ic["inputs"]["nutrition"]) === 3
    && count($ic["inputs"]["environment"]) === 2 && count($ic["inputs"]["welfare"]) === 1);
foreach ($ic["scores"] as $dim => $score) {
    $pts = array_column($ic["inputs"][$dim], "points");
    check("collapse: mean of $dim inputs equals score",
        round(array_sum($pts) / count($pts), 1) === $score);
}
check("collapse: scores unchanged", $ic["scores"] === ["environment" => 7.0, "nutrition" => 8.0, "welfare" => 8.5]);

section("save_dimension_scores (needs the dev database)");
query("DELETE FROM brands WHERE name = ?", "Inputs Test Co");
query("INSERT INTO brands (name, rating) VALUES (?, NULL)", "Inputs Test Co");
$tb = query("SELECT id FROM brands WHERE name = ?", "Inputs Test Co");
$tid = (int) ($tb[0]["id"] ?? 0);
check("test brand created", $tid > 0);
query("INSERT INTO brand_scores (brand_id, dimension, score, note, source) VALUES (?, 'welfare', 9, 'by hand', 'editor')", $tid);
// the importer writes as its own database user, so run the saves as that user
$child = function ($calls, $brandId = null) use ($tid) {
    $cmd = "DB_USER=" . escapeshellarg(getenv("IMPORT_DB_USER") ?: "ethicalbuy_import")
         . " DB_PASSWORD=" . escapeshellarg(getenv("IMPORT_DB_PASSWORD") ?: "ethicalbuy_import_dev")
         . " php " . escapeshellarg(__DIR__ . "/save_scores_child.php");
    $proc = proc_open($cmd, [0 => ["pipe", "r"], 1 => ["pipe", "w"]], $pipes);
    fwrite($pipes[0], json_encode(["brand_id" => $brandId ?? $tid, "calls" => $calls]));
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    proc_close($proc);
    return json_decode($out, true);
};
$url = "https://world.openfoodfacts.org/product/11";
$r = $child([
    ["scores" => $ic["scores"], "url" => $url, "apply" => true, "inputs" => $ic["inputs"]],
    ["scores" => $ic["scores"], "url" => $url, "apply" => true, "inputs" => $ic["inputs"]],
]);
check("save ok", ($r[0]["ok"] ?? false) && ($r[1]["ok"] ?? false));
$cnt = query("SELECT dimension, COUNT(*) AS n FROM brand_score_inputs WHERE brand_id = ? GROUP BY dimension ORDER BY dimension", $tid);
check("inputs replaced, not duplicated", $cnt === [["dimension" => "environment", "n" => 2], ["dimension" => "nutrition", "n" => 3]]);
check("curated dimension gets no inputs", in_array("kept curated welfare", $r[1]["notes"] ?? [], true)
    && !isset(get_brand_score_inputs($tid)["welfare"]));
check("curated score untouched", (float) query("SELECT score FROM brand_scores WHERE brand_id = ? AND dimension = 'welfare'", $tid)[0]["score"] === 9.0);
$mean = query(
    "SELECT s.dimension, s.score, ROUND(AVG(i.points), 1) AS mean
       FROM brand_scores s JOIN brand_score_inputs i ON i.brand_id = s.brand_id AND i.dimension = s.dimension
      WHERE s.brand_id = ? GROUP BY s.dimension, s.score", $tid);
check("stored inputs average to stored score", is_array($mean) && count($mean) === 2 && array_reduce($mean,
    function ($ok, $r) { return $ok && (float) $r["score"] === (float) $r["mean"]; }, true));
$fewer = $ic["inputs"]; $fewer["nutrition"] = array_slice($fewer["nutrition"], 0, 1);
$child([["scores" => ["nutrition" => off_mean_points($fewer["nutrition"])], "url" => null, "apply" => true, "inputs" => $fewer]]);
check("fewer products on refresh replace the old rows",
    count(get_brand_score_inputs($tid)["nutrition"] ?? []) === 1);
$child([["scores" => ["environment" => 2.0], "url" => null, "apply" => false, "inputs" => []]]);
check("dry run writes nothing", (float) query("SELECT score FROM brand_scores WHERE brand_id = ? AND dimension = 'environment'", $tid)[0]["score"] === 7.0);
query("DELETE FROM brands WHERE id = ?", $tid);
check("inputs cascade on brand delete", query("SELECT COUNT(*) AS n FROM brand_score_inputs WHERE brand_id = ?", $tid)[0]["n"] == 0);

section("products: matching and saving (needs the dev database)");
check("brand key: & and apostrophe", brand_match_key("Ben & Jerry's") === brand_match_key("ben-jerry-s"));
check("brand key: accents", brand_match_key("Müller") === brand_match_key("muller"));
check("brand key: near miss differs", brand_match_key("Alpron") !== brand_match_key("Alpro"));
$pp = function ($code, $name, $brands, $tags, $nutri, $eco = null, $labels = []) {
    return map_off_product(["code" => $code, "product_name" => $name, "brands" => $brands,
        "brands_tags" => $tags, "countries_tags" => ["en:united-kingdom"],
        "categories_tags" => ["en:beverages", "en:plant-based-milks"],
        "nutriscore_grade" => $nutri, "ecoscore_grade" => $eco, "labels_tags" => $labels]);
};
$feed = [
    $pp("9001", "Oat Drink", "Prodco", ["prodco"], "b", "a", ["en:vegan"]),
    $pp("9002", "Soya Drink", "Prodco", ["prodco"], "c", "b"),
    $pp("9003", "Almond Drink", "Prodco, Other Shop", ["prodco", "other-shop"], "a"),
    $pp("9004", "Rice Drink", "Prodcos", ["prodcos"], "e"),          // near miss
    $pp("9001", "Oat Drink", "Prodco", ["prodco"], "b", "a", ["en:vegan"]), // duplicate code
];
$mineP = off_products_for_brand("Prodco", $feed);
check("exact tag match only, deduplicated", array_map(function ($m) { return $m["product"]["code"]; }, $mineP) === ["9001", "9002", "9003"]);
check("near-miss brand matches nothing", off_products_for_brand("Prodc", $feed) === []);
check("product category readable", $mineP[0]["product"]["category"] === "Plant based milks");
// the old brand-level path and the product path give the same brand scores
$rawFeed = [
    ["code"=>"9001","product_name"=>"Oat Drink","brands"=>"Prodco","brands_tags"=>["prodco"],"countries_tags"=>["en:united-kingdom"],"nutriscore_grade"=>"b","ecoscore_grade"=>"a","labels_tags"=>["en:vegan"]],
    ["code"=>"9002","product_name"=>"Soya Drink","brands"=>"Prodco","brands_tags"=>["prodco"],"countries_tags"=>["en:united-kingdom"],"nutriscore_grade"=>"c","ecoscore_grade"=>"b"],
    ["code"=>"9003","product_name"=>"Almond Drink","brands"=>"Prodco, Other Shop","brands_tags"=>["prodco","other-shop"],"countries_tags"=>["en:united-kingdom"],"nutriscore_grade"=>"a"],
];
$oldScores = collapse_off_products($rawFeed)[0]["scores"];
check("product path gives the same brand scores as before", off_scores_from_products($mineP) == $oldScores);

query("DELETE FROM brands WHERE name IN ('Prodco', 'Prodc')");
query("INSERT INTO brands (name, rating) VALUES ('Prodco', 6), ('Prodc', NULL)");
$pid = (int) query("SELECT id FROM brands WHERE name = 'Prodco'")[0]["id"];
$pid2 = (int) query("SELECT id FROM brands WHERE name = 'Prodc'")[0]["id"];
query("INSERT INTO brand_scores (brand_id, dimension, score, note, source) VALUES (?, 'welfare', 3, 'by hand', 'curated')", $pid);
$runP = function ($brandId, $calls) use ($child) { return $child($calls, $brandId); };
$call = ["fn" => "products", "brand" => "Prodco", "mapped" => $feed, "apply" => true, "complete" => true];
$r1 = $runP($pid, [$call, $call]);
check("product save ok twice", ($r1[0]["ok"] ?? false) && ($r1[1]["ok"] ?? false));
check("re-saving does not duplicate products", (int) query("SELECT COUNT(*) n FROM products WHERE brand_id = ?", $pid)[0]["n"] === 3);
check("one score row per product dimension", (int) query("SELECT COUNT(*) n FROM product_scores ps JOIN products p ON p.id = ps.product_id WHERE p.brand_id = ?", $pid)[0]["n"] === 6);
$bs = query("SELECT dimension, score, source FROM brand_scores WHERE brand_id = ? ORDER BY dimension", $pid);
$means = query("SELECT ps.dimension, ROUND(AVG(ps.score), 1) m FROM product_scores ps JOIN products p ON p.id = ps.product_id WHERE p.brand_id = ? GROUP BY ps.dimension ORDER BY ps.dimension", $pid);
check("brand score equals mean of product scores", (float) $bs[0]["score"] === (float) $means[0]["m"]
    && (float) $bs[1]["score"] === (float) $means[1]["m"] && $bs[0]["dimension"] === "environment" && $bs[1]["dimension"] === "nutrition");
check("brand scores identical to the old path", (float) $bs[0]["score"] === $oldScores["environment"] && (float) $bs[1]["score"] === $oldScores["nutrition"]);
check("curated brand score kept", $bs[2]["source"] === "curated" && (float) $bs[2]["score"] === 3.0
    && in_array("kept curated welfare", $r1[0]["notes"], true));
check("product scores keep their input", query("SELECT ps.input FROM product_scores ps JOIN products p ON p.id = ps.product_id WHERE p.code = '9002' AND ps.dimension = 'nutrition'")[0]["input"] === "C");
check("product name key stored", query("SELECT name_key FROM products WHERE code = '9001'")[0]["name_key"] === "oat drink");
$r2 = $runP($pid2, [["fn" => "products", "brand" => "Prodc", "mapped" => $feed, "apply" => true, "complete" => true]]);
check("near-miss brand gets no products", (int) query("SELECT COUNT(*) n FROM products WHERE brand_id = ?", $pid2)[0]["n"] === 0);
$shorter = array_slice($feed, 0, 2);
$runP($pid, [["fn" => "products", "brand" => "Prodco", "mapped" => $shorter, "apply" => true, "complete" => false]]);
check("partial run removes nothing", (int) query("SELECT COUNT(*) n FROM products WHERE brand_id = ?", $pid)[0]["n"] === 3);
$runP($pid, [["fn" => "products", "brand" => "Prodco", "mapped" => $shorter, "apply" => true, "complete" => true]]);
check("complete run removes products no longer listed", (int) query("SELECT COUNT(*) n FROM products WHERE brand_id = ?", $pid)[0]["n"] === 2);
check("brand score follows the remaining products", (float) query("SELECT score FROM brand_scores WHERE brand_id = ? AND dimension = 'nutrition'", $pid)[0]["score"] === 7.0);
$runP($pid, [["fn" => "products", "brand" => "Prodco", "mapped" => $feed, "apply" => false, "complete" => true]]);
check("dry run writes nothing", (int) query("SELECT COUNT(*) n FROM products WHERE brand_id = ?", $pid)[0]["n"] === 2);
$runP($pid, [$call]);  // leave three products for the page tests below
$prodBrandId = $pid;

section("product page (needs the dev database)");
// runs a public page in a child PHP process, so apologize()'s exit and the
// HTTP status are exercised as on the site. Returns [status, html].
function runPage($script, $get, $cookie = []) {
    $code = '$_GET = json_decode(getenv("T_GET"), true); $_COOKIE = json_decode(getenv("T_COOKIE"), true);'
          . '$_SERVER["REQUEST_METHOD"] = "GET";'
          . 'register_shutdown_function(function () { file_put_contents("php://stderr", "STATUS:" . (http_response_code() ?: 200)); });'
          . 'require ' . var_export(__DIR__ . "/../public/" . $script, true) . ';';
    $env = array_merge(getenv(), ["T_GET" => json_encode($get), "T_COOKIE" => json_encode($cookie ?: new stdClass())]);
    $proc = proc_open([PHP_BINARY, "-r", $code], [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes, null, $env);
    $html = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    proc_close($proc);
    $status = preg_match('/STATUS:(\d+)/', $err, $m) ? (int) $m[1] : 0;
    return [$status, $html];
}
[$st, $html] = runPage("product.php", ["code" => "9001"]);
check("product: renders", $st === 200 && strpos($html, '<h3 class="card-title mb-0">Oat Drink</h3>') !== false);
structureChecks("product page", $html);
check("product: links up to brand", strpos($html, 'href="brand.php?brand=Prodco"') !== false);
check("product: own dimensions have single working", substr_count($html, 'derivation-single') === 3);
check("product: nutrition grade and points", preg_match('~Grade: B\s*= 8 points~', $html) === 1);
check("product: welfare labels shown", preg_match('~Labels: Vegan\s*= 7 points~', $html) === 1);
check("product: method line per product", strpos($html, 'Nutri-Score letter: A 10, B 8, C 6, D 4, E 2.') !== false);
check("product: editorial inherited and labelled", strpos($html, "The brand&#039;s, not this product&#039;s") !== false
    && strpos($html, 'Taken from the brand') !== false);
check("product: ODbL attribution and OFF link", strpos($html, 'Open Database Licence (ODbL)') !== false
    && strpos($html, 'href="https://world.openfoodfacts.org/product/9001" target="_blank" rel="noopener noreferrer"') !== false);
check("product: overall working collapsed", strpos($html, '<details class="mt-4 score-working">') !== false);
$pv = product_score_values(get_scores_for_products([query("SELECT id FROM products WHERE code='9001'")[0]["id"]])[(int) query("SELECT id FROM products WHERE code='9001'")[0]["id"]],
    brand_score_values(["rating" => 6], get_brand_scores($prodBrandId)));
check("product: headline uses the shared scoring", preg_match('~score-calc-result">' . preg_quote(rating_score(personal_score($pv, default_weights())["score"]), "~") . '<~', $html) === 1);
[$st, $html] = runPage("product.php", ["code" => "9003"]);
check("product: partly scored renders", $st === 200 && substr_count($html, 'Not assessed') === 3);  // environment, welfare, ownership
check("product: missing dimension excluded", strpos($html, 'no data (excluded from the average') !== false);
[$st, $html] = runPage("product.php", ["code" => "0000000"]);
check("product: unknown code is 404 with apology", $st === 404 && strpos($html, "We don&#039;t have that product.") !== false);
[$st, $html] = runPage("product.php", []);
check("product: no code apologises", strpos($html, 'No product was specified.') !== false);
$_COOKIE[PRIORITIES_COOKIE] = "editorial=0&environment=0&nutrition=5&welfare=0&ownership=0";
[$st, $html] = runPage("product.php", ["code" => "9002"], [PRIORITIES_COOKIE => $_COOKIE[PRIORITIES_COOKIE]]);
unset($_COOKIE[PRIORITIES_COOKIE]);
check("product: priorities apply (nutrition only)", strpos($html, 'score-calc-result">6/10<') !== false);

section("brand page products (needs the dev database)");
// 30 products: 28 with a nutrition grade, 2 without, so a nutrition-only
// reader sees two unscored products, which must sort last
$grades = ["a", "b", "c", "d", "e"];
$bulk = [];
for ($i = 1; $i <= 30; $i++) {
    $bulk[] = $pp(sprintf("77%04d", $i), "Bulk Item " . $i, "Prodco", ["prodco"], $i > 28 ? null : $grades[$i % 5]);
}
$runP($prodBrandId, [["fn" => "products", "brand" => "Prodco", "mapped" => array_merge($feed, $bulk), "apply" => true, "complete" => true]]);
$nutriOnly = [PRIORITIES_COOKIE => "editorial=0&environment=0&nutrition=5&welfare=0&ownership=0"];
[$st, $html] = runPage("brand.php", ["brand" => "Prodco"], $nutriOnly);
check("brand products: section shown", $st === 200 && strpos($html, '33 products listed by Open Food Facts') !== false);
structureChecks("brand products", $html);
$tbl = substr($html, strpos($html, 'brand-products'));
check("brand products: 25 per page", substr_count(substr($tbl, 0, strpos($tbl, '</table>')), 'href="product.php?code=') === 25);
check("brand products: best first", strpos($tbl, '10/10') < strpos($tbl, '8/10') && strpos($tbl, '8/10') < strpos($tbl, '6/10'));
check("brand products: pager", strpos($html, 'Page 1 of 2') !== false && strpos($html, 'pp=2#products') !== false);
[$st, $html2] = runPage("brand.php", ["brand" => "Prodco", "pp" => "2"], $nutriOnly);
$tbl2 = substr($html2, strpos($html2, 'brand-products'));
$tbl2 = substr($tbl2, 0, strpos($tbl2, '</table>'));
check("brand products: page 2 has the rest", substr_count($tbl2, 'href="product.php?code=') === 8);
check("brand products: unscored last", preg_match('~Bulk Item 29.*No data for your priorities.*Bulk Item 30.*No data for your priorities\s*</span>\s*</td>(?:(?!href="product).)*$~s', $tbl2) === 1
    || (strrpos($tbl2, 'No data for your priorities') > strrpos($tbl2, '/10')));
[$st, $html3] = runPage("brand.php", ["brand" => "Prodco", "pq" => "oat"], $nutriOnly);
$tbl3 = substr($html3, strpos($html3, 'brand-products'));
$tbl3 = substr($tbl3, 0, strpos($tbl3, '</table>'));
check("brand products: name filter", substr_count($tbl3, 'href="product.php?code=') === 1 && strpos($tbl3, 'Oat Drink') !== false);
[$st, $html4] = runPage("brand.php", ["brand" => "Prodco", "pq" => "zzz"]);
check("brand products: filter with no match", strpos($html4, 'No products match &ldquo;zzz&rdquo;') !== false);
$nutriMean = (float) query("SELECT score FROM brand_scores WHERE brand_id = ? AND dimension = 'nutrition'", $prodBrandId)[0]["score"];
check("brand working read from products", preg_match('~derivation-products.*?href="product.php\?code=\d+">~s', $html) === 1
    && preg_match('~derivation-sum">[^<]*\) / 31 = ' . preg_quote(number_format($nutriMean, 1)) . '~', $html) === 1);

section("search: brands and products (needs the dev database)");
query("DELETE FROM brands WHERE name IN ('Bén & Jerry''s Test', 'Oatsome Test')");
query("INSERT INTO brands (name, rating) VALUES ('Bén & Jerry''s Test', 7), ('Oatsome Test', 5)");
$bj = (int) query("SELECT id FROM brands WHERE name = 'Bén & Jerry''s Test'")[0]["id"];
$runP($bj, [["fn" => "products", "brand" => "Bén & Jerry's Test", "apply" => true, "complete" => true, "mapped" => [
    $pp("880001", "Cookie Dough & Brownie", "Bén & Jerry's Test", ["ben-jerry-s-test"], "e"),
    $pp("880002", "Crème Brûlée Pot", "Bén & Jerry's Test", ["ben-jerry-s-test"], "d"),
    $pp("880003", "Chunky Monkey's Banana", "Bén & Jerry's Test", ["ben-jerry-s-test"], "d"),
]]]);
[$st, $html] = runPage("search.php", ["q" => "oat"]);
structureChecks("search groups", $html);
check("search: two groups in order", $st === 200 && strpos($html, 'group-heading">Brands</h4>') !== false
    && strpos($html, 'group-heading">Brands</h4>') < strpos($html, 'id="products">Products</h4>'));
$brandsPart = substr($html, 0, strpos($html, 'id="products">Products</h4>'));
$productsPart = substr($html, strpos($html, 'id="products">Products</h4>'));
check("search: brand matched by name", strpos($brandsPart, 'Oatsome Test') !== false);
check("search: product matched and linked", strpos($productsPart, 'href="product.php?code=9001"') !== false
    && strpos($productsPart, 'href="brand.php?brand=Prodco">Prodco</a>') !== false);
check("search: product group has no brand-only matches", strpos($productsPart, 'Oatsome Test') === false);
[$st, $html] = runPage("search.php", ["q" => "Ben & Jerrys"]);
check("search: & and missing apostrophe find the brand", strpos($html, 'Bén &amp; Jerry&#039;s Test') !== false);
[$st, $html] = runPage("search.php", ["q" => "creme brulee"]);
check("search: accents ignored", strpos($html, 'Crème Brûlée Pot') !== false);
[$st, $html] = runPage("search.php", ["q" => "monkeys"]);
check("search: apostrophe in product name", strpos($html, 'Chunky Monkey&#039;s Banana') !== false);
[$st, $html] = runPage("search.php", ["q" => "cookie and brownie"]);
check("search: 'and' matches '&'", strpos($html, 'Cookie Dough &amp; Brownie') !== false);
[$st, $html] = runPage("search.php", ["q" => "o'"]);
check("search: short term with apostrophe does not error", $st === 200 && strpos($html, 'Search Results') !== false);
[$st, $html] = runPage("search.php", ["q" => ""]);
check("search: empty query lists brands, asks for a product name", $st === 200
    && strpos($html, 'Enter a name to search products.') !== false && strpos($html, 'Prodco') !== false);
[$st, $html] = runPage("search.php", ["q" => "the"]);
check("search: stopword query does not error", $st === 200);
[$st, $html] = runPage("search.php", ["search_string" => "Prodco"]);
check("search: old parameter still works", strpos($html, 'href="brand.php?brand=Prodco"') !== false);
// InnoDB's full-text index can trail a commit from another connection by a
// moment; wait for it rather than fail on timing
// (checked through a fresh connection, as the page uses one)
for ($wait = 0; $wait < 30; $wait++) {
    [$st, $html] = runPage("search.php", ["q" => "bulk"]);
    if (strpos($html, '30 products matched') !== false) { break; }
    usleep(200000);
}
check("search: products paginated", strpos($html, '30 products matched') !== false && strpos($html, 'Page 1 of 2') !== false
    && substr_count($html, 'href="product.php?code=') === 25);
[$st, $html] = runPage("search.php", ["q" => "bulk", "page" => "2"]);
check("search: page 2", substr_count($html, 'href="product.php?code=') === 5);
[$st, $html] = runPage("search.php", ["q" => "Bulk Item 1"]);
check("search: exact name first", preg_match('~search-products.*?href="product.php\?code=770001"~s', $html) === 1);
[$st, $html] = runPage("index.php", []);
check("nav: menu says Search", strpos($html, '>Search</a>') !== false && strpos($html, 'Product Search') === false);
query("DELETE FROM brands WHERE name IN ('Bén & Jerry''s Test', 'Oatsome Test', 'Prodco', 'Prodc')");

section("ownership: type mapping");
$expected = [
    "Multinational Corporation" => "multinational", "UK Public Company" => "listed",
    "Independent UK Company" => "independent", "Private Equity" => "private_equity",
    "Family-Owned UK Company" => "family", "Family-Owned" => "family", "Private UK Company" => "private",
    "Private Company" => "private", "Swiss Corporation" => "multinational", "Co-operative" => "cooperative",
    "Public Company" => "listed", "German Family-Owned" => "family", "Supermarket Own Brand" => "own_brand",
    "Subsidiary (US Corporation)" => "subsidiary", "Publicly Traded UK Company" => "listed",
    "Irish Co-operative" => "cooperative", "Retail Own Brand" => "own_brand",
    "Subsidiary of German Pharma Company" => "subsidiary", "Independent US Company" => "independent",
    "Independent (Social Enterprise)" => "social_enterprise", "Private Austrian Company" => "private",
    "Subsidiary (US Corp)" => "subsidiary", "Private Irish Company" => "private",
];
$points = ["social_enterprise" => 10, "cooperative" => 9, "independent" => 8, "family" => 8, "private" => 6,
           "listed" => 4, "own_brand" => 3, "private_equity" => 3, "subsidiary" => 3, "multinational" => 2];
foreach ($expected as $type => $key) {
    $c = ownership_category_for_type($type);
    check("type \"$type\" -> $key", $c !== null && $c["key"] === $key && $c["points"] === $points[$key]);
}
check("every category has the agreed points", array_column(ownership_categories(), "points", "key") == $points);
check("case, accents and punctuation ignored", ownership_category_for_type("  family owned uk  COMPANY ")["key"] === "family"
    && ownership_category_for_type("Co operative")["key"] === "cooperative");
check("unseen type -> null", ownership_category_for_type("Mutual society") === null);
check("no substring match: Independent", ownership_category_for_type("Independent") === null);
check("no substring match: Private", ownership_category_for_type("Private") === null);
check("no substring match: longer private type", ownership_category_for_type("Private Equity Backed Co-operative") === null);
check("empty and null types -> null", ownership_category_for_type("") === null && ownership_category_for_type(null) === null);
check("note format", ownership_note("Family-Owned UK Company", ownership_category_for_type("Family-Owned UK Company"))
    === 'Recorded as "Family-Owned UK Company"; counted as Family-owned: 8');
check("dimension renamed", score_dimensions()["ownership"]["label"] === "Ownership");

section("ownership: script and pages (needs the dev database)");
// runs bin/score-ownership.php as the importer's database user
function runOwnershipScript($apply) {
    $env = array_merge(getenv(), ["DB_USER" => getenv("IMPORT_DB_USER") ?: "ethicalbuy_import",
                                  "DB_PASSWORD" => getenv("IMPORT_DB_PASSWORD") ?: "ethicalbuy_import_dev"]);
    $cmd = [PHP_BINARY, __DIR__ . "/../bin/score-ownership.php"];
    if ($apply) { $cmd[] = "--apply"; }
    $proc = proc_open($cmd, [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes, null, $env);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    return [proc_close($proc), $out];
}
$ownRows = function ($name) {
    return query("SELECT s.score, s.note, s.source FROM brand_scores s JOIN brands b ON b.id = s.brand_id
                   WHERE b.name = ? AND s.dimension = 'ownership'", $name);
};
query("DELETE FROM brands WHERE name LIKE 'Own Test %'");
query("INSERT INTO brands (name, type, rating) VALUES
       ('Own Test Family', 'Family-Owned UK Company', 7),
       ('Own Test Mutual', 'Mutual society', 7),
       ('Own Test Curated', 'Multinational Corporation', 7),
       ('Own Test Multi', 'Multinational Corporation', 6)");
$curId = (int) query("SELECT id FROM brands WHERE name = 'Own Test Curated'")[0]["id"];
admin_save_brand_score($curId, "ownership", 7.0, "Checked by hand");

[$rc, $out] = runOwnershipScript(false);
check("script: dry run exits 0", $rc === 0 && strpos($out, "DRY RUN") !== false);
check("script: dry run writes nothing", $ownRows("Own Test Family") === []);
check("script: dry run counts per category", preg_match('/Family-owned\s+8 pts\s+[1-9]/', $out) === 1);
check("script: reports unmapped name and type", strpos($out, 'Own Test Mutual: "Mutual society"') !== false);
check("script: reports curated skip", strpos($out, "Own Test Curated (source: curated)") !== false);

[$rc, $out] = runOwnershipScript(true);
$fam = $ownRows("Own Test Family");
check("script: writes the expected row", $rc === 0 && count($fam) === 1 && (float) $fam[0]["score"] === 8.0
    && $fam[0]["source"] === "type-mapping"
    && $fam[0]["note"] === 'Recorded as "Family-Owned UK Company"; counted as Family-owned: 8');
check("script: unmapped stays unscored", $ownRows("Own Test Mutual") === []);
check("script: curated row untouched", (float) $ownRows("Own Test Curated")[0]["score"] === 7.0
    && $ownRows("Own Test Curated")[0]["source"] === "curated");
[$rc, $out] = runOwnershipScript(true);
check("script: idempotent", count($ownRows("Own Test Family")) === 1 && preg_match('/would write|written\s+0/', $out) === 1
    && preg_match('/unchanged\s+[1-9]/', $out) === 1);
query("UPDATE brands SET type = 'Co-operative' WHERE name = 'Own Test Family'");
runOwnershipScript(true);
check("script: re-run follows a changed type", (float) $ownRows("Own Test Family")[0]["score"] === 9.0);
query("UPDATE brands SET type = 'Family-Owned UK Company' WHERE name = 'Own Test Family'");
runOwnershipScript(true);

[$st, $html] = runPage("brand.php", ["brand" => "Own Test Family"]);
structureChecks("ownership brand", $html);
check("brand: ownership row expands", preg_match('~<details class="dimension-derivation">\s*<summary>Ownership</summary>~', $html) === 1);
check("brand: recorded type, category and points", strpos($html,
    'Recorded as &ldquo;Family-Owned UK Company&rdquo;, counted as Family-owned: 8') !== false);
check("brand: method line", strpos($html, "Scored from the brand's recorded ownership type.") !== false);
check("brand: full category table, this brand marked",
    substr_count($html, '<td>Multinational group</td>') === 1 && strpos($html, 'Family-owned (this brand)') !== false);
check("brand: arithmetic includes ownership", strpos($html, 'Ownership 8.0 &times; 3 = 24.0') !== false);
$famValues = brand_score_values(["rating" => 7], get_brand_scores((int) query("SELECT id FROM brands WHERE name = 'Own Test Family'")[0]["id"]));
check("brand: headline equals the working (default weights)",
    preg_match('~score-calc-result">' . preg_quote(rating_score(personal_score($famValues, default_weights())["score"]), "~") . '<~', $html) === 1);
$ownCookie = "editorial=1&environment=0&nutrition=0&welfare=0&ownership=5";
[$st, $html] = runPage("brand.php", ["brand" => "Own Test Family"], [PRIORITIES_COOKIE => $ownCookie]);
check("brand: headline equals the working (custom weights)",
    strpos($html, '(7.0 + 40.0) / (1 + 5) = 47.0 / 6 = <strong class="score-calc-result">7.8/10</strong>') !== false
    && round((7 * 1 + 8 * 5) / 6, 1) === personal_score($famValues, parse_weights($ownCookie))["score"]);
[$st, $html] = runPage("brand.php", ["brand" => "Own Test Mutual"]);
check("brand: unmapped type is Not assessed", preg_match('~<td>\s*Ownership\s*</td>.*?Not assessed~s', $html) === 1);
[$st, $html] = runPage("brand.php", ["brand" => "Own Test Curated"]);
check("brand: hand entry says so with its note", strpos($html, 'Entered by hand.') !== false
    && strpos($html, 'Checked by hand') !== false && strpos($html, 'counted as Multinational') === false);

// a product inherits the brand's working
$famId = (int) query("SELECT id FROM brands WHERE name = 'Own Test Family'")[0]["id"];
$child([["fn" => "products", "brand" => "Own Test Family", "apply" => true, "complete" => true,
         "mapped" => [$pp("660001", "Family Jam", "Own Test Family", ["own-test-family"], "c")]]], $famId);
[$st, $html] = runPage("product.php", ["code" => "660001"]);
check("product: ownership inherited and labelled", strpos($html, 'Taken from the brand') !== false
    && strpos($html, "The brand&#039;s, not this product&#039;s") !== false);
check("product: same ownership working as the brand", strpos($html,
    'Recorded as &ldquo;Family-Owned UK Company&rdquo;, counted as Family-owned: 8') !== false
    && strpos($html, 'Family-owned (this brand)') !== false);
check("product: arithmetic includes ownership", strpos($html, 'Ownership 8.0 &times; 3 = 24.0') !== false);
$pScores = get_scores_for_products([(int) query("SELECT id FROM products WHERE code = '660001'")[0]["id"]]);
$pValues = product_score_values(reset($pScores), $famValues);
check("product: headline equals the working",
    preg_match('~score-calc-result">' . preg_quote(rating_score(personal_score($pValues, default_weights())["score"]), "~") . '<~', $html) === 1);

// /admin: an unchanged form keeps the type-mapped source; a real edit makes it curated
$multiId = (int) query("SELECT id FROM brands WHERE name = 'Own Test Multi'")[0]["id"];
$form = [];
foreach (get_brand_scores($multiId) as $dim => $row) {
    $form["score_$dim"] = rtrim(rtrim((string) $row["score"], "0"), ".");
    $form["note_$dim"] = (string) ($row["note"] ?? "");
}
check("admin: unchanged save ok", admin_save_brand_scores($multiId, $form) === []);
check("admin: unchanged ownership keeps its source", $ownRows("Own Test Multi")[0]["source"] === "type-mapping");
$form["score_ownership"] = "5";
$form["note_ownership"] = "Now employee-owned";
admin_save_brand_scores($multiId, $form);
check("admin: hand edit is not type-mapping", $ownRows("Own Test Multi")[0]["source"] === "curated"
    && (float) $ownRows("Own Test Multi")[0]["score"] === 5.0);
runOwnershipScript(true);
check("script: never overwrites the hand edit", (float) $ownRows("Own Test Multi")[0]["score"] === 5.0);

$o = renderTo("about.php", ["title" => "About"]);
check("about: ownership table generated", substr_count($o, '<td>Multinational group</td>') === 1
    && strpos($o, 'Business size is not scored yet.') !== false);
check("about: no longer says ownership is hand entered", strpos($o, 'Ownership</h5>') !== false
    && strpos($o, '>Our overall view</h5>') !== false);
query("DELETE FROM brands WHERE name LIKE 'Own Test %'");

section("visual refresh");
$o = renderTo("about.php", ["title" => "About"]);
check("layout links the site stylesheet after Bootstrap",
    strpos($o, '<link href="/assets/site.css" rel="stylesheet">') !== false
    && strpos($o, 'bootstrap.min.css') < strpos($o, '/assets/site.css'));
check("stylesheet exists", is_file(__DIR__ . "/../public/assets/site.css"));
check("footer keeps the Open Food Facts attribution", strpos($o, 'Open Database Licence (ODbL)') !== false);
foreach (["9.5" => "Excellent", "7" => "Good", "5" => "Mixed", "4" => "Poor", "2" => "Avoid"] as $r => $label) {
    $r = (float) $r;
    $rating = $r;
    ob_start(); require(TEMPLATE_DIR . "/partials/rating_badge.php"); $badge = ob_get_clean();
    check("badge $r shows score and label",
        strpos($badge, '<span class="score__value">' . rating_score($r) . '</span>') !== false
        && strpos($badge, '<span class="score__label">' . $label . '</span>') !== false
        && strpos($badge, 'score--' . rating_class($r)) !== false);
}
$rating = null;
ob_start(); require(TEMPLATE_DIR . "/partials/rating_badge.php"); $badge = ob_get_clean();
check("unrated badge says Not rated", strpos($badge, 'Not rated') !== false && strpos($badge, 'score--none') !== false);
$css = file_get_contents(__DIR__ . "/../public/assets/site.css");
foreach (["success", "primary", "secondary", "warning", "danger"] as $band) {
    check("css defines score band $band", strpos($css, ".score--$band") !== false && strpos($css, "--score-$band-bg") !== false);
}
check("css makes no external requests", preg_match('~url\(|@import|https?://~i', $css) === 0);
$_SERVER["SCRIPT_NAME"] = "/search.php";
$o = renderTo("about.php", ["title" => "About"]);
unset($_SERVER["SCRIPT_NAME"]);
check("nav marks the current page", strpos($o, 'class="nav-link active" href="/search.php" aria-current="page"') !== false);

section("neutral branding");
$badgeFor = function ($r) { $rating = $r; ob_start(); require(TEMPLATE_DIR . "/partials/rating_badge.php"); return ob_get_clean(); };
$low = $badgeFor(2.0);
$high = $badgeFor(9.0);
check("2.0 badge is the Avoid band", strpos($low, 'score--danger') !== false && strpos($low, '2/10') !== false && strpos($low, 'Avoid') !== false);
check("9.0 badge is the Excellent band", strpos($high, 'score--success') !== false && strpos($high, '9/10') !== false && strpos($high, 'Excellent') !== false);
check("low and high badges differ", rating_class(2.0) !== rating_class(9.0));

// every hex colour in the stylesheet outside the score tokens and the
// success alert must be neutral or the blue accent: no red, amber or green
$css = file_get_contents(__DIR__ . "/../public/assets/site.css");
$chrome = preg_replace('~^.*--(score|eb-error)-?.*$~m', '', $css);
$chrome = preg_replace('~\.alert-success \{[^}]*\}~', '', $chrome);
$offenders = [];
preg_match_all('~#([0-9a-f]{6})\b~i', $chrome, $m);
foreach (array_unique($m[1]) as $hex) {
    [$r, $g, $b] = array_map(function ($i) use ($hex) { return hexdec(substr($hex, $i, 2)) / 255; }, [0, 2, 4]);
    $max = max($r, $g, $b); $min = min($r, $g, $b); $d = $max - $min;
    $sat = $max == 0 ? 0 : $d / $max;
    if ($d == 0) { continue; }
    $hue = $max == $r ? fmod(($g - $b) / $d + 6, 6) * 60 : ($max == $g ? (($b - $r) / $d + 2) * 60 : (($r - $g) / $d + 4) * 60);
    // allow near-greys and the blue accent family
    if ($sat > 0.15 && !($hue >= 190 && $hue <= 250)) { $offenders[] = "#$hex"; }
}
check("no red, amber or green in the chrome", $offenders === []) || print("   " . implode(", ", $offenders) . "\n");
check("score-bar uses the score ramp", strpos($css, '.score-bar--danger    > span { background: var(--score-danger-bar); }') !== false);
$o = renderTo("about.php", ["title" => "About"]);
check("wordmark is the neutral tick mark", strpos($o, '<circle cx="12" cy="12" r="9.5"') !== false && strpos($o, 'M20 4c-7.5') === false);

section("open food facts dump: grades and reading");
check("eco a-plus -> 10", off_grade_to_score("a-plus", off_eco_grade_map()) === 10.0);
check("eco f -> 1", off_grade_to_score("f", off_eco_grade_map()) === 1.0);
foreach (["unknown", "not-applicable", "", null] as $g) {
    check("eco " . var_export($g, true) . " -> unscored", off_grade_to_score($g, off_eco_grade_map()) === null);
}
check("nutri-score has no a-plus or f", off_grade_to_score("a-plus") === null && off_grade_to_score("f") === null);
check("grade label A+", off_grade_label("a-plus") === "A+" && off_grade_label("c") === "C");
check("environment method lists A+ and F", strpos(off_dimension_method("environment"), "A+ 10") !== false
    && strpos(off_dimension_method("environment"), "F 1") !== false);
$aboutHtml = renderTo("about.php", ["title" => "About"]);
check("about: eco mapping generated with A+ and F", strpos($aboutHtml, "A+ = 10, A = 10, B = 8, C = 6, D = 4, E = 2, F = 1") !== false);
foreach (["xx:dump-alpha", "en:dump-alpha", "fr:dump-alpha"] as $tag) {
    check("prefix stripped: $tag", in_array(brand_match_key("Dump Alpha"), off_product_brand_keys(["brands_tags" => [$tag]]), true));
}

$fixture = __DIR__ . "/fixtures/off-dump-sample.tsv";
$rows = iterator_to_array(off_dump_rows($fixture, null, $readStats), false);
check("dump: header read by name, malformed lines skipped", count($rows) === 10 && $readStats["malformed"] === 2);
check("dump: tags split into arrays", $rows[0]["brands_tags"] === ["xx:dump-alpha"] && $rows[0]["countries_tags"] === ["en:france", "en:united-kingdom"]);
$gz = tempnam(sys_get_temp_dir(), "offdump") . ".tsv.gz";
file_put_contents($gz, gzencode(file_get_contents($fixture)));
check("dump: gzip and plain read identically", iterator_to_array(off_dump_rows($gz), false) == $rows);
$fakeBrands = [["id" => 1, "name" => "Dump Alpha"], ["id" => 2, "name" => "Dump Beta"]];
$uk = off_dump_products_by_brand($fixture, $fakeBrands, "en:united-kingdom", $st);
check("dump: UK only", array_keys($uk[1]) === ["D001", "D002", "D003", "D005"]);
check("dump: shared product filed once, under its first tag we hold", isset($uk[1]["D005"]) && !isset($uk[2]["D005"]) && $st["shared"] === 1);
check("dump: near miss and other brands ignored", !isset($uk[1]["D009"]) && count($uk) === 2);
$any = off_dump_products_by_brand($fixture, $fakeBrands, null);
check("dump: --country=any keeps the French-only product", isset($any[1]["D004"]));
check("dump: welfare from labels", $uk[1]["D001"]["scores"]["welfare"] === 9.5 && !isset($uk[1]["D002"]["scores"]["welfare"]));
check("dump: a-plus and f scored, unknown and blank not", $uk[1]["D001"]["scores"]["environment"] === 10.0
    && $uk[1]["D002"]["scores"]["environment"] === 1.0 && !isset($uk[1]["D003"]["scores"]["environment"])
    && !isset($uk[1]["D005"]["scores"]["environment"]) && !isset($uk[1]["D003"]["scores"]["nutrition"]));
check("dump: input shown as A+", $uk[1]["D001"]["contributions"]["environment"]["input"] === "A+");

section("open food facts dump: importer run (needs the dev database)");
function runDumpImport($args) {
    $env = array_merge(getenv(), ["DB_USER" => getenv("IMPORT_DB_USER") ?: "ethicalbuy_import",
        "DB_PASSWORD" => getenv("IMPORT_DB_PASSWORD") ?: "ethicalbuy_import_dev",
        // would fail loudly if anything tried the network
        "OFF_BASE" => "http://127.0.0.1:9"]);
    $proc = proc_open(array_merge([PHP_BINARY, __DIR__ . "/../bin/import-openfoodfacts.php"], $args),
        [1 => ["pipe", "w"], 2 => ["pipe", "w"]], $pipes, null, $env);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    return [proc_close($proc), $out];
}
query("DELETE FROM brands WHERE name LIKE 'Dump %'");
query("INSERT INTO brands (name, rating, notes) VALUES ('Dump Alpha', 6, 'keep me'), ('Dump Beta', NULL, NULL),
       ('Dump Gamma', 7, NULL), ('Dump Delta', 5, NULL)");
$did = function ($n) { return (int) query("SELECT id FROM brands WHERE name = ?", $n)[0]["id"]; };
$child([["fn" => "products", "brand" => "Dump Gamma", "apply" => true, "complete" => true,
         "mapped" => [$pp("G001", "Gamma Old Product", "Dump Gamma", ["dump-gamma"], "b")]]], $did("Dump Gamma"));
$child([["fn" => "products", "brand" => "Dump Delta", "apply" => true, "complete" => true,
         "mapped" => [$pp("D998", "Delta Discontinued", "Dump Delta", ["dump-delta"], "a"),
                      $pp("D007", "Delta Kept Jam", "Dump Delta", ["dump-delta"], "d")]]], $did("Dump Delta"));
$codesOf = function ($name) { return array_column(query("SELECT p.code FROM products p JOIN brands b ON b.id = p.brand_id WHERE b.name = ? ORDER BY p.code", $name) ?: [], "code"); };

[$rc, $out] = runDumpImport(["--products"]);
check("--products without --from-dump refuses", $rc === 1 && strpos($out, "add --from-dump=FILE") !== false);

[$rc, $dry] = runDumpImport(["--products", "--from-dump=$fixture"]);
check("dry run: exit 0, no contact needed, no network", $rc === 0 && strpos($dry, "GET ") === false && strpos($dry, "network call") === false);
check("dry run: writes nothing", $codesOf("Dump Alpha") === []);
check("dry run: per-brand products and grades", preg_match('~Dump Alpha\s+4 products \(\+4 -0\)~', $dry) === 1
    && strpos($dry, "environment A+:1 F:1") !== false);
check("dry run: resulting scores", strpos($dry, "nutrition 8 (new)") !== false && strpos($dry, "environment 5.5 (new)") !== false);
check("dry run: removal counted for a processed brand", preg_match('~Dump Delta\s+1 products \(\+0 -1\)~', $dry) === 1);
check("dry run: zero-product brand reported with its tag", strpos($dry, 'Dump Gamma  (looked for tag "dump-gamma")') !== false);
check("dry run: shared and malformed counted", strpos($dry, "2 malformed skipped, 1 shared") !== false);
[$rc, $dryGz] = runDumpImport(["--products", "--from-dump=$gz"]);
$strip = function ($o) { return preg_replace('~^dump: .*$~m', '', preg_replace('~in \d+s~', '', $o)); };
check("dry run: gzip and plain give the same report", $strip($dry) === $strip($dryGz));

[$rc, $out] = runDumpImport(["--products", "--from-dump=$fixture", "--apply"]);
check("apply: exit 0, no network", $rc === 0 && strpos($out, "GET ") === false);
check("apply: products written", $codesOf("Dump Alpha") === ["D001", "D002", "D003", "D005"] && $codesOf("Dump Beta") === ["D006", "D010"]);
$bsOf = function ($name) {
    $r = query("SELECT s.dimension, s.score FROM brand_scores s JOIN brands b ON b.id = s.brand_id WHERE b.name = ?", $name);
    return array_map("floatval", array_column($r ?: [], "score", "dimension"));
};
$alpha = $bsOf("Dump Alpha");
check("apply: derived brand scores", $alpha == ["nutrition" => 8.0, "environment" => 5.5, "welfare" => 9.5]);
$meanRows = query("SELECT ps.dimension, ROUND(AVG(ps.score), 1) m FROM product_scores ps JOIN products p ON p.id = ps.product_id
                   JOIN brands b ON b.id = p.brand_id WHERE b.name = 'Dump Alpha' GROUP BY ps.dimension");
check("apply: brand score equals mean of product scores", array_map("floatval", array_column($meanRows, "m", "dimension")) == $alpha);
check("apply: retrieved_at is the dump's date", query("SELECT retrieved_at FROM products WHERE code = 'D001'")[0]["retrieved_at"] === off_dump_date($fixture));
check("apply: source_url is the product page", query("SELECT source_url FROM products WHERE code = 'D001'")[0]["source_url"] === "https://world.openfoodfacts.org/product/D001");
check("apply: brand with no dump rows keeps its products", $codesOf("Dump Gamma") === ["G001"]);
check("apply: product missing from the dump deleted", $codesOf("Dump Delta") === ["D007"]);
check("apply: rating and notes untouched", query("SELECT rating, notes FROM brands WHERE name = 'Dump Alpha'")[0] == ["rating" => "6.0", "notes" => "keep me"]);
$before = query("SELECT p.code, p.brand_id, p.retrieved_at, ps.dimension, ps.score, ps.input FROM products p
                  LEFT JOIN product_scores ps ON ps.product_id = p.id WHERE p.code LIKE 'D0%' ORDER BY p.code, ps.dimension");
[$rc, $again] = runDumpImport(["--products", "--from-dump=$fixture", "--apply"]);
$after = query("SELECT p.code, p.brand_id, p.retrieved_at, ps.dimension, ps.score, ps.input FROM products p
                 LEFT JOIN product_scores ps ON ps.product_id = p.id WHERE p.code LIKE 'D0%' ORDER BY p.code, ps.dimension");
check("second run: no data changes", $before === $after && $bsOf("Dump Alpha") == $alpha);
[$rc, $dry2] = runDumpImport(["--products", "--from-dump=$fixture"]);
check("second run: reports no changes", preg_match('~updated\s+0~', $dry2) === 1 && strpos($dry2, "Largest score changes:\n  none") !== false
    && preg_match('~Dump Alpha\s+4 products \(\+0 -0\)~', $dry2) === 1);
[$st, $html] = runPage("product.php", ["code" => "D001"]);
check("product page shows the A+ grade", preg_match('~Grade: A\+\s*= 10 points~', $html) === 1);
query("DELETE FROM brands WHERE name LIKE 'Dump %'");
@unlink($gz);

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURE(S)\n";
exit($fails === 0 ? 0 : 1);
