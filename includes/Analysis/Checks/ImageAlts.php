<?php
/**
 * Check: Image Alt Attributes.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;
use MMSEO\ContentExtractor;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that all images have alt attributes, and that the focus keyword
 * appears in at least one alt text.
 *
 * ID: image_alts
 */
class ImageAlts extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'image_alts';
	}

	/**
	 * {@inheritdoc}
	 */
	public function weight(): int {
		return 2;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $context
	 * @return array{status: string, score: int, text: string}
	 */
	public function run( array $context ): array {
		$kw      = (string) ( $context['focus_kw'] ?? '' );
		$content = $context['content'] ?? [];
		$images  = $content['images'] ?? [];

		if ( empty( $images ) ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				'text'   => __( 'No images found. Add images to enrich your content.', 'mm-seo' ),
			];
		}

		$missing_alt    = 0;
		$kw_in_alt      = false;
		$total          = count( $images );

		foreach ( $images as $image ) {
			$alt = (string) ( $image['alt'] ?? '' );
			if ( '' === $alt ) {
				$missing_alt++;
			} elseif ( '' !== $kw && ContentExtractor::keyword_match( $alt, $kw ) ) {
				$kw_in_alt = true;
			}
		}

		if ( $missing_alt > 0 ) {
			return [
				'status' => self::STATUS_BAD,
				'score'  => 3,
				/* translators: %1$d: number of images missing alt, %2$d: total images */
				'text'   => sprintf(
					_n(
						'%1$d of %2$d image is missing an alt attribute.',
						'%1$d of %2$d images are missing alt attributes.',
						$missing_alt,
						'mm-seo'
					),
					$missing_alt,
					$total
				),
			];
		}

		// All images have alts.
		if ( '' !== $kw && ! $kw_in_alt ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 6,
				'text'   => __( 'All images have alt attributes, but the focus keyword does not appear in any of them. Consider adding it to one image alt.', 'mm-seo' ),
			];
		}

		return [
			'status' => self::STATUS_GOOD,
			'score'  => 9,
			'text'   => __( 'All images have alt attributes.', 'mm-seo' ),
		];
	}
}
