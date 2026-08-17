<?php

require_once(__DIR__ . "/../includes/config.php");

$saved = isset($_GET["saved"]);

if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    if (!csrf_valid(input_string($_POST, "csrf_token")))
    {
        apologize("Your session expired. Please set your priorities again.");
    }

    if (input_string($_POST, "action") === "reset")
    {
        clear_weights();
        redirect("priorities.php");
    }

    $weights = [];
    foreach (score_dimensions() as $key => $_)
    {
        $weights[$key] = (int) input_string($_POST, "w_" . $key, (string) WEIGHT_DEFAULT);
    }

    save_weights($weights);
    redirect("priorities.php?saved=1");
}

render("priorities.php", [
    "title" => "Your Priorities",
    "weights" => current_weights(),
    "chosen" => has_chosen_priorities(),
    "saved" => $saved,
]);
