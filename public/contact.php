<?php

require_once(__DIR__ . "/../includes/config.php");

// the form holds a per-session token, so it must never be cached
header("Cache-Control: no-store");

$values = ["name" => "", "email" => "", "message" => "", "about" => "", "item" => ""];
$errors = [];
$sent = isset($_GET["sent"]);

// "Report a problem with this brand/product" links pre-fill what it is about
$about = CONTACT_ABOUT[input_string($_GET, "about")] ?? "";
if ($about !== "")
{
    $item = contact_item(input_string($_GET, "item"));
    if ($item !== "")
    {
        $values["about"] = $about;
        $values["item"] = $item;
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST")
{
    $sent = false;

    // over the limit: stop before doing any work at all
    $wait = rate_wait("contact", contact_rate_rules());
    if ($wait > 0)
    {
        show_error_page(429, "You've sent us several messages recently. Thank you. "
            . "Please wait a while before sending another.", $wait);
    }

    foreach (array_keys($values) as $key)
    {
        $values[$key] = input_string($_POST, $key);
    }

    if (!csrf_valid(input_string($_POST, "csrf_token")))
    {
        // usually a stale form after the session expired
        $errors[] = "Your session expired. Please try sending the message again.";
    }
    else
    {
        $age = contact_form_age(input_string($_POST, "form_stamp"));
        $bot = input_string($_POST, CONTACT_HONEYPOT) !== ""
            || $age === null
            || $age < rate_setting("CONTACT_MIN_SECONDS", 3);

        if ($bot)
        {
            // look successful, store nothing, but count it against the limit
            rate_hit("contact");
            write_log("contact", "discarded automated-looking submission from " . truncated_ip(client_ip()));
            redirect("contact.php?sent=1");
        }

        [$errors, $clean] = validate_contact($values);
        $values = $clean;

        if (!$errors && turnstile_enabled() && !turnstile_verify(input_string($_POST, "cf-turnstile-response")))
        {
            $errors[] = "We couldn't confirm you're not an automated program. Please try again.";
        }

        if (!$errors)
        {
            rate_hit("contact");

            if (save_contact_message($clean["name"], $clean["email"], contact_stored_message($clean)))
            {
                write_log("contact", "message received from " . truncated_ip(client_ip()));

                // post/redirect/get, so a refresh doesn't resend the message
                redirect("contact.php?sent=1");
            }

            $errors[] = "We couldn't save your message just now. Please try again shortly.";
        }
    }
}

render("contact.php", [
    "title" => "Contact",
    "description" => "Contact Ethical Buy, or report a problem with a brand or product.",
    "values" => $values,
    "errors" => $errors,
    "sent" => $sent,
    "stamp" => contact_form_stamp(),
    "turnstile_site_key" => turnstile_enabled() ? env("TURNSTILE_SITE_KEY") : null,
]);
