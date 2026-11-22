<?php
define( 'WP_CACHE', false );

/**
 * The base configuration for WordPress
 */

// ** Database settings ** //
define( 'DB_NAME', getenv('WORDPRESS_DB_NAME') ?: 'tajalhdaya' );
define( 'DB_USER', getenv('WORDPRESS_DB_USER') ?: 'tajalhdaya_user' );
define( 'DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: 'tajalhdaya_password' );
define( 'DB_HOST', getenv('WORDPRESS_DB_HOST') ?: 'db:3306' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 */
define( 'AUTH_KEY',          'J$RCNt7U]w4Y0XeA=Wk50ad:mc]eHF<j>Q;E[I2)j{6po:VRC]1%WgX{lJC,CDk=' );
define( 'SECURE_AUTH_KEY',   'mf>L4z%zN3]X%(XKs6Av8nh2GYjD#^Im#J~z6C|:n9.PwrZf[8:=K4%-XBHSZ~nb' );
define( 'LOGGED_IN_KEY',     'Ya+57L18DkcF},W5:.+lUFfWtCov|UJZxO5:]V_f3 5/b7Ng6jpLb>U*fGdMYd%J' );
define( 'NONCE_KEY',         '21G1DDUy/cTuePFUm9lUwrcvX`%`*YH?Y$MG]zb}YFuR1aJq8{34zR%T,2}9>ayo' );
define( 'AUTH_SALT',         '|7Rkm]LlY:&)vF|#,Fe(4d?h9~x7?c>&VuT^ce`odlsp6k5:f}.TY42,]-~Mj9)}' );
define( 'SECURE_AUTH_SALT',  'iaRpH8eE$|D}vsult1n+p,w?C|Jg1xX~SjQ)^i7f[!VStl]6di5WO|=qA,82]9Mc' );
define( 'LOGGED_IN_SALT',    'eg_dXe}v{:/#cBHS:V5qOM{|@XtGZ-_j)94%u/N_ThNw4./Bg2kKh}C^K(#JfA0(' );
define( 'NONCE_SALT',        'ssSU0FnKvruJ*7;t]GON]v^/3fAD$I?KZ4/@B0`4<FdaJW1?02980Aq3wyjr1|Kw' );
define( 'WP_CACHE_KEY_SALT', '`:.D*J6;|b26 MIdy o$[P0C{[xG4FL3Sw`P!svZ)!D22U/WdPhiKL=6]%,)F@1a' );
/**#@-*/

$table_prefix = 'wp_';

// Local environment overrides
define( 'WP_HOME', 'http://localhost:8085' );
define( 'WP_SITEURL', 'http://localhost:8085' );

if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', '3f82bbf30a3e0da2c20ddc68081d88f4' );
define( 'WP_AUTO_UPDATE_CORE', false );

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
