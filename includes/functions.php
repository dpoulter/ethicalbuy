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
 * True if this request arrived over TLS.
 *
 * X-Forwarded-Proto is only believed when TRUST_PROXY=1, because any client
 * can send that header when the app is reachable directly.
 */
function is_secure_request()
{
    if (!empty($_SERVER["HTTPS"]) && strtolower($_SERVER["HTTPS"]) !== "off")
    {
        return true;
    }

    if (env("TRUST_PROXY", "0") === "1"
        && strtolower($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? "") === "https")
    {
        return true;
    }

    // local development over plain HTTP
    return in_array($_SERVER["REMOTE_ADDR"] ?? "", ["127.0.0.1", "::1"], true);
}

/**
 * Reads the submitted Basic credentials as [user, password], or [null, null].
 *
 * Falls back to parsing the Authorization header, because under CGI/FastCGI
 * PHP_AUTH_USER is only populated if the web server forwards it.
 */
function basic_auth_credentials()
{
    if (isset($_SERVER["PHP_AUTH_USER"]))
    {
        return [$_SERVER["PHP_AUTH_USER"], $_SERVER["PHP_AUTH_PW"] ?? ""];
    }

    $header = $_SERVER["HTTP_AUTHORIZATION"]
        ?? $_SERVER["REDIRECT_HTTP_AUTHORIZATION"]
        ?? "";

    if (stripos($header, "Basic ") !== 0)
    {
        return [null, null];
    }

    $decoded = base64_decode(substr($header, 6), true);
    if ($decoded === false || strpos($decoded, ":") === false)
    {
        return [null, null];
    }

    return explode(":", $decoded, 2);
}

/**
 * Gate for every admin page. Sends 401 and exits unless valid credentials
 * were supplied. Fails closed: if the app is misconfigured, nobody gets in.
 *
 * Credentials come from ADMIN_USER and ADMIN_PASSWORD_HASH, the latter being
 * a password_hash() digest so no plaintext password is ever stored.
 */
function require_admin()
{
    // Basic credentials travel base64-encoded, which is not encryption
    if (!is_secure_request())
    {
        error_log("Refused admin access over plain HTTP");
        http_response_code(403);
        exit("Admin is only available over HTTPS.");
    }

    $user = env("ADMIN_USER", "");
    $hash = env("ADMIN_PASSWORD_HASH", "");

    if ($user === "" || $hash === "")
    {
        error_log("Admin is not configured: set ADMIN_USER and ADMIN_PASSWORD_HASH");
        http_response_code(500);
        exit("Admin is not configured.");
    }

    [$given_user, $given_pass] = basic_auth_credentials();

    $ok = is_string($given_user)
        && hash_equals($user, $given_user)
        && password_verify((string) $given_pass, $hash);

    if (!$ok)
    {
        if ($given_user !== null)
        {
            error_log("Failed admin login for user: " . $given_user);
        }

        header('WWW-Authenticate: Basic realm="Ethical Buy admin", charset="UTF-8"');
        http_response_code(401);
        exit("Authentication required.");
    }
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

// columns for lists and search
define("BRAND_COLUMNS", "brand, category, type, owner, notes, availability, rating");

// the detail page additionally shows certifications, the owner's registered
// identity, and cites its sources.
// Requires migrations 002_brand_provenance.sql and 003_owner_companies.sql.
define("BRAND_DETAIL_COLUMNS", BRAND_COLUMNS .
    ", certifications, source, source_url, source_licence, retrieved_at" .
    ", owner_company_number, owner_company_name, owner_company_status" .
    ", owner_parent_name, owner_parent_number, owner_source_url");

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
    // "category IS NULL" first keeps uncategorised brands at the end, so the
    // page doesn't open on the least useful group
    return query(
        "SELECT " . BRAND_COLUMNS . "
           FROM brand_v
          ORDER BY category IS NULL, category, brand"
    );
}

/**
 * Get a single brand by name. Returns a row, null if not found,
 * or false on error.
 */
function get_brand($brand)
{
    $rows = query(
        "SELECT " . BRAND_DETAIL_COLUMNS . "
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

/* ------------------------------------------------------------------ *
 * Admin
 *
 * The public site reads brand_v, which is a view and therefore not
 * reliably writable. Everything below targets the base tables instead:
 * brands, categories and owners. If your production schema differs,
 * these are the only functions that need to change.
 * ------------------------------------------------------------------ */

/**
 * Every category, for the admin dropdown. Returns id/name rows.
 */
function get_all_categories()
{
    $rows = query("SELECT id, name FROM categories ORDER BY name");

    return $rows === false ? [] : $rows;
}

/**
 * Every owner, for the admin dropdown. Returns id/name rows.
 */
function get_all_owners()
{
    $rows = query("SELECT id, name FROM owners ORDER BY name");

    return $rows === false ? [] : $rows;
}

/**
 * Brands for the admin list, optionally filtered by name.
 * Returns rows, or false on error.
 */
function admin_list_brands($search = "")
{
    $sql = "SELECT b.id, b.name, b.type, b.availability, b.rating, b.updated_at,
                   c.name AS category, o.name AS owner
              FROM brands b
              LEFT JOIN categories c ON c.id = b.category_id
              LEFT JOIN owners     o ON o.id = b.owner_id";

    $params = [];
    $search = trim($search);
    if ($search !== "")
    {
        $sql .= " WHERE UPPER(b.name) LIKE UPPER(?)";
        $params[] = "%" . like_escape($search) . "%";
    }

    $sql .= " ORDER BY b.name";

    return query($sql, ...$params);
}

/**
 * A single brand by id. Returns a row, null if not found, false on error.
 */
function admin_get_brand($id)
{
    $rows = query(
        "SELECT id, name, category_id, owner_id, type, notes, availability, rating
           FROM brands
          WHERE id = ?",
        $id
    );

    if ($rows === false)
    {
        return false;
    }

    return $rows[0] ?? null;
}

/**
 * True if another brand already uses this name.
 * $exclude_id lets a brand keep its own name while editing.
 */
function brand_name_exists($name, $exclude_id = null)
{
    $rows = query(
        "SELECT id FROM brands WHERE name = ? AND (? IS NULL OR id <> ?) LIMIT 1",
        $name,
        $exclude_id,
        $exclude_id
    );

    return !empty($rows);
}

/**
 * Validates submitted brand fields. Returns an array of errors keyed by
 * field name; empty means valid.
 */
function validate_brand($data, $exclude_id = null)
{
    $errors = [];

    $name = trim((string) ($data["name"] ?? ""));
    if ($name === "")
    {
        $errors["name"] = "A brand name is required.";
    }
    else if (mb_strlen($name) > 150)
    {
        $errors["name"] = "Keep the brand name under 150 characters.";
    }
    else if (brand_name_exists($name, $exclude_id))
    {
        $errors["name"] = "There is already a brand with that name.";
    }

    $rating = trim((string) ($data["rating"] ?? ""));
    if ($rating !== "")
    {
        if (!is_numeric($rating))
        {
            $errors["rating"] = "The rating must be a number between 1 and 10, or left blank.";
        }
        else if ((float) $rating < 1 || (float) $rating > 10)
        {
            $errors["rating"] = "The rating must be between 1 and 10.";
        }
    }

    foreach (["type" => 100, "availability" => 100] as $field => $max)
    {
        if (mb_strlen(trim((string) ($data[$field] ?? ""))) > $max)
        {
            $errors[$field] = "Keep this under $max characters.";
        }
    }

    if (mb_strlen((string) ($data["notes"] ?? "")) > 5000)
    {
        $errors["notes"] = "Keep the notes under 5000 characters.";
    }

    return $errors;
}

/**
 * Normalises a submitted form into the columns brands expects.
 * Empty strings become NULL so the database stores absence, not "".
 */
function brand_params($data)
{
    $blank_to_null = function ($value) {
        $value = trim((string) $value);
        return $value === "" ? null : $value;
    };

    return [
        "name"         => trim((string) ($data["name"] ?? "")),
        "category_id"  => $blank_to_null($data["category_id"] ?? ""),
        "owner_id"     => $blank_to_null($data["owner_id"] ?? ""),
        "type"         => $blank_to_null($data["type"] ?? ""),
        "notes"        => $blank_to_null($data["notes"] ?? ""),
        "availability" => $blank_to_null($data["availability"] ?? ""),
        "rating"       => $blank_to_null($data["rating"] ?? ""),
    ];
}

/**
 * Inserts a brand. Returns true on success.
 */
function admin_create_brand($data)
{
    $p = brand_params($data);

    $result = query(
        "INSERT INTO brands (name, category_id, owner_id, type, notes, availability, rating)
              VALUES (?, ?, ?, ?, ?, ?, ?)",
        $p["name"], $p["category_id"], $p["owner_id"],
        $p["type"], $p["notes"], $p["availability"], $p["rating"]
    );

    return $result !== false;
}

/**
 * Updates a brand. Returns true on success.
 */
function admin_update_brand($id, $data)
{
    $p = brand_params($data);

    $result = query(
        "UPDATE brands
            SET name = ?, category_id = ?, owner_id = ?, type = ?,
                notes = ?, availability = ?, rating = ?
          WHERE id = ?",
        $p["name"], $p["category_id"], $p["owner_id"], $p["type"],
        $p["notes"], $p["availability"], $p["rating"], $id
    );

    return $result !== false;
}

/**
 * Deletes a brand. Returns true on success.
 */
function admin_delete_brand($id)
{
    return query("DELETE FROM brands WHERE id = ?", $id) !== false;
}

/**
 * Owners with their Companies House match state, plus how many candidates
 * are waiting to be confirmed. Requires migrations/003_owner_companies.sql.
 */
function admin_list_owners()
{
    $rows = query(
        "SELECT o.id, o.name, o.company_number, o.company_name, o.company_status,
                o.parent_company_name, o.match_status, o.match_note, o.source_url,
                (SELECT COUNT(*) FROM owner_company_candidates c WHERE c.owner_id = o.id)
                    AS candidate_count,
                (SELECT COUNT(*) FROM brands b WHERE b.owner_id = o.id) AS brand_count
           FROM owners o
          ORDER BY o.match_status = 'confirmed', o.name"
    );

    return $rows === false ? false : $rows;
}

/**
 * Candidate companies suggested for an owner, best first.
 */
function admin_owner_candidates($owner_id)
{
    $rows = query(
        "SELECT company_number, company_name, company_status, address_snippet, score
           FROM owner_company_candidates
          WHERE owner_id = ?
          ORDER BY score DESC, company_name",
        $owner_id
    );

    return $rows === false ? [] : $rows;
}

/**
 * Confirms one of the suggested companies as this owner's registered identity.
 *
 * Only ever promotes a candidate we actually fetched, so a forged form field
 * cannot invent a company number.
 */
function admin_confirm_owner_company($owner_id, $company_number)
{
    $rows = query(
        "SELECT company_number, company_name, company_status
           FROM owner_company_candidates
          WHERE owner_id = ? AND company_number = ?
          LIMIT 1",
        $owner_id, $company_number
    );

    if (empty($rows))
    {
        return false;
    }

    $c = $rows[0];

    $ok = query(
        "UPDATE owners
            SET company_number = ?, company_name = ?, company_status = ?,
                match_status = 'confirmed', match_note = 'confirmed by hand',
                source = ?, source_url = ?, source_licence = ?, retrieved_at = NOW()
          WHERE id = ?",
        $c["company_number"], $c["company_name"], $c["company_status"],
        "companieshouse",
        "https://find-and-update.company-information.service.gov.uk/company/"
            . rawurlencode($c["company_number"]),
        "OGL v3.0",
        $owner_id
    );

    if ($ok === false)
    {
        return false;
    }

    query("DELETE FROM owner_company_candidates WHERE owner_id = ?", $owner_id);

    return true;
}

/**
 * Unlinks an owner from its company, sending it back to the importer.
 */
function admin_clear_owner_match($owner_id)
{
    return query(
        "UPDATE owners
            SET company_number = NULL, company_name = NULL, company_status = NULL,
                incorporated_on = NULL, parent_company_name = NULL,
                parent_company_number = NULL, match_status = 'unmatched',
                match_note = NULL, source = NULL, source_url = NULL,
                source_licence = NULL, retrieved_at = NULL
          WHERE id = ?",
        $owner_id
    ) !== false;
}

/**
 * Escapes LIKE wildcards so that a user searching for "50%" does not
 * match everything.
 */
function like_escape($value)
{
    return str_replace(["\\", "%", "_"], ["\\\\", "\\%", "\\_"], (string) $value);
}
