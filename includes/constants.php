<?php

/**
 * constants.php
 *
 * Global constants.
 *
 * Secrets are read from the environment, never from this file. See
 * .env.example for the full list and README.md for how to set them.
 */

/**
 * Reads an environment variable, falling back to $default.
 * Passing null as $default makes the variable required.
 */
function env($name, $default = null)
{
    $value = getenv($name);

    if ($value === false || $value === "")
    {
        if ($default === null)
        {
            // fail loudly and without echoing anything sensitive
            error_log("Missing required environment variable: $name");
            http_response_code(500);
            exit("Server is not configured correctly.");
        }

        return $default;
    }

    return $value;
}

// database connection
define("DATABASE", env("DB_NAME", "ethicalbuy"));
define("SERVER", env("DB_HOST", "localhost"));
define("USERNAME", env("DB_USER", "ethicalbuy"));
define("PASSWORD", env("DB_PASSWORD"));

// site
define("SITE_URL", env("SITE_URL", "https://ethicalbuy.duckdns.org"));

// set to "1" only while developing locally
define("DEBUG", env("APP_DEBUG", "0") === "1");
