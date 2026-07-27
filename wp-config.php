<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'arkray_developer' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'Gn[9OIL9p(V2JQ0[o0<U2rK1.(^Kb=ep%:Qq}M*f=+Ee&(3qAFT<!iHHNtLP`{$/' );
define( 'SECURE_AUTH_KEY',  '74w.mrLf8HU+!.MgG5:p7D]tO9@+@aHff[)aU!qoLJi7,0(iD18S,rW^=06kzk E' );
define( 'LOGGED_IN_KEY',    '.,68([$PJUyt6PI5NmWXOf^gYuahGwD~9q<lH1-LYESknrec0a/>2F-EP9n!VG@O' );
define( 'NONCE_KEY',        'g}tzCLTMpdrJr&n|3}Q9zmzoT5,6{pRJ5sB1oIR<3LxXV+g|r?2ljJi|Mjh^ndM6' );
define( 'AUTH_SALT',        '|YV.LA9VA{OsG)O-UI,SwBxB)(~XF^?&4)~Qf)Xo_*]Ppc{[5n{7.``GaZ*Qyl[e' );
define( 'SECURE_AUTH_SALT', 'CD@=#)mYp?KeZ4UUY4pb1dpP)]h$+Nk:5`Nibf<`rFei?AzVO{B0^}!_*Azp&!aY' );
define( 'LOGGED_IN_SALT',   'g|sd%?N8w+SPw_N!9P7{Z[}W0g]Y1g4+Eo-tC<Mlm~j7]|VJf}rTv(Im/h$D@54i' );
define( 'NONCE_SALT',       'Is^O/&`pB2I/mvSKK#k~J8#w77TM[B0!ypO#F-eH[VC(67Gu^}[!827xn,f=Ab>&' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

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
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
