<?php

/**
 * config.php
 *
 * Configures pages.
 */

require_once(__DIR__ . "/constants.php");
require_once(__DIR__ . "/functions.php");
require_once(__DIR__ . "/scoring.php");
require_once(__DIR__ . "/products.php");

require_once(__DIR__ . "/errors.php");
require_once(__DIR__ . "/ratelimit.php");
require_once(__DIR__ . "/contact.php");
require_once(__DIR__ . "/headers.php");
require_once(__DIR__ . "/seo.php");

// never render errors to visitors in production; always log them
install_error_handling();

// CSP, HSTS and friends on every response, error pages included
send_security_headers();

// /admin exists only on the private host names, if any are configured
enforce_admin_host();

// a path that belongs to no page is a 404, not the home page
enforce_known_path();
