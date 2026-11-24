<?php
/**
 * Router for PHP's built-in server, used by tests/smoke.php. Mimics Caddy's
 * php_fastcgi: an existing file is served as itself, anything else is
 * handed to /index.php with the original REQUEST_URI. Not part of the site.
 */

$public = realpath(__DIR__ . "/../public");
$path = rawurldecode((string) parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH));
$file = realpath($public . $path);

if ($file !== false && strpos($file, $public) === 0)
{
    if (is_file($file))
    {
        return false;
    }

    if (is_dir($file) && is_file($file . "/index.php"))
    {
        return false;
    }
}

$_SERVER["SCRIPT_NAME"] = "/index.php";
$_SERVER["PHP_SELF"] = "/index.php";
$_SERVER["SCRIPT_FILENAME"] = $public . "/index.php";
chdir($public);
require $public . "/index.php";
