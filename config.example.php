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

/** Token lifetime in seconds (default in JwtService if unset: 604800 = 7 days). */
define('JWT_TTL', 60 * 60 * 24 * 7);
