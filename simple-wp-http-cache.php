<?php
/**
 * Plugin Name:     Simple WP HTTP Cache
 * Plugin URI:      N/A
 * Description:     A simple HTTP Cache and log for WordPress.
 * Author:          Edwin Cromley
 * Author URI:      https://edwincromley.com
 * Text Domain:     simple-wp-http-cache
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Simple_WP_HTTP_Cache
 */

// Require main source file.
require_once 'src/class-logger-interface.php';
require_once 'src/class-logger.php';
require_once 'src/main.php';

add_action( 'admin_init', 'simple_wp_http_cache_activate' );

/**
 * Admin panel activation hook.
 */
function simple_wp_http_cache_activate() {
	// Set minimum versions.
	$php = '7.0.0';
	$wp = '4.9.8';

	global $wp_version;
	$flag = '';

	if ( version_compare( PHP_VERSION, $php, '<' ) ) {
		$flag = 'PHP';
	} elseif ( version_compare( $wp_version, $wp, '<' ) ) {
		$flag = 'WordPress';
	}

	if ( empty( $flag ) ) {
		return;
	}

	simple_wp_http_cache_deactivate();

	if ( $flag === 'PHP' ) {
		add_action( 'admin_notices', 'simple_wp_http_cache_activate_php_error_notice' );

		if ( is_admin() && isset( $_GET ) ) {
			unset( $_GET['activate'] );
		}
	}

	if ( $flag === 'WordPress' ) {
		add_action( 'admin_notices', 'simple_wp_http_cache_activate_wp_error_notice' );

		if ( is_admin() && isset( $_GET ) ) {
			unset( $_GET['activate'] );
		}
	}
}

function simple_wp_http_cache_activate_php_error_notice() {
	?>
	<div class="error below-h2">
		<p>
		<?php
			/* translators: %s is the current PHP version */
			printf( __( 'Simple WP HTTP Cache requires at least PHP 7.0.0, your server is running: %s. Contact your hosting provider to upgrade.', 'simple-wp-http-cache' ), sanitize_text_field( PHP_VERSION ) );
		?>
		</p>
	</div>
	<?php
}

function simple_wp_http_cache_activate_wp_error_notice() {
	global $wp_version;
	?>
	<div class="error below-h2">
		<p>
		<?php
			/* translators: %s is the current WordPress version */
			printf( __( 'Simple WP HTTP Cache requires at least WordPress 4.9.8, your server is running: %s. Use the WordPress admin dashboard to upgrade, make sure to backup first!', 'simple-wp-http-cache' ), sanitize_text_field( $wp_version ) );
		?>
		</p>
	</div>
	<?php
}

function simple_wp_http_cache_deactivate() {
	deactivate_plugins( plugin_basename( __FILE__ ) );
}
