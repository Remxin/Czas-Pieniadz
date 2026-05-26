<?php

/**
 * Copy this file to config.php and set real values.
 * config.php is gitignored.
 */

define('USERNAME', '');
define('PASSWORD', '');
define('HOST', '');
define('DATABASE', '');

/** Long random string (32+ characters). Used to sign JWTs. */
define('JWT_SECRET', '');

/** Cookie name for the auth JWT; default used if omitted is auth_token in code paths that check defined(). */
define('JWT_COOKIE_NAME', 'auth_token');

/** Legacy fallback TTL in seconds (prefer JWT_ACCESS_TTL for access tokens). */
define('JWT_TTL', 60 * 60 * 24 * 7);

/** Access token lifetime in seconds (default: 900 = 15 minutes). */
define('JWT_ACCESS_TTL', 900);

/** Refresh token lifetime without "remember me" (default: 86400 = 1 day). */
define('JWT_REFRESH_TTL', 86400);

/** Refresh token lifetime with "remember me" (default: 2592000 = 30 days). */
define('JWT_REFRESH_REMEMBER_TTL', 60 * 60 * 24 * 30);

/** Cookie name for the opaque refresh token. */
define('REFRESH_COOKIE_NAME', 'refresh_token');
