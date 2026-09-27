<?php
/**
 * Gutenberg editor sidebar integration.
 *
 * @package MMSEO
 */

namespace MMSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class EditorSidebar
 *
 * Enqueues the Gutenberg sidebar script and passes localised data to it.
 */
class EditorSidebar {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue' ] );
	}

	/**
	 * Enqueue Gutenberg sidebar assets.
	 */
	public function enqueue() {
		wp_enqueue_script(
			'mmseo-gutenberg',
			MMSEO_URL . 'assets/js/gutenberg-sidebar.js',
			[
				'wp-plugins',
				'wp-editor',
				'wp-element',
				'wp-components',
				'wp-data',
				'wp-compose',
				'wp-api-fetch',
				'wp-i18n',
				'mmseo-analyzer',
				'mmseo-snippet-preview',
			],
			MMSEO_VERSION,
			true
		);

		wp_set_script_translations( 'mmseo-gutenberg', 'mm-seo', MMSEO_DIR . 'languages' );

		$settings  = \MMSEO\Options::settings();
		$sep       = $settings['separator'] ?? '|';
		$site_name = get_bloginfo( 'name' );

		$schema_types = [
			''                => __( 'Default', 'mm-seo' ),
			'Article'         => 'Article',
			'BlogPosting'     => 'BlogPosting',
			'NewsArticle'     => 'NewsArticle',
			'WebPage'         => 'WebPage',
			'AboutPage'       => 'AboutPage',
			'ContactPage'     => 'ContactPage',
			'FAQPage'         => 'FAQPage',
			'Event'           => 'Event',
			'TouristAttraction' => 'TouristAttraction',
			'TouristTrip'     => 'TouristTrip',
			'Product'         => 'Product',
			'Recipe'          => 'Recipe',
			'None'            => __( 'None (no schema)', 'mm-seo' ),
		];

		wp_localize_script(
			'mmseo-gutenberg',
			'MMSEOSidebarData',
			[
				'homeHost'    => parse_url( home_url(), PHP_URL_HOST ),
				'siteName'    => $site_name,
				'sep'         => $sep,
				'schemaTypes' => $schema_types,
			]
		);

		wp_enqueue_style(
			'mmseo-admin',
			MMSEO_URL . 'assets/css/admin.css',
			[],
			MMSEO_VERSION
		);
	}
}
