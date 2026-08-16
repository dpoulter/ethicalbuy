<?php

/**
 * functions.php
 *
 * Helper functions.
 */

require_once(__DIR__ . "/constants.php");

define("TEMPLATE_DIR", realpath(__DIR__ . "/../templates"));

/**
 * Escapes a value for safe output in HTML.
 */
function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

/**
 * Encodes a value as JSON that is safe to embed inside a <script> block.
 */
function json_for_html($value)
{
    return json_encode(
        $value,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
}

/**
 * Apologizes to user with message.
 *
 * Must be called before any output has been sent, since it renders a
 * complete page of its own.
 */
function apologize($message)
{
    render("apology.php", ["title" => "Sorry", "message" => $message]);
    exit;
}

/**
 * Facilitates debugging by dumping contents of variable to the browser.
 * Only does anything when APP_DEBUG=1.
 */
function dump($variable)
{
    if (!DEBUG)
    {
        return;
    }

    echo "<pre>" . e(print_r($variable, true)) . "</pre>";
    exit;
}

/**
 * Logs out current user, if any.  Based on Example #1 at
 * http://us.php.net/manual/en/function.session-destroy.php.
 */
function logout()
{
    // unset any session variables
    $_SESSION = [];

    // expire cookie
    if (!empty($_COOKIE[session_name()]))
    {
        setcookie(session_name(), "", time() - 42000);
    }

    // destroy session
    session_destroy();
}

/**
 * Executes SQL statement, possibly with parameters, returning
 * an array of all rows in result set or false on (non-fatal) error.
 *
 * Every value interpolated into a statement must be passed as a
 * parameter -- never concatenated into $sql.
 */
function query(/* $sql [, ... ] */)
{
    // SQL statement
    $sql = func_get_arg(0);

    // parameters, if any
    $parameters = array_slice(func_get_args(), 1);

    // try to connect to database, once per request
    static $handle;
    static $connected = false;
    if (!$connected)
    {
        $connected = true;

        try
        {
            // connect to database
            $handle = new PDO(
                "mysql:dbname=" . DATABASE . ";host=" . SERVER . ";charset=utf8mb4",
                USERNAME,
                PASSWORD,
                [
                    // surface errors instead of returning false silently
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    // ensure that PDO::prepare returns false when passed invalid SQL
                    PDO::ATTR_EMULATE_PREPARES => false,

                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        }
        catch (PDOException $e)
        {
            // log the detail, tell the visitor nothing
            error_log("Database connection failed: " . $e->getMessage());
            $handle = null;
            return false;
        }
    }

    if ($handle === null)
    {
        return false;
    }

    try
    {
        $statement = $handle->prepare($sql);
        $statement->execute($parameters);
        return $statement->fetchAll();
    }
    catch (PDOException $e)
    {
        error_log("Query failed: " . $e->getMessage() . " -- SQL: " . $sql);
        return false;
    }
}

/**
 * Redirects user to destination, which can be
 * a URL or a relative path on the local host.
 *
 * Because this function outputs an HTTP header, it
 * must be called before caller outputs any HTML.
 */
function redirect($destination)
{
    // handle absolute URL
    if (preg_match("/^https?:\/\//", $destination))
    {
        header("Location: " . $destination);
    }

    // handle absolute path -- built from SITE_URL, never from the
    // client-supplied Host header
    else if (preg_match("/^\//", $destination))
    {
        header("Location: " . rtrim(SITE_URL, "/") . $destination);
    }

    // handle relative path
    else
    {
        $path = rtrim(dirname($_SERVER["PHP_SELF"]), "/\\");
        header("Location: " . rtrim(SITE_URL, "/") . "$path/$destination");
    }

    // exit immediately since we're redirecting anyway
    exit;
}

/**
 * Renders a template inside the site's header and footer, passing in values.
 *
 * Templates are fragments: only header.php opens the HTML document and only
 * footer.php closes it.
 */
function render($template, $values = [])
{
    // resolve the template and confirm it really is inside templates/
    $path = realpath(TEMPLATE_DIR . "/" . $template);
    if ($path === false || strpos($path, TEMPLATE_DIR . DIRECTORY_SEPARATOR) !== 0)
    {
        error_log("Invalid template: $template");
        http_response_code(500);
        exit("Server is not configured correctly.");
    }

    // extract variables into local scope
    extract($values);

    require(TEMPLATE_DIR . "/header.php");
    require($path);
    require(TEMPLATE_DIR . "/footer.php");
}

/**
 * Returns the Bootstrap table/list class for an ethical rating,
 * or "" when the product has not been rated.
 */
function rating_class($rating)
{
    if ($rating === null || $rating === "" || !is_numeric($rating))
    {
        return "";
    }

    $rating = (float) $rating;

    if ($rating <= 3) return "danger";
    if ($rating < 5) return "warning";
    if ($rating <= 6) return "secondary";
    if ($rating <= 8) return "primary";
    return "success";
}

/**
 * Insert message into log table
 */
function write_log($module, $text)
{
    query("INSERT INTO message_log (module, message_text) VALUES (?, ?)", $module, $text);
}

/**
 * Log job
 */
function log_job($job_name)
{
    query("INSERT INTO jobs (job_name) VALUES (?)", $job_name);
}

/**
 * Search brands by category. Returns rows, or false on error.
 */
function search_categories($search_string)
{
    return query(
        "SELECT brand, category, type, owner, notes, availability, rating
           FROM brand_v
          WHERE UPPER(category) LIKE UPPER(?)",
        "%" . like_escape($search_string) . "%"
    );
}

/**
 * Search brands by name. Returns rows, or false on error.
 */
function search_brands($search_string)
{
    return query(
        "SELECT brand, category, type, owner, notes, availability, rating
           FROM brand_v
          WHERE UPPER(brand) LIKE UPPER(?)",
        "%" . like_escape($search_string) . "%"
    );
}

/**
 * Get every brand, for the category browser. Returns rows, or false on error.
 */
function get_categories()
{
    return query(
        "SELECT brand, category, type, owner, notes, availability, rating
           FROM brand_v
          ORDER BY category, brand"
    );
}

/**
 * Escapes LIKE wildcards so that a user searching for "50%" does not
 * match everything.
 */
function like_escape($value)
{
    return str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], (string) $value);
}
