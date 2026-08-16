<?php

require_once(__DIR__ . "/../../includes/config.php");

require_admin();

// no id means "create"
$raw_id = input_string($_GET, "id");
$id = ctype_digit($raw_id) ? (int) $raw_id : null;

$values = [
    "name" => "", "category_id" => "", "owner_id" => "",
    "type" => "", "notes" => "", "availability" => "", "rating" => "",
];

if ($id !== null)
{
    $existing = admin_get_brand($id);

    if ($existing === false)
    {
        apologize("We could not load that brand just now. Please try again shortly.");
    }

    if ($existing === null)
    {
        http_response_code(404);
        apologize("There is no brand with that id.");
    }

    foreach ($values as $key => $_)
    {
        $values[$key] = (string) ($existing[$key] ?? "");
    }
}

$errors = [];

if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    if (!csrf_valid(input_string($_POST, "csrf_token")))
    {
        $errors[] = "Your session expired. Please submit the form again.";
    }

    foreach ($values as $key => $_)
    {
        $values[$key] = input_string($_POST, $key);
    }

    $errors = array_merge($errors, validate_brand($values, $id));

    if (!$errors)
    {
        $saved = ($id === null)
            ? admin_create_brand($values)
            : admin_update_brand($id, $values);

        if ($saved)
        {
            write_log("admin", ($id === null ? "created" : "updated") . " brand: " . $values["name"]);
            redirect("index.php?" . ($id === null ? "created=1" : "updated=1"));
        }

        $errors[] = "We couldn't save that just now. Please try again shortly.";
    }
}

render("admin/form.php", [
    "title" => $id === null ? "New Brand" : "Edit Brand",
    "id" => $id,
    "values" => $values,
    "errors" => $errors,
    "categories" => get_all_categories(),
    "owners" => get_all_owners(),
]);
