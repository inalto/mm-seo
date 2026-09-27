<?php
namespace MMSEO\Admin;

defined( 'ABSPATH' ) || exit;

use MMSEO\Options;
use MMSEO\Analysis\Analyzer;
use MMSEO\Modules\Sitemaps\Sitemaps;

/**
 * Admin bootstrapper: registers the MM SEO admin menu, asset enqueue, and AJAX handlers.
 *
 * @package MMSEO\Admin
 */
class Admin {

	/** @var string[] Schema types list (reused in multiple places). */
	private const SCHEMA_TYPES = [
		'',
		'Article',
		'BlogPosting',
		'NewsArticle',
		'WebPage',
		'AboutPage',
		'ContactPage',
		'FAQPage',
		'Event',
		'TouristAttraction',
		'TouristTrip',
		'Product',
		'Recipe',
		'None',
	];

	/**
	 * Register all hooks.
	 */
	public function register(): void {
		add_action( 'admin_menu',            [ $this, 'add_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_mmseo_flush_sitemap', [ $this, 'handle_flush_sitemap' ] );
		add_action( 'wp_ajax_mmseo_bulk_analyze',     [ $this, 'handle_bulk_analyze' ] );

		// Instantiate and register sub-components (guarded by class_exists).
		$sub_components = [
			SettingsPage::class,
			MetaBox::class,
			EditorSidebar::class,
			TermSeo::class,
			Columns::class,
			HealthCheck::class,
		];

		foreach ( $sub_components as $class ) {
			if ( class_exists( $class ) ) {
				( new $class() )->register();
			}
		}
	}

	// -------------------------------------------------------------------------
	// Admin menu
	// -------------------------------------------------------------------------

	/**
	 * Register the top-level menu and all submenu pages.
	 */
	public function add_menus(): void {
		add_menu_page(
			__( 'MM SEO', 'mm-seo' ),
			__( 'MM SEO', 'mm-seo' ),
			'mmseo_manage_options',
			'mm-seo',
			[ SettingsPage::class, 'render' ],
			'dashicons-chart-line',
			80
		);

		$submenus = [
			'mm-seo'           => __( 'Dashboard', 'mm-seo' ),
			'mm-seo-titles'    => __( 'Titles & Meta', 'mm-seo' ),
			'mm-seo-social'    => __( 'Social', 'mm-seo' ),
			'mm-seo-sitemap'   => __( 'Sitemap', 'mm-seo' ),
			'mm-seo-redirects' => __( 'Redirects & 404', 'mm-seo' ),
			'mm-seo-advanced'  => __( 'Advanced', 'mm-seo' ),
			'mm-seo-tools'     => __( 'Tools', 'mm-seo' ),
		];

		foreach ( $submenus as $slug => $label ) {
			add_submenu_page(
				'mm-seo',
				$label . ' &mdash; ' . __( 'MM SEO', 'mm-seo' ),
				$label,
				'mmseo_manage_options',
				$slug,
				[ SettingsPage::class, 'render' ]
			);
		}
	}

	// -------------------------------------------------------------------------
	// Asset enqueue
	// -------------------------------------------------------------------------

	/**
	 * Register and conditionally enqueue admin scripts and styles.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Always register analyzer + snippet-preview (no enqueue by default).
		wp_register_script(
			'mmseo-analyzer',
			MMSEO_URL . 'assets/js/analyzer.js',
			[ 'wp-i18n' ],
			MMSEO_VERSION,
			true
		);
		wp_set_script_translations( 'mmseo-analyzer', 'mm-seo', MMSEO_DIR . 'languages' );

		wp_register_script(
			'mmseo-snippet-preview',
			MMSEO_URL . 'assets/js/snippet-preview.js',
			[ 'mmseo-analyzer' ],
			MMSEO_VERSION,
			true
		);
		wp_set_script_translations( 'mmseo-snippet-preview', 'mm-seo', MMSEO_DIR . 'languages' );

		// MM SEO settings pages.
		if ( false !== strpos( $hook_suffix, 'mm-seo' ) ) {
			wp_enqueue_style(
				'mmseo-admin',
				MMSEO_URL . 'assets/css/admin.css',
				[],
				MMSEO_VERSION
			);

			wp_enqueue_script(
				'mmseo-admin-settings',
				MMSEO_URL . 'assets/js/admin-settings.js',
				[ 'mmseo-snippet-preview', 'wp-i18n' ],
				MMSEO_VERSION,
				true
			);
			wp_set_script_translations( 'mmseo-admin-settings', 'mm-seo', MMSEO_DIR . 'languages' );

			wp_enqueue_media();
		}

		// Post edit screens.
		$is_post_screen = in_array( $hook_suffix, [ 'post.php', 'post-new.php' ], true );

		if ( $is_post_screen ) {
			global $typenow;

			$is_block_editor = function_exists( 'use_block_editor_for_post_type' )
				&& ! empty( $typenow )
				&& use_block_editor_for_post_type( $typenow );

			if ( ! $is_block_editor ) {
				// Classic editor: enqueue metabox assets.
				wp_enqueue_script(
					'mmseo-metabox',
					MMSEO_URL . 'assets/js/metabox.js',
					[ 'mmseo-analyzer', 'mmseo-snippet-preview', 'wp-api-fetch', 'wp-i18n' ],
					MMSEO_VERSION,
					true
				);
				wp_set_script_translations( 'mmseo-metabox', 'mm-seo', MMSEO_DIR . 'languages' );

				wp_enqueue_style(
					'mmseo-metabox',
					MMSEO_URL . 'assets/css/metabox.css',
					[],
					MMSEO_VERSION
				);

				wp_enqueue_media();

				// Localize metabox data.
				$post_id   = (int) ( $_GET['post'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
				$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
				$settings  = Options::settings();

				wp_localize_script(
					'mmseo-metabox',
					'MMSEOMetabox',
					[
						'postId'    => $post_id,
						'homeHost'  => $home_host,
						'siteName'  => get_bloginfo( 'name' ),
						'sep'       => $settings['separator'] ?? '|',
						'permalink' => $post_id > 0 ? get_permalink( $post_id ) : '',
						'nonce'     => wp_create_nonce( 'wp_rest' ),
					]
				);
			} else {
				// Block editor: enqueue Gutenberg sidebar.
				wp_enqueue_script(
					'mmseo-gutenberg',
					MMSEO_URL . 'assets/js/gutenberg.js',
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

				$settings  = Options::settings();
				$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

				wp_localize_script(
					'mmseo-gutenberg',
					'MMSEOSidebarData',
					[
						'homeHost'    => $home_host,
						'siteName'    => get_bloginfo( 'name' ),
						'sep'         => $settings['separator'] ?? '|',
						'schemaTypes' => self::SCHEMA_TYPES,
					]
				);
			}
		}
	}

	// -------------------------------------------------------------------------
	// Action handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle admin-post action: flush sitemap cache.
	 */
	public function handle_flush_sitemap(): void {
		check_admin_referer( 'mmseo_flush_sitemap' );

		if ( ! current_user_can( 'mmseo_manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'mm-seo' ) );
		}

		if ( class_exists( Sitemaps::class ) ) {
			Sitemaps::flush_cache();
		}

		$redirect = wp_get_referer() ?: admin_url( 'admin.php?page=mm-seo-sitemap' );
		wp_safe_redirect( add_query_arg( 'mmseo_notice', 'sitemap_flushed', $redirect ) );
		exit;
	}

	/**
	 * Handle AJAX: bulk-analyze posts.
	 */
	public function handle_bulk_analyze(): void {
		check_ajax_referer( 'mmseo_bulk_analyze' );

		if ( ! current_user_can( 'mmseo_manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Insufficient permissions.', 'mm-seo' ) ], 403 );
		}

		$offset    = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
		$per_batch = 20;

		$query = new \WP_Query( [
			'post_type'      => get_post_types( [ 'public' => true ] ),
			'post_status'    => 'publish',
			'posts_per_page' => $per_batch,
			'offset'         => $offset,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		] );

		$total     = (int) $query->found_posts;
		$post_ids  = $query->posts;
		$processed = count( $post_ids );

		if ( class_exists( Analyzer::class ) ) {
			foreach ( $post_ids as $post_id ) {
				Analyzer::analyze_post( (int) $post_id );
			}
		}

		$new_offset = $offset + $processed;
		$done       = ( $new_offset >= $total ) || ( $processed < $per_batch );

		wp_send_json( [
			'done'      => $done,
			'processed' => $processed,
			'total'     => $total,
			'offset'    => $new_offset,
		] );
	}
}
