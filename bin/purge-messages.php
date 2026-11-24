<?php
/**
 * Deletes contact messages and log rows older than the retention period,
 * plus expired rate limit counters. Dry run unless --apply.
 *
 *   DB_USER=ethicalbuy_import DB_PASSWORD=... php bin/purge-messages.php [--apply]
 *
 * Retention is MESSAGE_RETENTION_DAYS (default 180), the same figure the
 * privacy policy shows. Run it as the importer account: the site's own
 * account cannot delete. From cron, daily:
 *
 *   17 3 * * * cd /srv/ethicalbuy && php bin/purge-messages.php --apply
 *
 * Prints counts and dates only, never message contents.
 */

if (PHP_SAPI !== "cli")
{
    http_response_code(404);
    exit;
}

require_once(__DIR__ . "/../includes/constants.php");
require_once(__DIR__ . "/../includes/functions.php");

error_reporting(E_ALL);
ini_set("display_errors", "1");

$options = getopt("", ["apply", "help"]);

if (isset($options["help"]))
{
    fwrite(STDOUT, "Usage: php bin/purge-messages.php [--apply]\n\n" .
        "Dry run unless --apply. Deletes contact_messages and message_log rows\n" .
        "older than MESSAGE_RETENTION_DAYS (default 180), and rate_limits\n" .
        "counters older than two days.\n");
    exit(0);
}

$apply = isset($options["apply"]);
$days = message_retention_days();
$cutoff = date("Y-m-d H:i:s", time() - $days * 86400);
$counter_cutoff = time() - 2 * 86400;

$targets = [
    ["contact_messages", "created_at < ?", $cutoff],
    ["message_log", "created_at < ?", $cutoff],
    ["rate_limits", "window_start < ?", $counter_cutoff],
];

echo ($apply ? "Purging" : "Dry run: would purge") . " rows older than $days days (before $cutoff)\n";

$failed = false;

foreach ($targets as [$table, $where, $param])
{
    // table and condition come from the list above, never from input
    $rows = query("SELECT COUNT(*) AS n FROM $table WHERE $where", $param);

    if ($rows === false)
    {
        echo "  $table: could not read (check grants)\n";
        $failed = true;
        continue;
    }

    $n = (int) $rows[0]["n"];

    if ($n > 0 && $table !== "rate_limits")
    {
        $range = query("SELECT MIN(created_at) AS oldest, MAX(created_at) AS newest FROM $table WHERE $where", $param);
        $span = $range ? " (" . $range[0]["oldest"] . " to " . $range[0]["newest"] . ")" : "";
    }
    else
    {
        $span = "";
    }

    if (!$apply)
    {
        echo "  $table: $n row(s)$span\n";
        continue;
    }

    if ($n > 0 && query("DELETE FROM $table WHERE $where", $param) === false)
    {
        echo "  $table: delete failed (check grants)\n";
        $failed = true;
        continue;
    }

    echo "  $table: deleted $n row(s)$span\n";
}

if (!$apply)
{
    echo "Nothing deleted. Re-run with --apply to delete.\n";
}

exit($failed ? 1 : 0);
