<?php
namespace MMSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Core plugin singleton. Boots all services and enabled modules.
 */
final class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/**
	 * Whether frontend output is suspended (another SEO plugin active).
	 *
	 * @var bool
	 */
	public bool $frontend_suspended = false;

	/**
	 * Service classes that are always registered (when not suspended).
	 * Key = short name, value = FQCN.
	 */
	private const ALWAYS = [
		'meta'             => Meta::class,
		'capabilities'     => Capabilities::class,
		'compat_divi'      => Compat\Divi::class,
		'seo_conflicts'    => Compat\SeoConflicts::class,
		'admin'            => Admin\Admin::class,
		'analyzer'         => Analysis\Analyzer::class,
		'rest_controller'  => Analysis\RestController::class,
		// Head is added conditionally (not when frontend_suspended).
	];

	/**
	 * Module classes keyed by module id.
	 *
	 * @var array<string, class-string>
	 */
	private const MODULES = [
		'sitemaps'       => Modules\Sitemaps\Sitemaps::class,
		'redirects'      => Modules\Redirects\Redirects::class,
		'monitor404'     => Modules\Redirects\Monitor404::class,
		'indexnow'       => Modules\IndexNow::class,
		'robots_editor'  => Modules\RobotsTxt::class,
		'verification'   => Modules\Verification::class,
		'rss'            => Modules\RssOptimizer::class,
		'breadcrumbs'    => Frontend\Breadcrumbs::class,
	];

	/** Private constructor — use boot(). */
	private function __construct() {}

	/**
	 * Return the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot the plugin. Called on plugins_loaded priority 5.
	 */
	public static function boot(): void {
		$plugin = self::instance();

		// Detect conflicting SEO plugins and suspend frontend output unless force_output is on.
		$plugin->frontend_suspended = ! empty( Compat\SeoConflicts::detect() ) && ! Options::get( 'force_output', false );

		// Load text domain on init.
		add_action(
			'init',
			function (): void {
				load_plugin_textdomain( 'mm-seo', false, dirname( plugin_basename( MMSEO_FILE ) ) . '/languages' );
			}
		);

		// Flush rewrite rules on init (late priority) if flag is set.
		add_action(
			'init',
			function (): void {
				if ( get_option( 'mmseo_flush_rewrite' ) ) {
					flush_rewrite_rules();
					delete_option( 'mmseo_flush_rewrite' );
				}
			},
			99
		);

		// Register always-on services.
		foreach ( self::ALWAYS as $class ) {
			if ( class_exists( $class ) ) {
				( new $class() )->register();
			}
		}

		// Register Head only when not suspended.
		if ( ! $plugin->frontend_suspended ) {
			$head_class = Frontend\Head::class;
			if ( class_exists( $head_class ) ) {
				( new $head_class() )->register();
			}
		}

		// Register enabled modules.
		foreach ( self::MODULES as $id => $class ) {
			if ( ! class_exists( $class ) ) {
				continue;
			}
			if ( ! Options::module_enabled( $id ) ) {
				continue;
			}
			// Frontend-output modules must not register frontend hooks when suspended.
			if ( $plugin->frontend_suspended && in_array( $id, [ 'sitemaps', 'indexnow', 'breadcrumbs' ], true ) ) {
				continue;
			}
			( new $class() )->register();
		}
	}

	/**
	 * Whether MM SEO frontend output is suspended (e.g. another SEO plugin active).
	 */
	public static function is_frontend_suspended(): bool {
		return self::instance()->frontend_suspended;
	}
}
