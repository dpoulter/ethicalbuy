<?php

/**
 * Scores the Ownership dimension from each brand's recorded ownership type.
 *
 *   php bin/score-ownership.php            # dry run: prints what it would do
 *   php bin/score-ownership.php --apply    # writes
 *
 * For every brand whose brands.type is listed in ownership_categories()
 * (includes/ownership.php), writes a brand_scores row with dimension
 * "ownership", the category's points and a note saying how it was reached.
 *
 * Guarantees, enforced below:
 *   * never overwrites an ownership score entered by hand in /admin (any
 *     source other than "type-mapping"); those are reported as skipped
 *   * never guesses: a type not in the table is reported and left unscored
 *   * re-running only updates its own rows, one per brand, never duplicated
 *   * touches nothing but ownership rows in brand_scores
 *
 * Runs with the importer's database user; needs no new grants.
 */

if (PHP_SAPI !== "cli")
{
    http_response_code(404);
    exit;
}

require_once(__DIR__ . "/../includes/constants.php");
require_once(__DIR__ . "/../includes/functions.php");
require_once(__DIR__ . "/../includes/scoring.php");

error_reporting(E_ALL);
ini_set("display_errors", "1");

$options = getopt("", ["apply", "help"]);

if (isset($options["help"]))
{
    fwrite(STDOUT, "Usage: php bin/score-ownership.php [--apply]\n\n" .
        "Dry run unless --apply. Scores Ownership from brands.type using\n" .
        "the table in includes/ownership.php.\n");
    exit(0);
}

$apply = isset($options["apply"]);

/**
 * Plans (and, when applying, writes) ownership scores. Returns a report:
 *   categories  [label => count] scored per category
 *   written     rows inserted or changed
 *   unchanged   rows already correct
 *   unmapped    [[name, type]] brands whose type is not in the table
 *   curated     [[name, source]] brands with a hand-entered score, left alone
 *   failed      [name] brands whose write failed
 * or false if the brands could not be read.
 */
function score_ownership($apply)
{
    $brands = query("SELECT id, name, type FROM brands ORDER BY name");
    $existing = query(
        "SELECT brand_id, score, note, source FROM brand_scores WHERE dimension = 'ownership'"
    );

    if ($brands === false || $existing === false)
    {
        return false;
    }

    $current = [];
    foreach ($existing as $row)
    {
        $current[(int) $row["brand_id"]] = $row;
    }

    $report = ["categories" => [], "written" => 0, "unchanged" => 0,
               "unmapped" => [], "curated" => [], "failed" => []];

    foreach ($brands as $brand)
    {
        $id = (int) $brand["id"];
        $have = $current[$id] ?? null;

        // a value entered in /admin always wins
        if ($have !== null && $have["source"] !== OWNERSHIP_SOURCE)
        {
            $report["curated"][] = [$brand["name"], $have["source"]];
            continue;
        }

        $category = ownership_category_for_type($brand["type"]);

        if ($category === null)
        {
            $report["unmapped"][] = [$brand["name"], $brand["type"]];
            continue;
        }

        $label = $category["label"];
        $report["categories"][$label] = ($report["categories"][$label] ?? 0) + 1;

        $note = ownership_note($brand["type"], $category);

        if ($have !== null && (float) $have["score"] === (float) $category["points"] && $have["note"] === $note)
        {
            $report["unchanged"]++;
            continue;
        }

        if ($apply)
        {
            $ok = query(
                "INSERT INTO brand_scores (brand_id, dimension, score, note, source, retrieved_at)
                 VALUES (?, 'ownership', ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE score = VALUES(score), note = VALUES(note),
                     source = VALUES(source), retrieved_at = VALUES(retrieved_at)",
                $id, $category["points"], $note, OWNERSHIP_SOURCE
            );

            if ($ok === false)
            {
                $report["failed"][] = $brand["name"];
                continue;
            }
        }

        $report["written"]++;
    }

    return $report;
}

fwrite(STDOUT, ($apply ? "APPLYING" : "DRY RUN (pass --apply to write)") . "\n\n");

$report = score_ownership($apply);

if ($report === false)
{
    fwrite(STDERR, "Could not read brands or scores.\n");
    exit(1);
}

fwrite(STDOUT, "Scored by category:\n");
foreach (ownership_categories() as $category)
{
    fwrite(STDOUT, sprintf("  %-34s %2d pts  %d\n",
        $category["label"], $category["points"], $report["categories"][$category["label"]] ?? 0));
}

fwrite(STDOUT, sprintf("\n  %s %d\n  unchanged     %d\n",
    $apply ? "written      " : "would write  ", $report["written"], $report["unchanged"]));

fwrite(STDOUT, "\nUnmapped type, left unscored (" . count($report["unmapped"]) . "):\n");
foreach ($report["unmapped"] as [$name, $type])
{
    fwrite(STDOUT, "  $name: " . ($type === null || $type === "" ? "(no type)" : "\"$type\"") . "\n");
}

fwrite(STDOUT, "\nSkipped, entered by hand (" . count($report["curated"]) . "):\n");
foreach ($report["curated"] as [$name, $source])
{
    fwrite(STDOUT, "  $name (source: $source)\n");
}

if ($report["failed"])
{
    fwrite(STDOUT, "\nFailed (" . count($report["failed"]) . "): " . implode(", ", $report["failed"]) . "\n");
}

if (!$apply)
{
    fwrite(STDOUT, "\nNothing was written. Re-run with --apply.\n");
}

exit($report["failed"] ? 1 : 0);
