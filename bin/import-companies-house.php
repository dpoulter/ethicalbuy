<?php

/**
 * Resolves brand owners to their Companies House identity.
 *
 *   export CH_API_KEY=...
 *   php bin/import-companies-house.php --contact=you@example.com
 *   php bin/import-companies-house.php --contact=you@example.com --apply
 *
 * Get a free API key at https://developer.company-information.service.gov.uk/
 *
 * What this writes: the registered company name, number, status, incorporation
 * date, and the corporate parent from the PSC register.
 *
 * What it refuses to write:
 *   * an ownership link it is not certain about -- those become candidates for
 *     a human to confirm in /admin/owners.php
 *   * anything about an individual person. The PSC register names real people
 *     with partial dates of birth. That is personal data under UK GDPR and is
 *     discarded before it reaches the database.
 *
 * Companies House data is Open Government Licence v3.0. Attribution is a
 * condition of use and the brand page carries it.
 */

if (PHP_SAPI !== "cli")
{
    http_response_code(404);
    exit;
}

require_once(__DIR__ . "/../includes/constants.php");
require_once(__DIR__ . "/../includes/functions.php");
require_once(__DIR__ . "/../includes/import_companies.php");

error_reporting(E_ALL);
ini_set("display_errors", "1");

// ---------------------------------------------------------------- options

$options = getopt("", [
    "contact::", "api-key::", "owner::", "limit::", "delay::",
    "recheck", "apply", "dump", "help",
]);

if (isset($options["help"]))
{
    fwrite(STDOUT, <<<TXT
Usage: php bin/import-companies-house.php --contact=you@example.com [options]

  --contact=EMAIL   Required. Identifies this client in the User-Agent.
  --api-key=KEY     Companies House REST key. Prefer the CH_API_KEY env var
                    so the key does not land in your shell history.
  --owner=NAME      Resolve a single owner instead of every unmatched one.
  --limit=N         Stop after N owners.
  --delay=SECONDS   Pause between requests (default 0.6). The published limit
                    is 600 requests per 5 minutes.
  --recheck         Also revisit owners already marked ambiguous.
  --dump            Print the raw search response for the first owner and exit.
  --apply           Actually write. Without it, this is a dry run.

Only an exact, unique, active name match is linked automatically. Everything
else is recorded as a candidate for you to confirm in /admin/owners.php

TXT);
    exit(0);
}

$contact = trim((string) ($options["contact"] ?? getenv("IMPORT_CONTACT") ?: ""));
if ($contact === "")
{
    fwrite(STDERR, "Refusing to run without --contact=EMAIL.\n");
    exit(1);
}

$api_key = trim((string) ($options["api-key"] ?? getenv("CH_API_KEY") ?: ""));
if ($api_key === "")
{
    fwrite(STDERR, "No API key. Set CH_API_KEY or pass --api-key=KEY.\n");
    fwrite(STDERR, "Register free at https://developer.company-information.service.gov.uk/\n");
    exit(1);
}

$owner_filter = trim((string) ($options["owner"] ?? ""));
$limit   = (int) ($options["limit"] ?? 0);
$delay   = (float) ($options["delay"] ?? 0.6);
$recheck = isset($options["recheck"]);
$apply   = isset($options["apply"]);
$dump    = isset($options["dump"]);

$agent = "EthicalBuy-import/0.1 (+" . SITE_URL . "; " . $contact . ")";

// ---------------------------------------------------------------- fetching

function ch_get($path, $agent, $api_key, $delay)
{
    static $last = 0.0;

    // stay under the published rate limit without a fixed sleep per call
    $wait = ($last + $delay) - microtime(true);
    if ($wait > 0)
    {
        usleep((int) ($wait * 1000000));
    }
    $last = microtime(true);

    $ch = curl_init(CH_API . $path);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_USERAGENT      => $agent,
        // Companies House uses Basic auth with the key as the username
        // and an empty password.
        CURLOPT_USERPWD        => $api_key . ":",
        CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER     => ["Accept: application/json"],
    ]);

    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false)
    {
        return [null, "network error: $error"];
    }

    if ($status === 401 || $status === 403)
    {
        return [null, "HTTP $status -- check CH_API_KEY"];
    }

    if ($status === 429)
    {
        return [null, "HTTP 429 rate limited -- raise --delay and retry"];
    }

    if ($status === 404)
    {
        return [null, "not found"];
    }

    if ($status !== 200)
    {
        return [null, "HTTP $status"];
    }

    $decoded = json_decode($body, true);

    return is_array($decoded) ? [$decoded, null] : [null, "response was not JSON"];
}

// ---------------------------------------------------------------- writing

function store_candidates($owner_id, $candidates, $apply)
{
    if (!$apply)
    {
        return;
    }

    query("DELETE FROM owner_company_candidates WHERE owner_id = ?", $owner_id);

    foreach (array_slice($candidates, 0, 5) as $c)
    {
        query(
            "INSERT INTO owner_company_candidates
                 (owner_id, company_number, company_name, company_status, address_snippet, score)
             VALUES (?, ?, ?, ?, ?, ?)",
            $owner_id, $c["company_number"], $c["company_name"],
            $c["company_status"], $c["address_snippet"], $c["score"]
        );
    }
}

function mark_ambiguous($owner_id, $note, $apply)
{
    if (!$apply)
    {
        return;
    }

    query(
        "UPDATE owners SET match_status = 'ambiguous', match_note = ?, retrieved_at = NOW()
          WHERE id = ?",
        mb_substr($note, 0, 255), $owner_id
    );
}

function confirm_match($owner_id, $profile, $parent, $apply)
{
    if (!$apply)
    {
        return true;
    }

    $ok = query(
        "UPDATE owners
            SET company_number = ?, company_name = ?, company_status = ?,
                incorporated_on = ?, parent_company_name = ?, parent_company_number = ?,
                match_status = 'confirmed', match_note = NULL,
                source = ?, source_url = ?, source_licence = ?, retrieved_at = NOW()
          WHERE id = ?",
        $profile["company_number"], $profile["company_name"], $profile["company_status"],
        $profile["incorporated_on"],
        $parent["name"] ?? null, $parent["number"] ?? null,
        $profile["source"], $profile["source_url"], $profile["source_licence"],
        $owner_id
    );

    if ($ok !== false && $apply)
    {
        query("DELETE FROM owner_company_candidates WHERE owner_id = ?", $owner_id);
    }

    return $ok !== false;
}

// ---------------------------------------------------------------- run

fwrite(STDOUT, ($apply ? "APPLYING" : "DRY RUN (pass --apply to write)") . "\n");
fwrite(STDOUT, "source: Companies House (" . CH_LICENCE . ")\n\n");

if ($owner_filter !== "")
{
    $owners = query("SELECT id, name FROM owners WHERE name = ? ORDER BY name", $owner_filter);
}
else if ($recheck)
{
    $owners = query("SELECT id, name FROM owners WHERE match_status <> 'confirmed' ORDER BY name");
}
else
{
    $owners = query("SELECT id, name FROM owners WHERE match_status = 'unmatched' ORDER BY name");
}

if ($owners === false)
{
    fwrite(STDERR, "Could not read owners. Has migrations/003_owner_companies.sql been applied?\n");
    exit(1);
}

if (!$owners)
{
    fwrite(STDOUT, "Nothing to do. Use --recheck to revisit ambiguous owners.\n");
    exit(0);
}

if ($limit > 0)
{
    $owners = array_slice($owners, 0, $limit);
}

$stats = ["confirmed" => 0, "ambiguous" => 0, "failed" => 0];

foreach ($owners as $owner)
{
    fwrite(STDOUT, $owner["name"] . "\n");

    [$search, $error] = ch_get(
        "/search/companies?" . http_build_query(["q" => $owner["name"], "items_per_page" => 20]),
        $agent, $api_key, $delay
    );

    if ($error !== null)
    {
        fwrite(STDOUT, "  ! search failed: $error\n");
        $stats["failed"]++;

        // an auth or rate-limit problem will affect every remaining owner
        if (strpos($error, "CH_API_KEY") !== false || strpos($error, "429") !== false)
        {
            fwrite(STDERR, "\nStopping: this will affect every remaining owner.\n");
            exit(1);
        }

        continue;
    }

    if ($dump)
    {
        fwrite(STDOUT, "\n--- raw search response ---\n");
        fwrite(STDOUT, json_encode($search, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        exit(0);
    }

    $candidates = rank_company_candidates($search["items"] ?? [], $owner["name"]);
    [$match, $status, $reason] = choose_company_match($owner["name"], $candidates);

    if ($status !== "confirmed")
    {
        fwrite(STDOUT, "  ? $reason\n");

        foreach (array_slice($candidates, 0, 3) as $c)
        {
            fwrite(STDOUT, sprintf("      %3d%%  %-45s %s  %s\n",
                $c["score"], mb_substr($c["company_name"], 0, 45),
                $c["company_number"], $c["company_status"] ?? "?"));
        }

        store_candidates($owner["id"], $candidates, $apply);
        mark_ambiguous($owner["id"], $reason, $apply);
        $stats["ambiguous"]++;
        continue;
    }

    // confirmed: fetch the authoritative profile
    [$profile_raw, $error] = ch_get("/company/" . rawurlencode($match["company_number"]),
        $agent, $api_key, $delay);

    if ($error !== null)
    {
        fwrite(STDOUT, "  ! profile failed: $error\n");
        $stats["failed"]++;
        continue;
    }

    $profile = map_company_profile($profile_raw);

    if ($profile === null)
    {
        fwrite(STDOUT, "  ! profile could not be mapped\n");
        $stats["failed"]++;
        continue;
    }

    // corporate parent, if the PSC register names one
    $parent = [];
    [$psc_raw, $psc_error] = ch_get(
        "/company/" . rawurlencode($match["company_number"]) . "/persons-with-significant-control",
        $agent, $api_key, $delay
    );

    if ($psc_error === null)
    {
        $parents = map_corporate_pscs($psc_raw);
        $parent = $parents[0] ?? [];
    }

    if (!confirm_match($owner["id"], $profile, $parent, $apply))
    {
        fwrite(STDOUT, "  ! could not save\n");
        $stats["failed"]++;
        continue;
    }

    fwrite(STDOUT, sprintf("  = %s (%s, %s)%s\n",
        $profile["company_name"], $profile["company_number"], $profile["company_status"],
        isset($parent["name"]) ? "  parent: " . $parent["name"] : ""));

    $stats["confirmed"]++;
}

fwrite(STDOUT, "\n");
foreach ($stats as $key => $count)
{
    fwrite(STDOUT, sprintf("  %-9s %d\n", $key, $count));
}

if (!$apply)
{
    fwrite(STDOUT, "\nNothing was written. Re-run with --apply.\n");
}
else if ($stats["ambiguous"] > 0)
{
    fwrite(STDOUT, "\nConfirm the ambiguous ones at /admin/owners.php\n");
}

exit($stats["failed"] > 0 ? 1 : 0);
