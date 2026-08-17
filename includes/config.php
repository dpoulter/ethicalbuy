<?php

/**
 * config.php
 *
 * Configures pages.
 */

require_once(__DIR__ . "/constants.php");
require_once(__DIR__ . "/functions.php");
require_once(__DIR__ . "/scoring.php");

// never render errors to visitors in production; always log them
if (DEBUG)
{
    ini_set("display_errors", "1");
}
else
{
    ini_set("display_errors", "0");
}
ini_set("log_errors", "1");
error_reporting(E_ALL);

// enable sessions
if (session_status() === PHP_SESSION_NONE)
{
    session_start();
}
