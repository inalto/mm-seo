<?php
/**
 * Plugin Name: MM SEO
 * Description: Full-featured, lean SEO plugin by Martini Multimedia. Titles, meta, schema, sitemaps, redirects, 404 monitor, and more — without the bloat.
 * Version: 1.0.0
 * Author: Martini Multimedia
 * Author URI: https://www.martini-multimedia.net
 * Text Domain: mm-seo
 * Domain Path: /languages
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

// PHP/WP version guard.
if ( version_compare( PHP_VERSION, '8.1', '<' ) || version_compare( $GLOBALS['wp_version'] ?? '0', '6.6', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' .
				esc_html__( 'MM SEO requires PHP 8.1+ and WordPress 6.6+. Please update your environment.', 'mm-seo' ) .
				'</p></div>';
		}
	);
	return;
}

// Constants.
define( 'MMSEO_VERSION', '1.0.0' );
define( 'MMSEO_FILE', __FILE__ );
define( 'MMSEO_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'MMSEO_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );

// PSR-4-style autoloader: MMSEO\ → includes/.
spl_autoload_register(
	function ( string $class ): void {
		$prefix = 'MMSEO\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = MMSEO_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

// Activation hook.
register_activation_hook(
	__FILE__,
	function ( bool $network_wide ): void {
		if ( $network_wide && is_multisite() ) {
			$sites = get_sites( [ 'number' => 0, 'fields' => 'ids' ] );
			foreach ( $sites as $site_id ) {
				switch_to_blog( (int) $site_id );
				MMSEO\Options::seed_defaults();
				update_option( 'mmseo_flush_rewrite', 1 );
				restore_current_blog();
			}
		} else {
			MMSEO\Options::seed_defaults();
			update_option( 'mmseo_flush_rewrite', 1 );
		}
	}
);

// Deactivation hook.
register_deactivation_hook(
	__FILE__,
	function (): void {
		flush_rewrite_rules();
		wp_clear_scheduled_hook( 'mmseo_purge_404_log' );
	}
);

// WP-CLI commands (file self-registers `wp mmseo`).
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once MMSEO_DIR . 'includes/Cli/Commands.php';
}

// Boot the plugin.
add_action( 'plugins_loaded', [ 'MMSEO\\Plugin', 'boot' ], 5 );
