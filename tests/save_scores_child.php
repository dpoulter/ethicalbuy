<?php
/**
 * Helper for tests/smoke.php: runs save_dimension_scores() or
 * save_brand_products() as the importer's
 * database user. Reads {"brand_id", "calls": [...]} as JSON on stdin and
 * prints [{ok, notes}, ...]. Not part of the site.
 */

if (PHP_SAPI !== "cli")
{
    http_response_code(404);
    exit;
}

putenv("DB_NAME=" . (getenv("DB_NAME") ?: "ethicalbuy"));
putenv("APP_DEBUG=0");

require_once(__DIR__ . "/../includes/constants.php");
require_once(__DIR__ . "/../includes/functions.php");
require_once(__DIR__ . "/../includes/import.php");

$in = json_decode(stream_get_contents(STDIN), true);
$out = [];

foreach ($in["calls"] as $call)
{
    $notes = [];
    $ok = ($call["fn"] ?? "") === "products"
        ? save_brand_products((int) $in["brand_id"], $call["brand"], $call["mapped"],
            $call["apply"], $notes, $call["complete"] ?? false)
        : save_dimension_scores((int) $in["brand_id"], $call["scores"], $call["url"],
            $call["apply"], $notes, $call["inputs"]);
    $out[] = ["ok" => $ok, "notes" => $notes];
}

echo json_encode($out);
