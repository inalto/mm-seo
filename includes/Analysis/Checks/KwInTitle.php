<?php
/**
 * Check: Keyword in SEO Title.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;
use MMSEO\ContentExtractor;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies that the focus keyword appears in the SEO title.
 *
 * IDs: kw_in_title
 */
class KwInTitle extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'kw_in_title';
	}

	/**
	 * {@inheritdoc}
	 */
	public function weight(): int {
		return 3;
	}

	/**
	 * {@inheritdoc} — belongs to the Basic SEO category.
	 */
	public function category(): string {
		return 'basic';
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $context
	 * @return array{status: string, score: int, text: string}
	 */
	public function run( array $context ): array {
		$kw    = (string) ( $context['focus_kw'] ?? '' );
		$title = (string) ( $context['title'] ?? '' );

		if ( '' === $kw ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				'text'   => __( 'Set a focus keyword to get keyword checks.', 'mm-seo' ),
			];
		}

		if ( ! ContentExtractor::keyword_match( $title, $kw ) ) {
			return [
				'status' => self::STATUS_BAD,
				'score'  => 2,
				'text'   => __( 'The focus keyword does not appear in the SEO title.', 'mm-seo' ),
			];
		}

		// Bonus: check if keyword appears in the first half.
		$title_len = mb_strlen( $title );
		$first_half = mb_substr( $title, 0, (int) ceil( $title_len / 2 ) );
		if ( ContentExtractor::keyword_match( $first_half, $kw ) ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				'text'   => __( 'The focus keyword appears at the beginning of the SEO title. Well done!', 'mm-seo' ),
			];
		}

		return [
			'status' => self::STATUS_GOOD,
			'score'  => 9,
			'text'   => __( 'The focus keyword appears in the SEO title.', 'mm-seo' ),
		];
	}
}
