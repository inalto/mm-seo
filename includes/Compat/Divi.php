<?php
namespace MMSEO\Compat;

defined( 'ABSPATH' ) || exit;

/**
 * Divi theme compatibility.
 *
 * Loads the WPSEO_Frontend stub on Divi sites so Divi's et_is_seo_plugin_active()
 * continues to recognise an active SEO plugin and does not re-enable its own ePanel
 * SEO title/description output.
 *
 * This is a Divi shim — it is independent of Yoast SEO. The stub merely impersonates
 * the WPSEO_Frontend class name that Divi's legacy check looks for, so Divi keeps its
 * built-in ePanel SEO output disabled while MM SEO handles all meta tags instead.
 *
 * The stub is loaded only when:
 *   - The active theme is Divi (template = 'Divi'), AND
 *   - No conflicting third-party SEO plugin is active (they already define WPSEO_Frontend
 *     or equivalent, so the stub would be redundant), AND
 *   - The real WPSEO_Frontend class is NOT already defined (avoids redeclaration if
 *     Yoast SEO or another plugin has already defined it).
 */
class Divi {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'after_setup_theme', [ $this, 'maybe_load_stub' ], 20 );
	}

	/**
	 * Load the WPSEO_Frontend stub when Divi is the active theme and no other
	 * SEO plugin is already providing the class Divi looks for.
	 */
	public function maybe_load_stub(): void {
		if ( 'Divi' !== get_template() ) {
			return;
		}

		// Skip if another SEO plugin is active — it will already satisfy Divi's check.
		if ( ! empty( SeoConflicts::detect() ) ) {
			return;
		}

		// Skip if the real WPSEO_Frontend class is already defined.
		if ( class_exists( 'WPSEO_Frontend', false ) ) {
			return;
		}

		require_once dirname( __DIR__ ) . '/Compat/wpseo-frontend-stub.php';
	}
}
