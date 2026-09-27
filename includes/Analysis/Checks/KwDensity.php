<?php
/**
 * Check: Keyword Density.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;
use MMSEO\ContentExtractor;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that keyword density is in the 0.5–3% range.
 *
 * ID: kw_density
 */
class KwDensity extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'kw_density';
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
		$text    = (string) ( $content['text'] ?? '' );
		$wc      = (int) ( $content['word_count'] ?? 0 );

		if ( '' === $kw ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				'text'   => __( 'Set a focus keyword to get keyword checks.', 'mm-seo' ),
			];
		}

		if ( $wc <= 0 ) {
			return [
				'status' => self::STATUS_BAD,
				'score'  => 2,
				'text'   => __( 'No content found. Add content to evaluate keyword density.', 'mm-seo' ),
			];
		}

		$occurrences = ContentExtractor::keyword_occurrences( $text, $kw );
		$density     = ( $occurrences / $wc ) * 100;

		if ( 0 === $occurrences ) {
			return [
				'status' => self::STATUS_BAD,
				'score'  => 2,
				'text'   => __( 'The focus keyword does not appear in the content.', 'mm-seo' ),
			];
		}

		if ( $density > 3.0 ) {
			return [
				'status' => self::STATUS_BAD,
				'score'  => 3,
				/* translators: %s: calculated keyword density percentage */
				'text'   => sprintf(
					__( 'Keyword density is %s%% — over-optimization. Aim for 0.5–3%%.', 'mm-seo' ),
					number_format( $density, 1 )
				),
			];
		}

		if ( $density >= 0.5 ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				/* translators: %s: calculated keyword density percentage */
				'text'   => sprintf(
					__( 'Keyword density is %s%% — good.', 'mm-seo' ),
					number_format( $density, 1 )
				),
			];
		}

		// 0 < density < 0.5
		return [
			'status' => self::STATUS_OK,
			'score'  => 5,
			/* translators: %s: calculated keyword density percentage */
			'text'   => sprintf(
				__( 'Keyword density is %s%% — too low. Aim for at least 0.5%%.', 'mm-seo' ),
				number_format( $density, 1 )
			),
		];
	}
}
