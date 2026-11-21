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
$flash = "";

if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    if (!csrf_valid(input_string($_POST, "csrf_token")))
    {
        $errors[] = "Your session expired. Please save again.";
    }
    else
    {
        $errors = admin_save_brand_scores($id, $_POST);

        if (!$errors)
        {
            write_log("admin", "updated dimension scores for brand: " . $brand["name"]);
            redirect("scores.php?id=$id&saved=1");
        }
    }
}

if (isset($_GET["saved"]))
{
    $flash = "Scores saved.";
}

render("admin/scores.php", [
    "title" => "Scores: " . $brand["name"],
    "brand" => $brand,
    "scores" => get_brand_scores($id),
    "errors" => $errors,
    "flash" => $flash,
]);
