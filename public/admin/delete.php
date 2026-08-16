<?php

require_once(__DIR__ . "/../../includes/config.php");

require_admin();

$raw_id = input_string($_SERVER["REQUEST_METHOD"] === "POST" ? $_POST : $_GET, "id");

if (!ctype_digit($raw_id))
{
    apologize("No brand was specified.");
}

$id = (int) $raw_id;
$brand = admin_get_brand($id);

if ($brand === false)
{
    apologize("We could not load that brand just now. Please try again shortly.");
}

if ($brand === null)
{
    http_response_code(404);
    apologize("There is no brand with that id.");
}

$errors = [];

// deletion only ever happens on POST, with a valid token
if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    if (!csrf_valid(input_string($_POST, "csrf_token")))
    {
        $errors[] = "Your session expired. Please confirm again.";
    }
    else if (admin_delete_brand($id))
    {
        write_log("admin", "deleted brand: " . $brand["name"]);
        redirect("index.php?deleted=1");
    }
    else
    {
        $errors[] = "We couldn't delete that just now. Please try again shortly.";
    }
}

render("admin/delete.php", [
    "title" => "Delete Brand",
    "brand" => $brand,
    "errors" => $errors,
]);
