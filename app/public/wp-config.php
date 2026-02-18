<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'local' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'lv|F3grl^xT=XEMI)-~_J1iolIQy)TJ#nsY5[>*_C,GT)_;@<U<%rK[Sg[n*z C^' );
define( 'SECURE_AUTH_KEY',   'BQUw0bKjTM;dw!z:J3J{E|A:!^HGXlh2G.FY|3oaS0_{&M(o&QV?vkn:Re8QZl{#' );
define( 'LOGGED_IN_KEY',     's{I80hD@8D5#S+uBB~kDdf30#P%2;OlR@.A](^$z2vKZ%T+#x`ro]UC^3%}{C>(l' );
define( 'NONCE_KEY',         'C  %_?W`#C!-3XQx8KMf{t=:3UHJVq=ctVu(&Cw`cs^_atV-vPg*gG/`&g[}Yonq' );
define( 'AUTH_SALT',         '><Wkfq10h<3D1;H+QO%zmy2!pn*fLBDc+rY7C{}t</He(HS4E&)^0%wJ^8{HS+Ki' );
define( 'SECURE_AUTH_SALT',  'fZZ1Ak)J)3hDz$mSeHF>SR 2]0om$]XotkIQ&a6?C$:08~G{SH>10&;3W]2e.FY6' );
define( 'LOGGED_IN_SALT',    'Naqhq}r)4AHw<2}Mt6LW`$:bglVCQqvxE#f_gz1^*U:5fH?~;6F`_l[*e9b5j)%x' );
define( 'NONCE_SALT',        'rcxB]Wco#t)$(q28%vV*5N/b*.;EY]&4A@ud>mH:?d8zOsGf@($g~i:~T;pO3w?_' );
define( 'WP_CACHE_KEY_SALT', 'E$#DH98X1gsWCUD<09x.U=W;D0(3#9FL_QjG3Yb28}U9T=-M[V*@)+&`Q &H1CbS' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
