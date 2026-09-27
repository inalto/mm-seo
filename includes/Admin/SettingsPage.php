<?php
namespace MMSEO\Admin;

defined( 'ABSPATH' ) || exit;

use MMSEO\Options;
use MMSEO\ContentExtractor;

/**
 * Tabbed settings page renderer for MM SEO.
 *
 * @package MMSEO\Admin
 */
class SettingsPage {

	/** @var string[] Allowed separator characters. */
	private const SEPARATORS = [ '|', '–', '—', '·', '•', '~', '«', '»' ];

	/** @var string[] Allowed schema types. */
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

	/** @var array<string,string> Tab slug → label map. */
	private const TABS = [
		'mm-seo'           => 'Dashboard',
		'mm-seo-titles'    => 'Titles & Meta',
		'mm-seo-social'    => 'Social',
		'mm-seo-sitemap'   => 'Sitemap',
		'mm-seo-redirects' => 'Redirects & 404',
		'mm-seo-advanced'  => 'Advanced',
		'mm-seo-tools'     => 'Tools',
	];

	/** @var array<string,string> Page slug → tab id. */
	private const SLUG_TO_TAB = [
		'mm-seo'           => 'dashboard',
		'mm-seo-titles'    => 'titles',
		'mm-seo-social'    => 'social',
		'mm-seo-sitemap'   => 'sitemap',
		'mm-seo-redirects' => 'redirects',
		'mm-seo-advanced'  => 'advanced',
		'mm-seo-tools'     => 'tools',
	];

	// -------------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------------

	/**
	 * Register settings and hooks.
	 */
	public function register(): void {
		add_action( 'admin_init',                        [ $this, 'register_settings' ] );
		add_action( 'admin_notices',                     [ $this, 'show_notices' ] );
		add_action( 'admin_post_mmseo_export_settings',  [ $this, 'handle_export' ] );
		add_action( 'admin_post_mmseo_import_settings',  [ $this, 'handle_import' ] );
		add_action( 'admin_post_mmseo_regen_indexnow',   [ $this, 'handle_regen_indexnow' ] );
	}

	/**
	 * Register all settings groups.
	 */
	public function register_settings(): void {
		register_setting(
			'mmseo_settings_group',
			'mmseo_settings',
			[ 'sanitize_callback' => [ $this, 'sanitize_settings' ] ]
		);
		register_setting(
			'mmseo_titles_group',
			'mmseo_titles',
			[ 'sanitize_callback' => [ $this, 'sanitize_titles' ] ]
		);
		register_setting(
			'mmseo_social_group',
			'mmseo_social',
			[ 'sanitize_callback' => [ $this, 'sanitize_social' ] ]
		);
		register_setting(
			'mmseo_redirects_group',
			'mmseo_redirects',
			[ 'sanitize_callback' => [ $this, 'sanitize_redirects' ] ]
		);
		register_setting(
			'mmseo_robots_group',
			'mmseo_robots_txt',
			[ 'sanitize_callback' => 'wp_strip_all_tags' ]
		);
	}

	// -------------------------------------------------------------------------
	// Static render entry point (called by menu callback)
	// -------------------------------------------------------------------------

	/**
	 * Main render dispatcher (static — used as menu page callback).
	 */
	public static function render(): void {
		if ( ! current_user_can( 'mmseo_manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mm-seo' ) );
		}

		// Determine active tab from the page query var.
		$page    = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'mm-seo'; // phpcs:ignore WordPress.Security.NonceVerification
		$tab_id  = self::SLUG_TO_TAB[ $page ] ?? 'dashboard';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'MM SEO', 'mm-seo' ) . ' &mdash; ' . esc_html( self::tab_label( $tab_id ) ) . '</h1>';

		// Nav tabs.
		echo '<nav class="nav-tab-wrapper">';
		foreach ( self::TABS as $slug => $label ) {
			$active = ( $page === $slug ) ? ' nav-tab-active' : '';
			$url    = admin_url( 'admin.php?page=' . rawurlencode( $slug ) );
			echo '<a href="' . esc_url( $url ) . '" class="nav-tab' . esc_attr( $active ) . '">'
				. esc_html__( $label, 'mm-seo' ) // phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				. '</a>';
		}
		echo '</nav>';

		// Delegate to tab renderer.
		$instance = new self();
		$method   = 'render_' . $tab_id;
		if ( method_exists( $instance, $method ) ) {
			$instance->$method();
		}

		echo '</div>'; // .wrap
	}

	// -------------------------------------------------------------------------
	// Tab label helper
	// -------------------------------------------------------------------------

	private static function tab_label( string $tab_id ): string {
		$labels = [
			'dashboard' => __( 'Dashboard', 'mm-seo' ),
			'titles'    => __( 'Titles & Meta', 'mm-seo' ),
			'social'    => __( 'Social', 'mm-seo' ),
			'sitemap'   => __( 'Sitemap', 'mm-seo' ),
			'redirects' => __( 'Redirects & 404', 'mm-seo' ),
			'advanced'  => __( 'Advanced', 'mm-seo' ),
			'tools'     => __( 'Tools', 'mm-seo' ),
		];
		return $labels[ $tab_id ] ?? __( 'Dashboard', 'mm-seo' );
	}

	// -------------------------------------------------------------------------
	// Tab renderers
	// -------------------------------------------------------------------------

	/**
	 * Render the Dashboard tab.
	 */
	public function render_dashboard(): void {
		$settings = Options::settings();
		$modules  = Options::DEFAULT_SETTINGS['modules'];

		$module_labels = [
			'sitemaps'      => __( 'XML Sitemaps', 'mm-seo' ),
			'schema'        => __( 'Schema / Structured Data', 'mm-seo' ),
			'social'        => __( 'Social (OG / Twitter Cards)', 'mm-seo' ),
			'breadcrumbs'   => __( 'Breadcrumbs', 'mm-seo' ),
			'redirects'     => __( 'Redirects Manager', 'mm-seo' ),
			'monitor404'    => __( '404 Monitor', 'mm-seo' ),
			'indexnow'      => __( 'IndexNow', 'mm-seo' ),
			'robots_editor' => __( 'Robots.txt Editor', 'mm-seo' ),
			'verification'  => __( 'Site Verification', 'mm-seo' ),
			'rss'           => __( 'RSS Optimizer', 'mm-seo' ),
		];

		$module_descs = [
			'sitemaps'      => __( 'Automatically generate and serve an XML sitemap for posts, pages, and custom post types.', 'mm-seo' ),
			'schema'        => __( 'Output JSON-LD structured data (Article, Product, FAQ, etc.) for richer search results.', 'mm-seo' ),
			'social'        => __( 'Add Open Graph and Twitter Card meta tags for better social sharing previews.', 'mm-seo' ),
			'breadcrumbs'   => __( 'Display breadcrumb navigation and output breadcrumb schema markup.', 'mm-seo' ),
			'redirects'     => __( 'Manage 301/302/307 redirects directly from the WordPress admin.', 'mm-seo' ),
			'monitor404'    => __( 'Log 404 errors so you can identify broken links and fix them.', 'mm-seo' ),
			'indexnow'      => __( 'Instantly notify Bing and Yandex when content is published or updated.', 'mm-seo' ),
			'robots_editor' => __( 'Edit your site\'s robots.txt file directly from the WordPress admin.', 'mm-seo' ),
			'verification'  => __( 'Output Google, Bing, Yandex and Pinterest site verification meta tags on the homepage.', 'mm-seo' ),
			'rss'           => __( 'Append custom content before/after each RSS feed item.', 'mm-seo' ),
		];

		echo '<form method="post" action="options.php">';
		settings_fields( 'mmseo_settings_group' );

		// Modules grid.
		echo '<h2>' . esc_html__( 'Modules', 'mm-seo' ) . '</h2>';
		echo '<div class="mmseo-modules-grid">';
		foreach ( $modules as $id => $default_enabled ) {
			$enabled = (bool) ( $settings['modules'][ $id ] ?? $default_enabled );
			echo '<div class="mmseo-module-card">';
			echo '<label>';
			echo '<input type="checkbox" name="mmseo_settings[modules][' . esc_attr( $id ) . ']" value="1"'
				. checked( $enabled, true, false ) . '>';
			echo ' <strong>' . esc_html( $module_labels[ $id ] ?? $id ) . '</strong>';
			echo '</label>';
			echo '<p class="description">' . esc_html( $module_descs[ $id ] ?? '' ) . '</p>';
			echo '</div>';
		}
		echo '</div>';

		// Separator.
		echo '<h2>' . esc_html__( 'Title Separator', 'mm-seo' ) . '</h2>';
		echo '<fieldset><legend class="screen-reader-text">' . esc_html__( 'Title Separator', 'mm-seo' ) . '</legend>';
		$current_sep = $settings['separator'] ?? '|';
		foreach ( self::SEPARATORS as $sep ) {
			echo '<label style="margin-right:1em;">';
			echo '<input type="radio" name="mmseo_settings[separator]" value="' . esc_attr( $sep ) . '"'
				. checked( $current_sep, $sep, false ) . '>';
			echo ' <span style="font-size:1.2em;">' . esc_html( $sep ) . '</span>';
			echo '</label>';
		}
		echo '</fieldset>';

		// Site identity.
		echo '<h2>' . esc_html__( 'Site Identity', 'mm-seo' ) . '</h2>';
		$site_type = $settings['site_type'] ?? 'org';
		echo '<fieldset>';
		echo '<label style="margin-right:1em;">';
		echo '<input type="radio" name="mmseo_settings[site_type]" value="org"' . checked( $site_type, 'org', false ) . '>';
		echo ' ' . esc_html__( 'Organisation', 'mm-seo' );
		echo '</label>';
		echo '<label>';
		echo '<input type="radio" name="mmseo_settings[site_type]" value="person"' . checked( $site_type, 'person', false ) . '>';
		echo ' ' . esc_html__( 'Person', 'mm-seo' );
		echo '</label>';
		echo '</fieldset>';

		echo '<table class="form-table"><tbody>';

		// Org name.
		echo '<tr>';
		echo '<th scope="row"><label for="mmseo-org-name">' . esc_html__( 'Organisation / Person Name', 'mm-seo' ) . '</label></th>';
		echo '<td><input type="text" id="mmseo-org-name" name="mmseo_settings[org_name]" value="'
			. esc_attr( $settings['org_name'] ?? '' ) . '" class="regular-text"></td>';
		echo '</tr>';

		// Org logo.
		$org_logo_id = (int) ( $settings['org_logo_id'] ?? 0 );
		$org_logo    = $org_logo_id > 0 ? wp_get_attachment_image_url( $org_logo_id, 'thumbnail' ) : '';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Logo', 'mm-seo' ) . '</th>';
		echo '<td>';
		echo '<input type="hidden" id="mmseo-org-logo-id" name="mmseo_settings[org_logo_id]" value="' . esc_attr( (string) $org_logo_id ) . '">';
		if ( $org_logo ) {
			echo '<img src="' . esc_url( $org_logo ) . '" style="max-width:150px;display:block;margin-bottom:8px;" id="mmseo-org-logo-preview">';
		} else {
			echo '<img src="" style="max-width:150px;display:none;margin-bottom:8px;" id="mmseo-org-logo-preview">';
		}
		echo '<button type="button" class="button" id="mmseo-org-logo-btn">' . esc_html__( 'Select Logo', 'mm-seo' ) . '</button>';
		echo '<button type="button" class="button" id="mmseo-org-logo-remove" style="margin-left:4px;">' . esc_html__( 'Remove', 'mm-seo' ) . '</button>';
		echo '</td>';
		echo '</tr>';

		// Social profiles.
		$profiles       = $settings['social_profiles'] ?? [];
		$profile_labels = [
			'facebook'  => __( 'Facebook URL', 'mm-seo' ),
			'twitter'   => __( 'Twitter/X URL', 'mm-seo' ),
			'instagram' => __( 'Instagram URL', 'mm-seo' ),
			'linkedin'  => __( 'LinkedIn URL', 'mm-seo' ),
			'youtube'   => __( 'YouTube URL', 'mm-seo' ),
			'pinterest' => __( 'Pinterest URL', 'mm-seo' ),
		];
		foreach ( $profile_labels as $key => $label ) {
			echo '<tr>';
			echo '<th scope="row"><label for="mmseo-social-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td><input type="url" id="mmseo-social-' . esc_attr( $key ) . '" name="mmseo_settings[social_profiles][' . esc_attr( $key ) . ']" value="'
				. esc_attr( $profiles[ $key ] ?? '' ) . '" class="regular-text" placeholder="https://"></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		// Health check summary.
		if ( class_exists( HealthCheck::class ) ) {
			echo '<h2>' . esc_html__( 'Site Health', 'mm-seo' ) . '</h2>';
			$checks = HealthCheck::checks();
			echo '<div class="mmseo-health-grid">';
			foreach ( $checks as $check ) {
				$status = $check['status'] ?? 'ok';
				$color  = 'ok' === $status ? '#00a32a' : ( 'warning' === $status ? '#dba617' : '#d63638' );
				echo '<div class="mmseo-health-item" style="border-left:4px solid ' . esc_attr( $color ) . ';padding:8px 12px;margin-bottom:8px;">';
				echo '<strong>' . esc_html( $check['label'] ?? '' ) . '</strong>';
				echo '<p style="margin:4px 0 0;">' . esc_html( $check['message'] ?? '' ) . '</p>';
				echo '</div>';
			}
			echo '</div>';
		}

		submit_button();
		echo '</form>';
	}

	/**
	 * Render the Titles & Meta tab.
	 */
	public function render_titles(): void {
		$titles_data = Options::titles();

		echo '<form method="post" action="options.php">';
		settings_fields( 'mmseo_titles_group' );

		// Variable helper select.
		$this->render_variable_inserter();

		// Home.
		$home = $titles_data['home'] ?? [];
		$this->render_accordion_section(
			'mmseo-section-home',
			__( 'Homepage', 'mm-seo' ),
			function () use ( $home ) {
				$this->render_title_desc_fields( 'mmseo_titles[home]', $home );
			}
		);

		// Post types.
		echo '<h2>' . esc_html__( 'Post Types', 'mm-seo' ) . '</h2>';
		$post_types = get_post_types( [ 'public' => true ], 'objects' );
		foreach ( $post_types as $pt ) {
			$pt_data = $titles_data['post_types'][ $pt->name ] ?? [];
			$this->render_accordion_section(
				'mmseo-pt-' . $pt->name,
				$pt->label,
				function () use ( $pt, $pt_data ) {
					$base = 'mmseo_titles[post_types][' . $pt->name . ']';
					$this->render_title_desc_fields( $base, $pt_data );
					// noindex.
					echo '<p>';
					echo '<label><input type="checkbox" name="' . esc_attr( $base ) . '[noindex]" value="1"'
						. checked( ! empty( $pt_data['noindex'] ), true, false ) . '>';
					echo ' ' . esc_html__( 'No Index (exclude from search engines)', 'mm-seo' ) . '</label>';
					echo '</p>';
					// in_sitemap.
					echo '<p>';
					echo '<label><input type="checkbox" name="' . esc_attr( $base ) . '[in_sitemap]" value="1"'
						. checked( ! isset( $pt_data['in_sitemap'] ) || ! empty( $pt_data['in_sitemap'] ), true, false ) . '>';
					echo ' ' . esc_html__( 'Include in Sitemap', 'mm-seo' ) . '</label>';
					echo '</p>';
					// schema_type.
					$this->render_schema_type_select( $base, $pt_data['schema_type'] ?? '' );
				}
			);
		}

		// Taxonomies.
		echo '<h2>' . esc_html__( 'Taxonomies', 'mm-seo' ) . '</h2>';
		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
		foreach ( $taxonomies as $tax ) {
			$tax_data = $titles_data['taxonomies'][ $tax->name ] ?? [];
			$this->render_accordion_section(
				'mmseo-tax-' . $tax->name,
				$tax->label,
				function () use ( $tax, $tax_data ) {
					$base = 'mmseo_titles[taxonomies][' . $tax->name . ']';
					$this->render_title_desc_fields( $base, $tax_data );
					// noindex.
					echo '<p>';
					echo '<label><input type="checkbox" name="' . esc_attr( $base ) . '[noindex]" value="1"'
						. checked( ! empty( $tax_data['noindex'] ), true, false ) . '>';
					echo ' ' . esc_html__( 'No Index', 'mm-seo' ) . '</label>';
					echo '</p>';
					// in_sitemap.
					echo '<p>';
					echo '<label><input type="checkbox" name="' . esc_attr( $base ) . '[in_sitemap]" value="1"'
						. checked( ! isset( $tax_data['in_sitemap'] ) || ! empty( $tax_data['in_sitemap'] ), true, false ) . '>';
					echo ' ' . esc_html__( 'Include in Sitemap', 'mm-seo' ) . '</label>';
					echo '</p>';
				}
			);
		}

		// Archives.
		echo '<h2>' . esc_html__( 'Archives', 'mm-seo' ) . '</h2>';
		$archive_labels = [
			'author' => __( 'Author Archive', 'mm-seo' ),
			'date'   => __( 'Date Archive', 'mm-seo' ),
			'search' => __( 'Search Results', 'mm-seo' ),
			'404'    => __( '404 Not Found', 'mm-seo' ),
		];
		foreach ( $archive_labels as $key => $label ) {
			$arc_data = $titles_data['archives'][ $key ] ?? [];
			$this->render_accordion_section(
				'mmseo-arc-' . $key,
				$label,
				function () use ( $key, $arc_data ) {
					$base = 'mmseo_titles[archives][' . $key . ']';
					$this->render_title_desc_fields( $base, $arc_data );
					echo '<p>';
					echo '<label><input type="checkbox" name="' . esc_attr( $base ) . '[noindex]" value="1"'
						. checked( ! empty( $arc_data['noindex'] ), true, false ) . '>';
					echo ' ' . esc_html__( 'No Index', 'mm-seo' ) . '</label>';
					echo '</p>';
				}
			);
		}

		submit_button();
		echo '</form>';
	}

	/**
	 * Render the Social tab.
	 */
	public function render_social(): void {
		$social = Options::social();

		echo '<form method="post" action="options.php">';
		settings_fields( 'mmseo_social_group' );
		echo '<table class="form-table"><tbody>';

		// OG default image.
		$og_img_url = $social['og_default_image'] ?? '';
		$og_img_id  = (int) ( $social['og_default_image_id'] ?? 0 );
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Default OG Image', 'mm-seo' ) . '</th>';
		echo '<td>';
		echo '<input type="hidden" id="mmseo-og-image-id" name="mmseo_social[og_default_image_id]" value="' . esc_attr( (string) $og_img_id ) . '">';
		echo '<input type="url" id="mmseo-og-image-url" name="mmseo_social[og_default_image]" value="' . esc_attr( $og_img_url ) . '" class="regular-text" placeholder="https://">';
		echo ' <button type="button" class="button" id="mmseo-og-image-btn">' . esc_html__( 'Choose Image', 'mm-seo' ) . '</button>';
		if ( $og_img_url ) {
			echo '<br><img src="' . esc_url( $og_img_url ) . '" style="max-width:200px;margin-top:8px;" id="mmseo-og-image-preview">';
		} else {
			echo '<br><img src="" style="max-width:200px;margin-top:8px;display:none;" id="mmseo-og-image-preview">';
		}
		echo '</td>';
		echo '</tr>';

		// OG site name.
		echo '<tr>';
		echo '<th scope="row"><label for="mmseo-og-sitename">' . esc_html__( 'OG Site Name', 'mm-seo' ) . '</label></th>';
		echo '<td><input type="text" id="mmseo-og-sitename" name="mmseo_social[og_site_name]" value="'
			. esc_attr( $social['og_site_name'] ?? '' ) . '" class="regular-text"></td>';
		echo '</tr>';

		// Twitter card.
		$twitter_card = $social['twitter_card'] ?? 'summary_large_image';
		echo '<tr>';
		echo '<th scope="row"><label for="mmseo-twitter-card">' . esc_html__( 'Twitter Card Type', 'mm-seo' ) . '</label></th>';
		echo '<td><select id="mmseo-twitter-card" name="mmseo_social[twitter_card]">';
		echo '<option value="summary"' . selected( $twitter_card, 'summary', false ) . '>' . esc_html__( 'Summary', 'mm-seo' ) . '</option>';
		echo '<option value="summary_large_image"' . selected( $twitter_card, 'summary_large_image', false ) . '>' . esc_html__( 'Summary with Large Image', 'mm-seo' ) . '</option>';
		echo '</select></td>';
		echo '</tr>';

		// Twitter @site handle.
		echo '<tr>';
		echo '<th scope="row"><label for="mmseo-twitter-site">' . esc_html__( 'Twitter @Username', 'mm-seo' ) . '</label></th>';
		echo '<td><input type="text" id="mmseo-twitter-site" name="mmseo_social[twitter_site]" value="'
			. esc_attr( $social['twitter_site'] ?? '' ) . '" class="regular-text" placeholder="@username"></td>';
		echo '</tr>';

		echo '</tbody></table>';
		submit_button();
		echo '</form>';
	}

	/**
	 * Render the Sitemap tab.
	 */
	public function render_sitemap(): void {
		$sitemap_url = home_url( '/sitemap_index.xml' );
		echo '<p>';
		echo esc_html__( 'Your sitemap is available at:', 'mm-seo' );
		echo ' <a href="' . esc_url( $sitemap_url ) . '" target="_blank">' . esc_url( $sitemap_url ) . '</a>';
		echo '</p>';
		echo '<p class="description">' . esc_html__( 'Per-type inclusion is managed in the Titles & Meta tab for each post type and taxonomy.', 'mm-seo' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="mmseo_flush_sitemap">';
		wp_nonce_field( 'mmseo_flush_sitemap' );
		submit_button( __( 'Flush Sitemap Cache', 'mm-seo' ), 'secondary' );
		echo '</form>';
	}

	/**
	 * Render the Redirects & 404 tab.
	 */
	public function render_redirects(): void {
		$redirects = get_option( 'mmseo_redirects', [] );
		if ( ! is_array( $redirects ) ) {
			$redirects = [];
		}

		$settings = Options::settings();

		// Pre-fill new row from ?src= query arg.
		$prefill_src = '';
		if ( isset( $_GET['src'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$prefill_src = sanitize_text_field( wp_unslash( $_GET['src'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		echo '<form method="post" action="options.php" id="mmseo-redirects-form">';
		settings_fields( 'mmseo_redirects_group' );

		echo '<table class="widefat striped" id="mmseo-redirects-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Source URL', 'mm-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'Destination URL', 'mm-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'Code', 'mm-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'Regex', 'mm-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'Hits', 'mm-seo' ) . '</th>';
		echo '<th>' . esc_html__( 'Delete', 'mm-seo' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody id="mmseo-redirects-body">';

		foreach ( $redirects as $i => $rule ) {
			$this->render_redirect_row( $i, $rule );
		}

		// New row (potentially prefilled).
		$new_row = [
			'src'   => $prefill_src,
			'dst'   => '',
			'code'  => 301,
			'regex' => false,
			'hits'  => 0,
			'last'  => '',
		];
		echo '<tr id="mmseo-redirect-new-row">';
		$new_idx = count( $redirects );
		$this->render_redirect_row( $new_idx, $new_row );
		echo '</tr>';

		echo '</tbody></table>';

		echo '<p>';
		echo '<button type="button" class="button" id="mmseo-add-redirect-row">'
			. esc_html__( '+ Add Row', 'mm-seo' ) . '</button>';
		echo '</p>';

		submit_button();
		echo '</form>';

		// 404 log.
		if ( ! empty( $settings['modules']['monitor404'] ) ) {
			echo '<h2>' . esc_html__( '404 Log', 'mm-seo' ) . '</h2>';
			if ( class_exists( ListTable404::class ) ) {
				$table = new ListTable404();
				$table->prepare_items();
				$table->display();
			} else {
				echo '<p class="description">' . esc_html__( '404 Monitor is active. Log table component not loaded.', 'mm-seo' ) . '</p>';
			}
		}

		// Prefill JS.
		if ( '' !== $prefill_src ) {
			echo '<script>document.addEventListener("DOMContentLoaded",function(){';
			echo 'var s=document.querySelector("#mmseo-redirect-new-row input[name*=\'[src]\']");';
			echo 'if(s){s.value=' . wp_json_encode( $prefill_src ) . ';}';
			echo '});</script>';
		}
	}

	/**
	 * Render the Advanced tab.
	 */
	public function render_advanced(): void {
		$settings   = Options::settings();
		$robots_txt = (string) get_option( 'mmseo_robots_txt', '' );
		$has_file   = file_exists( ABSPATH . 'robots.txt' );

		echo '<form method="post" action="options.php">';
		settings_fields( 'mmseo_settings_group' );

		// robots.txt editor.
		echo '<h2>' . esc_html__( 'robots.txt', 'mm-seo' ) . '</h2>';
		if ( $has_file ) {
			echo '<div class="notice notice-warning inline"><p>'
				. esc_html__( 'A physical robots.txt file was found in the root of your site. The virtual editor below is disabled because WordPress cannot override a physical file.', 'mm-seo' )
				. '</p></div>';
			echo '<textarea class="large-text" rows="10" disabled>';
			echo esc_textarea( (string) file_get_contents( ABSPATH . 'robots.txt' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			echo '</textarea>';
		} else {
			// Separate form for robots_txt.
			echo '</form>';
			echo '<form method="post" action="options.php">';
			settings_fields( 'mmseo_robots_group' );
			echo '<textarea name="mmseo_robots_txt" class="large-text" rows="10">';
			echo esc_textarea( $robots_txt );
			echo '</textarea>';
			submit_button( __( 'Save robots.txt', 'mm-seo' ), 'secondary' );
			echo '</form>';
			echo '<form method="post" action="options.php">';
			settings_fields( 'mmseo_settings_group' );
		}

		// Verification codes.
		echo '<h2>' . esc_html__( 'Site Verification', 'mm-seo' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Paste the full meta tag or just the content= value.', 'mm-seo' ) . '</p>';
		echo '<table class="form-table"><tbody>';
		$verification = $settings['verification'] ?? [];
		$ver_labels   = [
			'google'    => __( 'Google Search Console', 'mm-seo' ),
			'bing'      => __( 'Bing Webmaster Tools', 'mm-seo' ),
			'yandex'    => __( 'Yandex Webmaster', 'mm-seo' ),
			'pinterest' => __( 'Pinterest', 'mm-seo' ),
		];
		foreach ( $ver_labels as $key => $label ) {
			echo '<tr>';
			echo '<th scope="row"><label for="mmseo-ver-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td><input type="text" id="mmseo-ver-' . esc_attr( $key ) . '" name="mmseo_settings[verification][' . esc_attr( $key ) . ']" value="'
				. esc_attr( $verification[ $key ] ?? '' ) . '" class="regular-text"></td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		// IndexNow.
		$indexnow_enabled = ! empty( $settings['modules']['indexnow'] );
		$indexnow_key     = (string) get_option( 'mmseo_indexnow_key', '' );
		echo '<h2>' . esc_html__( 'IndexNow', 'mm-seo' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'IndexNow Key', 'mm-seo' ) . '</th>';
		echo '<td>';
		if ( $indexnow_enabled && '' !== $indexnow_key ) {
			echo '<code>' . esc_html( $indexnow_key ) . '</code> ';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;">';
			echo '<input type="hidden" name="action" value="mmseo_regen_indexnow">';
			wp_nonce_field( 'mmseo_regen_indexnow' );
			echo '<button type="submit" class="button button-small">' . esc_html__( 'Regenerate Key', 'mm-seo' ) . '</button>';
			echo '</form>';
		} else {
			echo '<em>' . esc_html__( 'Enable the IndexNow module in the Dashboard tab to generate a key.', 'mm-seo' ) . '</em>';
		}
		echo '</td>';
		echo '</tr>';
		echo '</tbody></table>';

		// RSS before/after.
		echo '<h2>' . esc_html__( 'RSS Feed Additions', 'mm-seo' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr>';
		echo '<th scope="row"><label for="mmseo-rss-before">' . esc_html__( 'Content Before Each Post', 'mm-seo' ) . '</label></th>';
		echo '<td><textarea id="mmseo-rss-before" name="mmseo_settings[rss_before]" class="large-text" rows="4">'
			. esc_textarea( $settings['rss_before'] ?? '' ) . '</textarea></td>';
		echo '</tr>';
		echo '<tr>';
		echo '<th scope="row"><label for="mmseo-rss-after">' . esc_html__( 'Content After Each Post', 'mm-seo' ) . '</label></th>';
		echo '<td><textarea id="mmseo-rss-after" name="mmseo_settings[rss_after]" class="large-text" rows="4">'
			. esc_textarea( $settings['rss_after'] ?? '' ) . '</textarea></td>';
		echo '</tr>';
		echo '</tbody></table>';

		// Force output.
		echo '<h2>' . esc_html__( 'Conflict Handling', 'mm-seo' ) . '</h2>';
		echo '<p>';
		echo '<label><input type="checkbox" name="mmseo_settings[force_output]" value="1"'
			. checked( ! empty( $settings['force_output'] ), true, false ) . '>';
		echo ' <strong>' . esc_html__( 'Force output', 'mm-seo' ) . '</strong>';
		echo '</label>';
		echo '<br><span class="description">'
			. esc_html__( 'Output MM SEO meta tags even when another SEO plugin is active. Normally leave this off — MM SEO pauses its output automatically to prevent duplicate tags.', 'mm-seo' )
			. '</span>';
		echo '</p>';

		// Delete on uninstall.
		echo '<h2>' . esc_html__( 'Uninstall', 'mm-seo' ) . '</h2>';
		echo '<p>';
		echo '<label><input type="checkbox" name="mmseo_settings[delete_data_on_uninstall]" value="1"'
			. checked( ! empty( $settings['delete_data_on_uninstall'] ), true, false ) . '>';
		echo ' ' . esc_html__( 'Delete all MM SEO data when the plugin is uninstalled', 'mm-seo' );
		echo '</label>';
		echo '</p>';

		submit_button();
		echo '</form>';
	}

	/**
	 * Render the Tools tab.
	 */
	public function render_tools(): void {
		echo '<h2>' . esc_html__( 'Bulk SEO Analysis', 'mm-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Re-analyse all published posts and pages to update their SEO scores.', 'mm-seo' ) . '</p>';
		echo '<button type="button" class="button button-primary" id="mmseo-bulk-analyze-btn">'
			. esc_html__( 'Start Bulk Analysis', 'mm-seo' ) . '</button>';
		echo '<div id="mmseo-bulk-analyze-progress" style="margin-top:10px;display:none;">';
		echo '<progress id="mmseo-bulk-analyze-bar" value="0" max="100" style="width:100%;"></progress>';
		echo '<span id="mmseo-bulk-analyze-count"></span>';
		echo '</div>';

		// Localize nonce for bulk analyze.
		echo '<script>var MMSEOBulkAnalyze=' . wp_json_encode( [
			'nonce'  => wp_create_nonce( 'mmseo_bulk_analyze' ),
			'action' => 'mmseo_bulk_analyze',
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
		] ) . ';</script>';

		// Image alt audit.
		echo '<h2>' . esc_html__( 'Image Alt Audit', 'mm-seo' ) . '</h2>';

		// phpcs:ignore WordPress.Security.NonceVerification
		$do_audit = isset( $_GET['mmseo_alt_audit'] ) && '1' === $_GET['mmseo_alt_audit']; // phpcs:ignore WordPress.Security.NonceVerification
		echo '<p><a href="' . esc_url( add_query_arg( [ 'page' => 'mm-seo-tools', 'mmseo_alt_audit' => '1' ], admin_url( 'admin.php' ) ) ) . '" class="button">'
			. esc_html__( 'Run Image Alt Audit', 'mm-seo' ) . '</a></p>';

		if ( $do_audit && class_exists( ContentExtractor::class ) ) {
			$posts = get_posts( [
				'post_type'      => get_post_types( [ 'public' => true ] ),
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
			] );

			$rows = [];
			foreach ( $posts as $post_id ) {
				$post = get_post( $post_id );
				if ( ! $post ) {
					continue;
				}
				$content = ContentExtractor::extract( $post->post_content );
				$images  = $content['images'] ?? [];
				foreach ( $images as $img ) {
					if ( '' === $img['alt'] ) {
						$rows[] = [
							'post_id'    => $post_id,
							'post_title' => $post->post_title,
							'src'        => $img['src'],
						];
					}
				}
				if ( count( $rows ) >= 50 ) {
					break;
				}
			}

			if ( empty( $rows ) ) {
				echo '<div class="notice notice-success inline"><p>' . esc_html__( 'No images missing alt text found!', 'mm-seo' ) . '</p></div>';
			} else {
				echo '<table class="widefat striped"><thead><tr>';
				echo '<th>' . esc_html__( 'Post', 'mm-seo' ) . '</th>';
				echo '<th>' . esc_html__( 'Image', 'mm-seo' ) . '</th>';
				echo '</tr></thead><tbody>';
				foreach ( $rows as $row ) {
					echo '<tr>';
					echo '<td><a href="' . esc_url( get_edit_post_link( $row['post_id'] ) ) . '">'
						. esc_html( $row['post_title'] ) . '</a></td>';
					echo '<td><a href="' . esc_url( $row['src'] ) . '" target="_blank">' . esc_url( $row['src'] ) . '</a></td>';
					echo '</tr>';
				}
				echo '</tbody></table>';
			}
		}

		// Export.
		echo '<h2>' . esc_html__( 'Export Settings', 'mm-seo' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="mmseo_export_settings">';
		wp_nonce_field( 'mmseo_export', 'mmseo_export_nonce' );
		submit_button( __( 'Export Settings as JSON', 'mm-seo' ), 'secondary', 'submit', false );
		echo '</form>';

		// Import.
		echo '<h2>' . esc_html__( 'Import Settings', 'mm-seo' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="mmseo_import_settings">';
		wp_nonce_field( 'mmseo_import', 'mmseo_import_nonce' );
		echo '<textarea name="mmseo_import_data" class="large-text" rows="6" placeholder="' . esc_attr__( 'Paste exported JSON here…', 'mm-seo' ) . '"></textarea>';
		echo '<br>';
		submit_button( __( 'Import Settings', 'mm-seo' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	// -------------------------------------------------------------------------
	// Export / Import handlers
	// -------------------------------------------------------------------------

	/**
	 * Handle settings export.
	 */
	public function handle_export(): void {
		check_admin_referer( 'mmseo_export', 'mmseo_export_nonce' );
		if ( ! current_user_can( 'mmseo_manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mm-seo' ) );
		}

		$data = [
			'mmseo_settings' => get_option( 'mmseo_settings', [] ),
			'mmseo_titles'   => get_option( 'mmseo_titles', [] ),
			'mmseo_social'   => get_option( 'mmseo_social', [] ),
		];

		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="mm-seo-settings-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo wp_json_encode( $data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Handle settings import.
	 */
	public function handle_import(): void {
		check_admin_referer( 'mmseo_import', 'mmseo_import_nonce' );
		if ( ! current_user_can( 'mmseo_manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mm-seo' ) );
		}

		$raw  = isset( $_POST['mmseo_import_data'] ) ? wp_unslash( $_POST['mmseo_import_data'] ) : '';
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			wp_safe_redirect( add_query_arg( 'mmseo_notice', 'import_error', admin_url( 'admin.php?page=mm-seo-tools' ) ) );
			exit;
		}

		$allowed_keys = [ 'mmseo_settings', 'mmseo_titles', 'mmseo_social' ];

		foreach ( $allowed_keys as $key ) {
			if ( isset( $data[ $key ] ) && is_array( $data[ $key ] ) ) {
				update_option( $key, $data[ $key ] );
			}
		}

		wp_safe_redirect( add_query_arg( 'mmseo_notice', 'import_done', admin_url( 'admin.php?page=mm-seo-tools' ) ) );
		exit;
	}

	/**
	 * Handle IndexNow key regeneration.
	 */
	public function handle_regen_indexnow(): void {
		check_admin_referer( 'mmseo_regen_indexnow' );
		if ( ! current_user_can( 'mmseo_manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'mm-seo' ) );
		}
		$new_key = wp_generate_password( 32, false );
		update_option( 'mmseo_indexnow_key', $new_key );
		wp_safe_redirect( add_query_arg( 'mmseo_notice', 'indexnow_regen', admin_url( 'admin.php?page=mm-seo-advanced' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// Admin notices
	// -------------------------------------------------------------------------

	/**
	 * Display admin notices based on query var.
	 */
	public function show_notices(): void {
		if ( ! isset( $_GET['mmseo_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}

		$notice  = sanitize_key( $_GET['mmseo_notice'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$message = '';
		$type    = 'success';

		switch ( $notice ) {
			case 'sitemap_flushed':
				$message = __( 'Sitemap cache flushed successfully.', 'mm-seo' );
				break;
			case 'export_done':
				$message = __( 'Settings exported successfully.', 'mm-seo' );
				break;
			case 'import_done':
				$message = __( 'Settings imported successfully.', 'mm-seo' );
				break;
			case 'import_error':
				$message = __( 'Import failed: invalid JSON data.', 'mm-seo' );
				$type    = 'error';
				break;
			case 'indexnow_regen':
				$message = __( 'IndexNow key regenerated.', 'mm-seo' );
				break;
		}

		if ( '' !== $message ) {
			echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>'
				. esc_html( $message ) . '</p></div>';
		}
	}

	// -------------------------------------------------------------------------
	// Sanitize callbacks
	// -------------------------------------------------------------------------

	/**
	 * Sanitize mmseo_settings.
	 *
	 * @param mixed $input Raw input array.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			$input = [];
		}

		$defaults = Options::DEFAULT_SETTINGS;
		$output   = $defaults;

		// Modules: booleans.
		if ( isset( $input['modules'] ) && is_array( $input['modules'] ) ) {
			foreach ( $defaults['modules'] as $id => $_ ) {
				$output['modules'][ $id ] = ! empty( $input['modules'][ $id ] );
			}
		}

		// Separator whitelist.
		if ( isset( $input['separator'] ) && in_array( $input['separator'], self::SEPARATORS, true ) ) {
			$output['separator'] = $input['separator'];
		}

		// Site type.
		if ( isset( $input['site_type'] ) && in_array( $input['site_type'], [ 'org', 'person' ], true ) ) {
			$output['site_type'] = $input['site_type'];
		}

		// Org name.
		if ( isset( $input['org_name'] ) ) {
			$output['org_name'] = sanitize_text_field( $input['org_name'] );
		}

		// Org logo ID.
		if ( isset( $input['org_logo_id'] ) ) {
			$output['org_logo_id'] = absint( $input['org_logo_id'] );
		}

		// Social profiles.
		if ( isset( $input['social_profiles'] ) && is_array( $input['social_profiles'] ) ) {
			foreach ( $defaults['social_profiles'] as $key => $_ ) {
				$output['social_profiles'][ $key ] = isset( $input['social_profiles'][ $key ] )
					? esc_url_raw( $input['social_profiles'][ $key ] )
					: '';
			}
		}

		// Verification codes.
		if ( isset( $input['verification'] ) && is_array( $input['verification'] ) ) {
			foreach ( $defaults['verification'] as $key => $_ ) {
				$raw = $input['verification'][ $key ] ?? '';
				// If full meta tag, extract content= value.
				if ( preg_match( '/content=["\']([^"\']+)["\']/', $raw, $m ) ) {
					$output['verification'][ $key ] = sanitize_text_field( $m[1] );
				} else {
					$output['verification'][ $key ] = sanitize_text_field( wp_strip_all_tags( $raw ) );
				}
			}
		}

		// Delete data on uninstall.
		$output['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		// Force output (allow MM SEO tags even when another SEO plugin is active).
		$output['force_output'] = ! empty( $input['force_output'] );

		// RSS before/after.
		if ( isset( $input['rss_before'] ) ) {
			$output['rss_before'] = wp_kses_post( $input['rss_before'] );
		}
		if ( isset( $input['rss_after'] ) ) {
			$output['rss_after'] = wp_kses_post( $input['rss_after'] );
		}

		return $output;
	}

	/**
	 * Sanitize mmseo_titles.
	 *
	 * @param mixed $input Raw input array.
	 * @return array<string, mixed>
	 */
	public function sanitize_titles( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return Options::DEFAULT_TITLES;
		}

		$output = Options::titles();

		// Post types.
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $pt_name => $pt_vals ) {
				$pt_name = sanitize_key( $pt_name );
				if ( ! is_array( $pt_vals ) ) {
					continue;
				}
				$output['post_types'][ $pt_name ]['title']       = sanitize_text_field( $pt_vals['title'] ?? '' );
				$output['post_types'][ $pt_name ]['desc']        = sanitize_text_field( $pt_vals['desc'] ?? '' );
				$output['post_types'][ $pt_name ]['noindex']     = (bool) (int) ( $pt_vals['noindex'] ?? 0 );
				$output['post_types'][ $pt_name ]['in_sitemap']  = (bool) (int) ( $pt_vals['in_sitemap'] ?? 0 );
				$schema = sanitize_text_field( $pt_vals['schema_type'] ?? '' );
				$output['post_types'][ $pt_name ]['schema_type'] = in_array( $schema, self::SCHEMA_TYPES, true ) ? $schema : '';
			}
		}

		// Taxonomies.
		if ( isset( $input['taxonomies'] ) && is_array( $input['taxonomies'] ) ) {
			foreach ( $input['taxonomies'] as $tax_name => $tax_vals ) {
				$tax_name = sanitize_key( $tax_name );
				if ( ! is_array( $tax_vals ) ) {
					continue;
				}
				$output['taxonomies'][ $tax_name ]['title']      = sanitize_text_field( $tax_vals['title'] ?? '' );
				$output['taxonomies'][ $tax_name ]['desc']       = sanitize_text_field( $tax_vals['desc'] ?? '' );
				$output['taxonomies'][ $tax_name ]['noindex']    = (bool) (int) ( $tax_vals['noindex'] ?? 0 );
				$output['taxonomies'][ $tax_name ]['in_sitemap'] = (bool) (int) ( $tax_vals['in_sitemap'] ?? 0 );
			}
		}

		// Archives.
		if ( isset( $input['archives'] ) && is_array( $input['archives'] ) ) {
			$allowed_archive_keys = [ 'author', 'date', 'search', '404' ];
			foreach ( $input['archives'] as $arc_key => $arc_vals ) {
				if ( ! in_array( $arc_key, $allowed_archive_keys, true ) ) {
					continue;
				}
				if ( ! is_array( $arc_vals ) ) {
					continue;
				}
				$output['archives'][ $arc_key ]['title']   = sanitize_text_field( $arc_vals['title'] ?? '' );
				$output['archives'][ $arc_key ]['desc']    = sanitize_text_field( $arc_vals['desc'] ?? '' );
				$output['archives'][ $arc_key ]['noindex'] = (bool) (int) ( $arc_vals['noindex'] ?? 0 );
			}
		}

		// Home.
		if ( isset( $input['home'] ) && is_array( $input['home'] ) ) {
			$output['home']['title'] = sanitize_text_field( $input['home']['title'] ?? '' );
			$output['home']['desc']  = sanitize_text_field( $input['home']['desc'] ?? '' );
		}

		return $output;
	}

	/**
	 * Sanitize mmseo_social.
	 *
	 * @param mixed $input Raw input array.
	 * @return array<string, mixed>
	 */
	public function sanitize_social( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return Options::DEFAULT_SOCIAL;
		}

		$output                       = Options::social();
		$output['og_default_image']   = isset( $input['og_default_image'] ) ? esc_url_raw( $input['og_default_image'] ) : '';
		$output['og_default_image_id']= isset( $input['og_default_image_id'] ) ? absint( $input['og_default_image_id'] ) : 0;
		$output['og_site_name']       = sanitize_text_field( $input['og_site_name'] ?? '' );
		$twitter_card                 = $input['twitter_card'] ?? 'summary_large_image';
		$output['twitter_card']       = in_array( $twitter_card, [ 'summary', 'summary_large_image' ], true ) ? $twitter_card : 'summary_large_image';
		$output['twitter_site']       = sanitize_text_field( $input['twitter_site'] ?? '' );

		return $output;
	}

	/**
	 * Sanitize mmseo_redirects.
	 *
	 * @param mixed $input Raw input — array of redirect rule arrays.
	 * @return array<int, array<string, mixed>>
	 */
	public function sanitize_redirects( mixed $input ): array {
		if ( ! is_array( $input ) ) {
			return [];
		}

		$output       = [];
		$allowed_codes = [ 301, 302, 307 ];

		foreach ( $input as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$src = sanitize_text_field( $rule['src'] ?? '' );

			// Source must start with /.
			if ( '' === $src || '/' !== substr( $src, 0, 1 ) ) {
				continue;
			}

			$dst  = esc_url_raw( $rule['dst'] ?? '' );
			$code = (int) ( $rule['code'] ?? 301 );

			if ( ! in_array( $code, $allowed_codes, true ) ) {
				$code = 301;
			}

			$regex = ! empty( $rule['regex'] );

			// Test regex validity.
			if ( $regex && false === @preg_match( '#' . $src . '#', '' ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$regex = false;
			}

			$output[] = [
				'src'   => $src,
				'dst'   => $dst,
				'code'  => $code,
				'regex' => $regex,
				'hits'  => absint( $rule['hits'] ?? 0 ),
				'last'  => sanitize_text_field( $rule['last'] ?? '' ),
			];
		}

		return array_values( $output );
	}

	// -------------------------------------------------------------------------
	// Private rendering helpers
	// -------------------------------------------------------------------------

	/**
	 * Render title + desc input fields for a given settings base key.
	 *
	 * @param string               $base    Settings key base e.g. 'mmseo_titles[home]'.
	 * @param array<string, mixed> $data    Current values.
	 */
	private function render_title_desc_fields( string $base, array $data ): void {
		echo '<table class="form-table" style="margin:0"><tbody>';

		// Title.
		$title_id = 'mmseo-' . sanitize_key( str_replace( [ '[', ']' ], '-', $base ) ) . '-title';
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $title_id ) . '">' . esc_html__( 'SEO Title', 'mm-seo' ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" id="' . esc_attr( $title_id ) . '" name="' . esc_attr( $base ) . '[title]" value="'
			. esc_attr( $data['title'] ?? '' ) . '" class="large-text mmseo-title-tpl-input">';
		echo '</td>';
		echo '</tr>';

		// Description.
		$desc_id = 'mmseo-' . sanitize_key( str_replace( [ '[', ']' ], '-', $base ) ) . '-desc';
		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $desc_id ) . '">' . esc_html__( 'Meta Description', 'mm-seo' ) . '</label></th>';
		echo '<td>';
		echo '<textarea id="' . esc_attr( $desc_id ) . '" name="' . esc_attr( $base ) . '[desc]" class="large-text mmseo-desc-tpl-input" rows="2">'
			. esc_textarea( $data['desc'] ?? '' ) . '</textarea>';
		echo '</td>';
		echo '</tr>';

		echo '</tbody></table>';
	}

	/**
	 * Render schema type dropdown.
	 *
	 * @param string $base          Settings key base.
	 * @param string $current_value Currently saved value.
	 */
	private function render_schema_type_select( string $base, string $current_value ): void {
		echo '<p>';
		echo '<label for="mmseo-schema-' . esc_attr( sanitize_key( $base ) ) . '">' . esc_html__( 'Default Schema Type', 'mm-seo' ) . '</label> ';
		echo '<select id="mmseo-schema-' . esc_attr( sanitize_key( $base ) ) . '" name="' . esc_attr( $base ) . '[schema_type]">';
		foreach ( self::SCHEMA_TYPES as $type ) {
			$label = '' === $type ? __( 'Default (inherit)', 'mm-seo' ) : $type;
			echo '<option value="' . esc_attr( $type ) . '"' . selected( $current_value, $type, false ) . '>'
				. esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '</p>';
	}

	/**
	 * Render an accordion/details section.
	 *
	 * @param string   $id       HTML id attribute.
	 * @param string   $label    Section heading.
	 * @param callable $callback Content renderer.
	 */
	private function render_accordion_section( string $id, string $label, callable $callback ): void {
		echo '<details id="' . esc_attr( $id ) . '" class="mmseo-accordion" style="border:1px solid #ccd0d4;padding:8px 12px;margin-bottom:8px;">';
		echo '<summary style="cursor:pointer;font-weight:600;">' . esc_html( $label ) . '</summary>';
		echo '<div class="mmseo-accordion-body" style="padding-top:8px;">';
		$callback();
		echo '</div>';
		echo '</details>';
	}

	/**
	 * Render variable inserter widget.
	 */
	private function render_variable_inserter(): void {
		$vars = [
			'%%title%%'       => __( 'Post / Page Title', 'mm-seo' ),
			'%%sep%%'         => __( 'Separator', 'mm-seo' ),
			'%%sitename%%'    => __( 'Site Name', 'mm-seo' ),
			'%%tagline%%'     => __( 'Site Tagline', 'mm-seo' ),
			'%%excerpt%%'     => __( 'Post Excerpt', 'mm-seo' ),
			'%%date%%'        => __( 'Date', 'mm-seo' ),
			'%%author%%'      => __( 'Author', 'mm-seo' ),
			'%%term_title%%'  => __( 'Term Title', 'mm-seo' ),
			'%%page%%'        => __( 'Page Number', 'mm-seo' ),
		];

		echo '<div class="mmseo-variable-inserter" style="margin-bottom:12px;">';
		echo '<label>' . esc_html__( 'Insert variable:', 'mm-seo' ) . ' ';
		echo '<select id="mmseo-var-select">';
		foreach ( $vars as $tag => $desc ) {
			echo '<option value="' . esc_attr( $tag ) . '">' . esc_html( $tag ) . ' &mdash; ' . esc_html( $desc ) . '</option>';
		}
		echo '</select>';
		echo ' <button type="button" class="button" id="mmseo-var-insert">' . esc_html__( 'Insert', 'mm-seo' ) . '</button>';
		echo '</label>';
		echo '</div>';
		echo '<script>
(function(){
	document.addEventListener("DOMContentLoaded",function(){
		var btn=document.getElementById("mmseo-var-insert");
		var sel=document.getElementById("mmseo-var-select");
		var last=null;
		document.querySelectorAll(".mmseo-title-tpl-input,.mmseo-desc-tpl-input").forEach(function(el){
			el.addEventListener("focus",function(){last=el;});
		});
		if(btn&&sel){
			btn.addEventListener("click",function(){
				if(last){
					var s=last.selectionStart,e=last.selectionEnd,v=sel.value;
					last.value=last.value.substring(0,s)+v+last.value.substring(e);
					last.selectionStart=last.selectionEnd=s+v.length;
					last.focus();
				}
			});
		}
	});
})();
</script>';
	}

	/**
	 * Render a single redirect table row.
	 *
	 * @param int                  $i    Row index.
	 * @param array<string, mixed> $rule Redirect rule data.
	 */
	private function render_redirect_row( int $i, array $rule ): void {
		echo '<tr>';
		echo '<td><input type="text" name="mmseo_redirects[' . esc_attr( (string) $i ) . '][src]" value="'
			. esc_attr( $rule['src'] ?? '' ) . '" class="regular-text" placeholder="/old-path"></td>';
		echo '<td><input type="text" name="mmseo_redirects[' . esc_attr( (string) $i ) . '][dst]" value="'
			. esc_attr( $rule['dst'] ?? '' ) . '" class="regular-text" placeholder="https://"></td>';
		echo '<td><select name="mmseo_redirects[' . esc_attr( (string) $i ) . '][code]">';
		foreach ( [ 301, 302, 307 ] as $code ) {
			echo '<option value="' . esc_attr( (string) $code ) . '"'
				. selected( (int) ( $rule['code'] ?? 301 ), $code, false ) . '>' . esc_html( (string) $code ) . '</option>';
		}
		echo '</select></td>';
		echo '<td><input type="checkbox" name="mmseo_redirects[' . esc_attr( (string) $i ) . '][regex]" value="1"'
			. checked( ! empty( $rule['regex'] ), true, false ) . '></td>';
		echo '<td><input type="text" readonly value="' . esc_attr( (string) ( $rule['hits'] ?? 0 ) ) . '" style="width:60px;"></td>';
		echo '<td><input type="checkbox" name="mmseo_redirects_delete[]" value="' . esc_attr( (string) $i ) . '"></td>';
		echo '</tr>';
	}
}
