<?php

/**
 * legal.php
 *
 * Values the legal and trust pages need. Who runs the site is configuration,
 * never invented: when CONTROLLER_NAME or CONTACT_EMAIL is unset the pages
 * show a loud placeholder so it cannot be missed before launch.
 */

// change when the privacy policy or terms change in substance
const LEGAL_LAST_UPDATED = "2026-11-24";

/**
 * The data controller's name, or null if CONTROLLER_NAME is unset.
 */
function controller_name()
{
    $name = trim((string) env("CONTROLLER_NAME", ""));

    return $name === "" ? null : $name;
}

/**
 * The contact email for privacy and corrections, or null if CONTACT_EMAIL is
 * unset or not an email address.
 */
function contact_email()
{
    $email = trim((string) env("CONTACT_EMAIL", ""));

    return filter_var($email, FILTER_VALIDATE_EMAIL) === false ? null : $email;
}

/**
 * HTML for a configured value, or the placeholder naming the variable to set.
 */
function legal_value($value, $variable)
{
    if ($value !== null)
    {
        return e($value);
    }

    return '<mark class="config-missing">[not set: ' . e($variable) . ']</mark>';
}

/**
 * HTML for the contact email as a link, or the placeholder.
 */
function legal_email_link()
{
    $email = contact_email();

    return $email === null
        ? legal_value(null, "CONTACT_EMAIL")
        : '<a href="mailto:' . e($email) . '">' . e($email) . '</a>';
}

/**
 * Link to the contact form pre-filled to report a problem with a brand or
 * product. The item is URL-encoded here and validated again on arrival.
 */
function report_problem_url($about, $item)
{
    return "/contact.php?" . http_build_query(["about" => $about, "item" => (string) $item], "", "&", PHP_QUERY_RFC3986);
}

/**
 * A date such as "24 November 2026" from a DATETIME string, or "".
 */
function long_date($value)
{
    $time = $value ? strtotime((string) $value) : false;

    return $time === false ? "" : date("j F Y", $time);
}

/**
 * The newest of several DATETIME strings (nulls ignored), or null.
 */
function newest_date(...$dates)
{
    $dates = array_filter($dates, function ($d) { return is_string($d) && $d !== "" && strtotime($d) !== false; });

    if (!$dates)
    {
        return null;
    }

    usort($dates, function ($a, $b) { return strtotime($b) <=> strtotime($a); });

    return $dates[0];
}

/**
 * When anything we hold about a brand was last fetched: the newest
 * retrieved_at across the brand, its scores and its products. Null if none.
 */
function brand_last_updated($brand_id)
{
    $rows = query(
        "SELECT (SELECT MAX(retrieved_at) FROM brand_scores WHERE brand_id = ?) AS scores,
                (SELECT MAX(retrieved_at) FROM products WHERE brand_id = ?) AS products",
        $brand_id, $brand_id
    );

    return $rows === false || !$rows ? null : newest_date($rows[0]["scores"], $rows[0]["products"]);
}
