<?php
namespace MMSEO\Compat;

defined( 'ABSPATH' ) || exit;

use MMSEO\Options;

/**
 * Generic SEO plugin conflict detector and admin notice handler.
 *
 * When another active SEO plugin is detected, MM SEO suspends all frontend
 * output to prevent duplicate meta tags, and displays a persistent admin notice
 * explaining the situation. The "Force output" option overrides the suspension
 * when the administrator explicitly wants MM SEO output alongside another plugin.
 */
class SeoConflicts {

	/**
	 * Detect active conflicting SEO plugins.
	 *
	 * @return array<string, string> Map of plugin id => human-readable label.
	 */
	public static function detect(): array {
		$conflicts = [];

		if ( defined( 'WPSEO_VERSION' ) ) {
			$conflicts['yoast'] = 'Yoast SEO';
		}

		if ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ) ) {
			$conflicts['rank-math'] = 'Rank Math';
		}

		if ( defined( 'AIOSEO_VERSION' ) ) {
			$conflicts['aioseo'] = 'All in One SEO';
		}

		if ( defined( 'SEOPRESS_VERSION' ) ) {
			$conflicts['seopress'] = 'SEOPress';
		}

		return $conflicts;
	}

	/**
	 * Register hooks.
	 */
	public function register(): void {
		$conflicts = self::detect();

		if ( empty( $conflicts ) ) {
			return;
		}

		add_action( 'admin_notices', [ $this, 'show_conflict_notice' ] );
	}

	/**
	 * Display a persistent (non-dismissible) admin notice when another SEO plugin is active.
	 */
	public function show_conflict_notice(): void {
		$conflicts = self::detect();

		if ( empty( $conflicts ) ) {
			return;
		}

		$labels       = implode( ', ', array_values( $conflicts ) );
		$force_output = Options::get( 'force_output', false );

		if ( ! $force_output ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e( 'MM SEO', 'mm-seo' ); ?></strong>
					&mdash;
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: comma-separated list of active SEO plugin names */
							__( 'MM SEO has detected another active SEO plugin (%s). To avoid duplicate meta tags, MM SEO frontend output is paused. Deactivate the other SEO plugin, or enable "Force output" in MM SEO → Advanced if you know what you are doing.', 'mm-seo' ),
							$labels
						)
					);
					?>
				</p>
			</div>
			<?php
		} else {
			?>
			<div class="notice notice-warning">
				<p>
					<strong><?php esc_html_e( 'MM SEO', 'mm-seo' ); ?></strong>
					&mdash;
					<?php
					echo esc_html(
						sprintf(
							/* translators: %s: comma-separated list of active SEO plugin names */
							__( 'MM SEO output is forced while another SEO plugin is active (%s) — expect duplicate tags until the other plugin is deactivated.', 'mm-seo' ),
							$labels
						)
					);
					?>
				</p>
			</div>
			<?php
		}
	}
}
