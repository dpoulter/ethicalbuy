<?php

/**
 * contact.php
 *
 * The contact form's defences: honeypot, minimum time to submit, strict
 * plain-text validation, per-visitor limits and optional Cloudflare
 * Turnstile. Nothing here sends email; if that is ever added, the
 * CR/LF checks below already keep header injection out of name and email.
 */

// what a "Report a problem" link may say it is about
const CONTACT_ABOUT = ["brand" => "brand", "product" => "product"];

const CONTACT_NAME_MAX = 100;
const CONTACT_EMAIL_MAX = 254;
const CONTACT_MESSAGE_MAX = 5000;
const CONTACT_ITEM_MAX = 150;

// the honeypot: a field people never see and bots fill in
const CONTACT_HONEYPOT = "website";

/**
 * True if Turnstile is configured. It is used only when both keys are set.
 */
function turnstile_enabled()
{
    return env("TURNSTILE_SITE_KEY", "") !== "" && env("TURNSTILE_SECRET", "") !== "";
}

/**
 * Asks Cloudflare whether a Turnstile token is good. Fails closed: no token,
 * a network error or an unexpected answer all count as a failure.
 */
function turnstile_verify($token)
{
    if (!is_string($token) || $token === "" || strlen($token) > 2048)
    {
        return false;
    }

    $context = stream_context_create(["http" => [
        "method" => "POST",
        "header" => "Content-Type: application/x-www-form-urlencoded\r\n",
        "content" => http_build_query([
            "secret" => env("TURNSTILE_SECRET", ""),
            "response" => $token,
            "remoteip" => client_ip(),
        ]),
        "timeout" => 5,
        "ignore_errors" => true,
    ]]);

    $raw = @file_get_contents("https://challenges.cloudflare.com/turnstile/v0/siteverify", false, $context);
    $answer = is_string($raw) ? json_decode($raw, true) : null;

    if (!is_array($answer) || ($answer["success"] ?? false) !== true)
    {
        error_log("Turnstile verification failed");
        return false;
    }

    return true;
}

/**
 * A signed timestamp for the form, so we can tell how long the visitor took
 * to fill it in without trusting the browser. Signed with the session's CSRF
 * token, so it cannot be forged or carried to another session.
 */
function contact_form_stamp($now = null)
{
    $now = $now ?? time();

    return $now . "." . hash_hmac("sha256", "contact|" . $now, csrf_token());
}

/**
 * Seconds since the form was served, or null if the stamp is missing,
 * forged or older than a day.
 */
function contact_form_age($stamp, $now = null)
{
    $now = $now ?? time();

    if (!is_string($stamp) || !preg_match('/^(\d{10})\.([0-9a-f]{64})$/', $stamp, $m))
    {
        return null;
    }

    if (!hash_equals(hash_hmac("sha256", "contact|" . $m[1], csrf_token()), $m[2]))
    {
        return null;
    }

    $age = $now - (int) $m[1];

    return ($age < 0 || $age > 86400) ? null : $age;
}

/**
 * True if the text holds control characters other than tab and newline.
 */
function has_control_chars($text, $allow_newlines = false)
{
    $pattern = $allow_newlines ? '/[\x00-\x08\x0B-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';

    return preg_match($pattern, $text) === 1 || preg_match('//u', $text) !== 1;
}

/**
 * Cleans a "Report a problem" item name taken from the query string.
 * Returns the item, or "" if it is unusable.
 */
function contact_item($item)
{
    $item = trim((string) $item);

    if ($item === "" || mb_strlen($item) > CONTACT_ITEM_MAX || has_control_chars($item))
    {
        return "";
    }

    return $item;
}

/**
 * Validates a submission. $values holds name, email, message, about, item.
 * Returns [errors, cleaned values]. Errors with string keys belong to a
 * field; the rest are general.
 */
function validate_contact($values)
{
    $errors = [];

    $name = trim((string) ($values["name"] ?? ""));
    $email = trim((string) ($values["email"] ?? ""));
    // plain text: normalise line endings, nothing else is interpreted
    $message = trim(str_replace(["\r\n", "\r"], "\n", (string) ($values["message"] ?? "")));

    if ($name === "")
    {
        $errors["name"] = "Please tell us your name.";
    }
    else if (mb_strlen($name) > CONTACT_NAME_MAX)
    {
        $errors["name"] = "Please keep your name under " . CONTACT_NAME_MAX . " characters.";
    }
    else if (has_control_chars($name))
    {
        // includes CR and LF, which is what email header injection needs
        $errors["name"] = "Please use only ordinary characters in your name.";
    }

    if ($email === "")
    {
        $errors["email"] = "Please give us an email address so we can reply.";
    }
    else if (strlen($email) > CONTACT_EMAIL_MAX || has_control_chars($email)
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false)
    {
        $errors["email"] = "That doesn't look like a valid email address.";
    }

    if ($message === "")
    {
        $errors["message"] = "Please write a message.";
    }
    else if (mb_strlen($message) > CONTACT_MESSAGE_MAX)
    {
        $errors["message"] = "Please keep your message under " . CONTACT_MESSAGE_MAX . " characters.";
    }
    else if (has_control_chars($message, true))
    {
        $errors["message"] = "Your message contains characters we can't accept. Please write it as plain text.";
    }

    $about = CONTACT_ABOUT[(string) ($values["about"] ?? "")] ?? "";
    $item = $about === "" ? "" : contact_item($values["item"] ?? "");

    return [$errors, [
        "name" => $name,
        "email" => $email,
        "message" => $message,
        "about" => $item === "" ? "" : $about,
        "item" => $item,
    ]];
}

/**
 * The message as stored: a problem report starts with what it is about.
 */
function contact_stored_message($clean)
{
    if ($clean["about"] !== "" && $clean["item"] !== "")
    {
        return "Problem report about " . $clean["about"] . ": " . $clean["item"] . "\n\n" . $clean["message"];
    }

    return $clean["message"];
}

/**
 * The contact limits as [limit, seconds] rules.
 */
function contact_rate_rules()
{
    return [
        [rate_setting("RATE_CONTACT_PER_HOUR", 3), 3600],
        [rate_setting("RATE_CONTACT_PER_DAY", 10), 86400],
    ];
}
