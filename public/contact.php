<?php

require_once(__DIR__ . "/../includes/config.php");

$values = ["name" => "", "email" => "", "message" => ""];
$errors = [];
$sent = isset($_GET["sent"]);

if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    $sent = false;

    if (!csrf_valid(input_string($_POST, "csrf_token")))
    {
        // usually a stale form after the session expired
        $errors[] = "Your session expired. Please try sending the message again.";
    }

    $values["name"] = input_string($_POST, "name");
    $values["email"] = input_string($_POST, "email");
    $values["message"] = input_string($_POST, "message");

    if ($values["name"] === "")
    {
        $errors["name"] = "Please tell us your name.";
    }
    else if (mb_strlen($values["name"]) > 100)
    {
        $errors["name"] = "Please keep your name under 100 characters.";
    }

    if ($values["email"] === "")
    {
        $errors["email"] = "Please give us an email address so we can reply.";
    }
    else if (!filter_var($values["email"], FILTER_VALIDATE_EMAIL) || mb_strlen($values["email"]) > 255)
    {
        $errors["email"] = "That doesn't look like a valid email address.";
    }

    if ($values["message"] === "")
    {
        $errors["message"] = "Please write a message.";
    }
    else if (mb_strlen($values["message"]) > 5000)
    {
        $errors["message"] = "Please keep your message under 5000 characters.";
    }

    if (!$errors)
    {
        if (save_contact_message($values["name"], $values["email"], $values["message"]))
        {
            // post/redirect/get, so a refresh doesn't resend the message
            redirect("contact.php?sent=1");
        }

        $errors[] = "We couldn't save your message just now. Please try again shortly.";
    }
}

render("contact.php", [
    "title" => "Contact",
    "values" => $values,
    "errors" => $errors,
    "sent" => $sent,
]);
