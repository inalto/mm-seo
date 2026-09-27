<?php
/**
 * WordPress Site Health integration for MM SEO.
 *
 * @package MMSEO
 */

namespace MMSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Class HealthCheck
 *
 * Adds an MM SEO configuration test to the WordPress Site Health screen.
 */
class HealthCheck {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_filter( 'site_status_tests', [ $this, 'add_site_health_test' ] );
	}

	/**
	 * Add the MM SEO test to the Site Health tests array.
	 *
	 * @param array $tests Existing tests.
	 * @return array Modified tests.
	 */
	public function add_site_health_test( $tests ) {
		$tests['direct']['mmseo_seo_health'] = [
			'label' => __( 'MM SEO configuration', 'mm-seo' ),
			'test'  => [ self::class, 'site_health_test' ],
		];

		return $tests;
	}

	/**
	 * Run the Site Health test and return a result array.
	 *
	 * @return array WP Site Health result.
	 */
	public static function site_health_test() {
		$checks = self::checks();
		$warns  = array_filter( $checks, static function ( $c ) {
			return 'warn' === $c['status'];
		} );

		if ( empty( $warns ) ) {
			return [
				'label'       => __( 'MM SEO is configured correctly', 'mm-seo' ),
				'status'      => 'good',
				'badge'       => [
					'label' => 'SEO',
					'color' => 'green',
				],
				'description' => '<p>' . esc_html__( 'No SEO configuration issues found.', 'mm-seo' ) . '</p>',
				'actions'     => '',
				'test'        => 'mmseo_seo_health',
			];
		}

		$description = '<p>' . esc_html__( 'The following SEO configuration issues were found:', 'mm-seo' ) . '</p><ul>';

		foreach ( $warns as $warn ) {
			$description .= '<li><strong>' . esc_html( $warn['label'] ) . '</strong> &mdash; ' . esc_html( $warn['text'] ) . '</li>';
		}

		$description .= '</ul>';

		return [
			'label'       => __( 'MM SEO has configuration issues', 'mm-seo' ),
			'status'      => 'recommended',
			'badge'       => [
				'label' => 'SEO',
				'color' => 'orange',
			],
			'description' => $description,
			'actions'     => '',
			'test'        => 'mmseo_seo_health',
		];
	}

	/**
	 * Return all health check items.
	 *
	 * Each item has keys: status ('good'|'warn'), label, text.
	 *
	 * @return array
	 */
	public static function checks() {
		$items = [];

		// 1. Conflicting SEO plugins.
		$conflicts    = \MMSEO\Compat\SeoConflicts::detect();
		$force_output = \MMSEO\Options::get( 'force_output', false );

		if ( ! empty( $conflicts ) ) {
			$labels = implode( ', ', array_values( $conflicts ) );
			if ( $force_output ) {
				$items[] = [
					'status' => 'warn',
					'label'  => __( 'Another SEO plugin is active (output forced)', 'mm-seo' ),
					/* translators: %s: comma-separated list of active SEO plugin names */
					'text'   => sprintf( __( '%s is active alongside MM SEO. Output is forced — expect duplicate tags until the other plugin is deactivated.', 'mm-seo' ), $labels ),
				];
			} else {
				$items[] = [
					'status' => 'warn',
					'label'  => __( 'Another SEO plugin is active (output paused)', 'mm-seo' ),
					/* translators: %s: comma-separated list of active SEO plugin names */
					'text'   => sprintf( __( '%s is active: MM SEO frontend output is paused to avoid duplicate tags. Deactivate the other SEO plugin to enable MM SEO output, or enable "Force output" in MM SEO → Advanced.', 'mm-seo' ), $labels ),
				];
			}
		} else {
			$items[] = [
				'status' => 'good',
				'label'  => __( 'No conflicting SEO plugins detected', 'mm-seo' ),
				'text'   => __( 'No other active SEO plugins were found.', 'mm-seo' ),
			];
		}

		// 2. Divi ePanel SEO.
		$et_divi    = get_option( 'et_divi', [] );
		$divi_keys  = [
			'divi_seo_home_title',
			'divi_seo_home_description',
			'divi_seo_single_title',
			'divi_seo_single_description',
			'divi_seo_index_title',
			'divi_seo_index_description',
		];
		$divi_on    = false;
		foreach ( $divi_keys as $divi_key ) {
			if ( isset( $et_divi[ $divi_key ] ) && 'on' === $et_divi[ $divi_key ] ) {
				$divi_on = true;
				break;
			}
		}

		if ( $divi_on ) {
			$items[] = [
				'status' => 'warn',
				'label'  => __( 'Divi ePanel SEO active', 'mm-seo' ),
				'text'   => __( "Divi's built-in SEO fields are enabled and may output duplicate tags. Disable Divi SEO in Theme Options \u{2192} SEO.", 'mm-seo' ),
			];
		} else {
			$items[] = [
				'status' => 'good',
				'label'  => __( 'Divi ePanel SEO not conflicting', 'mm-seo' ),
				'text'   => __( 'Divi SEO fields are not active.', 'mm-seo' ),
			];
		}

		// 3. Permalink structure.
		if ( '' === get_option( 'permalink_structure' ) ) {
			$items[] = [
				'status' => 'warn',
				'label'  => __( 'Plain permalinks', 'mm-seo' ),
				'text'   => __( 'MM SEO sitemaps require pretty permalinks. Go to Settings > Permalinks and choose a non-plain structure.', 'mm-seo' ),
			];
		} else {
			$items[] = [
				'status' => 'good',
				'label'  => __( 'Permalink structure', 'mm-seo' ),
				'text'   => __( 'Pretty permalinks are enabled.', 'mm-seo' ),
			];
		}

		// 4. WordPress core sitemap conflict.
		if ( ! \MMSEO\Options::module_enabled( 'sitemaps' ) && function_exists( 'get_sitemap_url' ) ) {
			$items[] = [
				'status' => 'warn',
				'label'  => __( 'WordPress core sitemap active', 'mm-seo' ),
				'text'   => __( 'The WordPress core sitemap is active. Enable the MM SEO Sitemaps module to replace it.', 'mm-seo' ),
			];
		} else {
			$items[] = [
				'status' => 'good',
				'label'  => __( 'Sitemap configuration', 'mm-seo' ),
				'text'   => __( 'No sitemap conflict detected.', 'mm-seo' ),
			];
		}

		// 5. Physical robots.txt.
		if ( \MMSEO\Options::module_enabled( 'robots_editor' ) && file_exists( ABSPATH . 'robots.txt' ) ) {
			$items[] = [
				'status' => 'warn',
				'label'  => __( 'Physical robots.txt exists', 'mm-seo' ),
				'text'   => __( 'A physical robots.txt file exists and overrides the virtual one managed by MM SEO. Remove the file from the server root to let MM SEO control robots directives.', 'mm-seo' ),
			];
		} else {
			$items[] = [
				'status' => 'good',
				'label'  => __( 'robots.txt configuration', 'mm-seo' ),
				'text'   => __( 'No physical robots.txt conflict detected.', 'mm-seo' ),
			];
		}

		return $items;
	}
}
