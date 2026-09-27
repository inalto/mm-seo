<?php
/**
 * Empty WPSEO_Frontend stub (no namespace — intentionally global).
 *
 * Purpose: this is a Divi compatibility shim, not a Yoast dependency.
 *
 * Divi's et_is_seo_plugin_active() function (epanel/custom_functions.php) detects
 * active SEO plugins by checking for the existence of well-known class names such as
 * WPSEO_Frontend. When no matching class is found, Divi re-enables its own ePanel SEO
 * output, causing duplicate <title> and meta description tags alongside MM SEO.
 *
 * Loading this stub makes Divi believe a recognised SEO plugin is handling output, so
 * Divi's built-in SEO stays suppressed while MM SEO takes over instead.
 *
 * This file is loaded conditionally (only on Divi sites, only when no conflicting
 * third-party SEO plugin is active, and only when WPSEO_Frontend is not already defined).
 * It has no runtime effect beyond class_exists( 'WPSEO_Frontend' ) returning true.
 */

if ( ! class_exists( 'WPSEO_Frontend', false ) ) {
	class WPSEO_Frontend {
		/**
		 * Return a singleton-like instance (satisfies et_is_seo_plugin_active() checks).
		 *
		 * @return static
		 */
		public static function get_instance() {
			return new self();
		}

		/**
		 * Absorb any instance method calls without error.
		 *
		 * @param string  $name Method name.
		 * @param mixed[] $args Arguments.
		 * @return null
		 */
		public function __call( $name, $args ) {
			return null;
		}

		/**
		 * Absorb any static method calls without error.
		 *
		 * @param string  $name Method name.
		 * @param mixed[] $args Arguments.
		 * @return null
		 */
		public static function __callStatic( $name, $args ) {
			return null;
		}
	}
}
