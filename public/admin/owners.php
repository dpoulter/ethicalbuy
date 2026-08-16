<?php

require_once(__DIR__ . "/../../includes/config.php");

require_admin();

$errors = [];
$flash = "";

if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    if (!csrf_valid(input_string($_POST, "csrf_token")))
    {
        $errors[] = "Your session expired. Please try again.";
    }
    else
    {
        $owner_id = input_string($_POST, "owner_id");
        $action = input_string($_POST, "action");

        if (!ctype_digit($owner_id))
        {
            $errors[] = "No owner was specified.";
        }
        else if ($action === "confirm")
        {
            $number = input_string($_POST, "company_number");

            if (admin_confirm_owner_company((int) $owner_id, $number))
            {
                write_log("admin", "confirmed company $number for owner $owner_id");
                redirect("owners.php?confirmed=1");
            }

            $errors[] = "That company is no longer one of the suggestions. Re-run the importer.";
        }
        else if ($action === "clear")
        {
            if (admin_clear_owner_match((int) $owner_id))
            {
                write_log("admin", "cleared company match for owner $owner_id");
                redirect("owners.php?cleared=1");
            }

            $errors[] = "We couldn't clear that just now.";
        }
    }
}

if (isset($_GET["confirmed"])) { $flash = "Company confirmed."; }
else if (isset($_GET["cleared"])) { $flash = "Match cleared."; }

$owners = admin_list_owners();

if ($owners === false)
{
    apologize("We could not load owners. Has migrations/003_owner_companies.sql been applied?");
}

// candidates for anything not yet confirmed
$candidates = [];
foreach ($owners as $owner)
{
    if ($owner["match_status"] !== "confirmed")
    {
        $candidates[$owner["id"]] = admin_owner_candidates($owner["id"]);
    }
}

render("admin/owners.php", [
    "title" => "Owners",
    "owners" => $owners,
    "candidates" => $candidates,
    "errors" => $errors,
    "flash" => $flash,
]);
