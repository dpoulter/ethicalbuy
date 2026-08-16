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

    // handle absolute path -- sent as-is. A relative Location is valid per
    // RFC 7231, so we never have to trust the client-supplied Host header.
    else if (preg_match("/^\//", $destination))
    {
        header("Location: " . $destination);
    }

    // handle relative path, resolved against the current directory
    else
    {
        $path = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/\\");
        header("Location: $path/$destination");
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
 * The rating bands, in display order. Used by the legend so the key on
 * screen can never drift from rating_class().
 */
function rating_bands()
{
    return [
        ["class" => "success",   "range" => "9 - 10", "label" => "Excellent", "blurb" => "Strong ethical record across the board."],
        ["class" => "primary",   "range" => "7 - 8",  "label" => "Good",      "blurb" => "Generally good practice, minor concerns."],
        ["class" => "secondary", "range" => "5 - 6",  "label" => "Mixed",     "blurb" => "Some good practice, some real concerns."],
        ["class" => "warning",   "range" => "4",      "label" => "Poor",      "blurb" => "Significant concerns on several counts."],
        ["class" => "danger",    "range" => "1 - 3",  "label" => "Avoid",     "blurb" => "Serious, well-documented ethical problems."],
        ["class" => "",          "range" => "-",      "label" => "Not rated", "blurb" => "We have not assessed this brand yet."],
    ];
}

/**
 * Short human label for a rating, e.g. "Good".
 */
function rating_label($rating)
{
    $class = rating_class($rating);

    foreach (rating_bands() as $band)
    {
        if ($band["class"] === $class)
        {
            return $band["label"];
        }
    }

    return "Not rated";
}

/**
 * Formats a rating for display, e.g. "8/10" or "Not rated".
 */
function rating_score($rating)
{
    if ($rating === null || $rating === "" || !is_numeric($rating))
    {
        return "Not rated";
    }

    // show 7 rather than 7.0, but keep 7.5 intact
    $rating = (float) $rating;
    $formatted = ($rating == (int) $rating) ? (string) (int) $rating : (string) $rating;

    return "$formatted/10";
}

/**
 * Reads a string from $_GET/$_POST, defending against array-valued input
 * such as ?field[]=x, which would otherwise raise a conversion notice.
 */
function input_string(array $source, $key, $default = "")
{
    $value = $source[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/**
 * Returns this session's CSRF token, creating one if needed.
 */
function csrf_token()
{
    if (empty($_SESSION["csrf_token"]))
    {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

/**
 * Renders the hidden CSRF field for a form.
 */
function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * True if the submitted token matches this session's.
 */
function csrf_valid($token)
{
    return !empty($_SESSION["csrf_token"])
        && is_string($token)
        && hash_equals($_SESSION["csrf_token"], $token);
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

// the only columns any page reads, and the only fields/sorts a request may name
define("BRAND_COLUMNS", "brand, category, type, owner, notes, availability, rating");

const SEARCH_FIELDS = ["brand", "category", "type", "owner"];

const SEARCH_SORTS = [
    "relevance"   => "brand ASC",
    "brand"       => "brand ASC",
    "category"    => "category ASC, brand ASC",
    "rating_desc" => "rating IS NULL, rating DESC, brand ASC",
    "rating_asc"  => "rating IS NULL, rating ASC, brand ASC",
];

/**
 * Searches brands with optional filters. Returns rows, or false on error.
 *
 * $options:
 *   term         string  text to match (empty matches everything)
 *   field        string  one of SEARCH_FIELDS; anything else falls back to brand
 *   min_rating   string  only return brands rated at least this
 *   max_rating   string  only return brands rated at most this
 *   availability string  exact availability match
 *   sort         string  a key of SEARCH_SORTS
 *
 * $options never reaches the SQL string directly: field and sort are resolved
 * against the whitelists above, everything else is bound as a parameter.
 */
function search_brands($options = [])
{
    [$sql, $params] = build_brand_search($options);

    return query($sql, ...$params);
}

/**
 * Builds the SQL and bound parameters for search_brands().
 *
 * Split out from search_brands() so the whitelisting can be tested without
 * a database. Returns [$sql, $params].
 */
function build_brand_search($options = [])
{
    $field = in_array($options["field"] ?? "", SEARCH_FIELDS, true)
        ? $options["field"]
        : "brand";

    $order = SEARCH_SORTS[$options["sort"] ?? ""] ?? SEARCH_SORTS["brand"];

    $where = [];
    $params = [];

    $term = trim((string) ($options["term"] ?? ""));
    if ($term !== "")
    {
        $where[] = "UPPER($field) LIKE UPPER(?)";
        $params[] = "%" . like_escape($term) . "%";
    }

    if (is_numeric($options["min_rating"] ?? null))
    {
        $where[] = "rating >= ?";
        $params[] = (float) $options["min_rating"];
    }

    if (is_numeric($options["max_rating"] ?? null))
    {
        $where[] = "rating <= ?";
        $params[] = (float) $options["max_rating"];
    }

    $availability = trim((string) ($options["availability"] ?? ""));
    if ($availability !== "")
    {
        $where[] = "availability = ?";
        $params[] = $availability;
    }

    $sql = "SELECT " . BRAND_COLUMNS . " FROM brand_v";
    if ($where)
    {
        $sql .= " WHERE " . implode(" AND ", $where);
    }
    $sql .= " ORDER BY $order";

    return [$sql, $params];
}

/**
 * Get every brand, for the category browser. Returns rows, or false on error.
 */
function get_categories()
{
    return query(
        "SELECT " . BRAND_COLUMNS . "
           FROM brand_v
          ORDER BY category, brand"
    );
}

/**
 * Get a single brand by name. Returns a row, null if not found,
 * or false on error.
 */
function get_brand($brand)
{
    $rows = query(
        "SELECT " . BRAND_COLUMNS . "
           FROM brand_v
          WHERE brand = ?
          LIMIT 1",
        $brand
    );

    if ($rows === false)
    {
        return false;
    }

    return $rows[0] ?? null;
}

/**
 * Get every brand in a category, for the "more like this" list on a
 * brand page. Returns rows, or false on error.
 */
function get_brands_in_category($category, $exclude_brand = null)
{
    return query(
        "SELECT " . BRAND_COLUMNS . "
           FROM brand_v
          WHERE category <=> ?
            AND (? IS NULL OR brand <> ?)
          ORDER BY rating IS NULL, rating DESC, brand",
        $category,
        $exclude_brand,
        $exclude_brand
    );
}

/**
 * Distinct availability values, for the search filter dropdown.
 * Read from the data rather than hardcoded. Returns a list of strings.
 */
function get_availability_options()
{
    $rows = query(
        "SELECT DISTINCT availability
           FROM brand_v
          WHERE availability IS NOT NULL AND availability <> ''
          ORDER BY availability"
    );

    return $rows === false ? [] : array_column($rows, "availability");
}

/**
 * Records a contact form submission. Returns true on success.
 */
function save_contact_message($name, $email, $message)
{
    $result = query(
        "INSERT INTO contact_messages (name, email, message) VALUES (?, ?, ?)",
        $name,
        $email,
        $message
    );

    return $result !== false;
}

/**
 * Escapes LIKE wildcards so that a user searching for "50%" does not
 * match everything.
 */
function like_escape($value)
{
    return str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], (string) $value);
}
