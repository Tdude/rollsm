<?php
/**
 * The base configuration for WordPress rollsm.se
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
define( 'DB_NAME', 'wp_cjemf' );

/** Database username */
define( 'DB_USER', 'wp_ezus8' );

/** Database password */
define( 'DB_PASSWORD', '9Oh^rYsBO1IJ9*%l' );

/** Database hostname */
define( 'DB_HOST', 'ls-63f6d1113489821b81d1bddddd392a43fbacc283.cqcpcn8entjs.eu-north-1.rds.amazonaws.com:3306' );

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
define('AUTH_KEY', '|+)de*~END1D!~X6uS7!vw(*eX53e&P(7L)%XTY3VGYt9B6-3;+w-0!ZRh]#Bk!j');
define('SECURE_AUTH_KEY', 'w9n2C9;[0KrD22k907T!nOifq_&v1@19ne;493Vy;P7Gq-OAGnzwH%utz%0T7X#Y');
define('LOGGED_IN_KEY', '*xfP4(bDFsMkL*+6|(ol3E[9EF|s~pTae5/S]1;7B-HF#II)5d;!zTyL8k25lDir');
define('NONCE_KEY', ':nP7PhI)2Kxi6_Vb%:61;S1QG47[oJ!zVdg2+6vX13)r8twHmq;(3*+3Vh%pq0jY');
define('AUTH_SALT', 'ySl%YmHhN@4e1v!W3Fmqp-igndF|0*P(85JMA3hm%u;6HbV(+05k5_[0c2;7c);9');
define('SECURE_AUTH_SALT', 'f/v6WA(:O1&J@av2:eiwe#&-96&%:uN52Y&vj60yhw56pFJ[34J0~K%w+]-Mv15l');
define('LOGGED_IN_SALT', 'y66w7G&4f5jb14/bB7):|W8972l6056A:~R0N6E/f:0uut+]Hs]DzGLF&#lp8BLN');
define('NONCE_SALT', 'O@b%n94q3@z7gZ2o;Y/2HQx9@3MLaEN%S21[C%36r779e058Yc4+YxO96@+CT+AK');


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'Z8NK3nsyu_';


/* Add any custom values between this line and the "stop editing" line. */

define('WP_ALLOW_MULTISITE', true);
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

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
