<?php
namespace MMSEO\Modules;

use MMSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Site-verification module.
 *
 * Outputs verification meta tags for Google, Bing, Yandex, and Pinterest.
 * Tags are hooked to 'mmseo_head' (fired by Frontend\Head::output between the
 * MM SEO comment markers) rather than directly on wp_head.
 *
 * Tags are printed only on the front page; verification crawlers fetch the
 * home page and that keeps the markup clean on inner pages.
 *
 * Verification codes are stored in mmseo_settings under:
 *   verification.google   → google-site-verification
 *   verification.bing     → msvalidate.01
 *   verification.yandex   → yandex-verification
 *   verification.pinterest → p:domain_verify
 */
class Verification extends Module {

	public function id(): string {
		return 'verification';
	}

	public function register(): void {
		add_action( 'mmseo_head', [ $this, 'output_tags' ] );
	}

	// -------------------------------------------------------------------------
	// Output
	// -------------------------------------------------------------------------

	/**
	 * Print verification meta tags on the front page.
	 */
	public function output_tags(): void {
		if ( ! is_front_page() ) {
			return;
		}

		$tags = [
			'google-site-verification' => Options::get( 'verification.google', '' ),
			'msvalidate.01'            => Options::get( 'verification.bing', '' ),
			'yandex-verification'      => Options::get( 'verification.yandex', '' ),
			'p:domain_verify'          => Options::get( 'verification.pinterest', '' ),
		];

		foreach ( $tags as $name => $content ) {
			$content = trim( (string) $content );
			if ( '' === $content ) {
				continue;
			}
			printf(
				"\t<meta name=\"%s\" content=\"%s\" />\n",
				esc_attr( $name ),
				esc_attr( $content )
			);
		}
	}
}
