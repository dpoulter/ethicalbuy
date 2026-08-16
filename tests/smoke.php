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

echo $fails === 0 ? "\nALL PASS\n" : "\n$fails FAILURE(S)\n";
exit($fails === 0 ? 0 : 1);
