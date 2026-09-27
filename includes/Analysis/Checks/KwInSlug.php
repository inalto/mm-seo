<?php
/**
 * Check: Keyword in Post Slug.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies that the focus keyword appears in the post slug.
 *
 * ID: kw_in_slug
 */
class KwInSlug extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'kw_in_slug';
	}

	/**
	 * {@inheritdoc}
	 */
	public function weight(): int {
		return 2;
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
		$kw   = (string) ( $context['focus_kw'] ?? '' );
		$slug = (string) ( $context['slug'] ?? '' );

		if ( '' === $kw ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				'text'   => __( 'Set a focus keyword to get keyword checks.', 'mm-seo' ),
			];
		}

		// Normalise slug: replace dashes with spaces for word matching.
		$slug_words    = str_replace( [ '-', '_' ], ' ', $slug );
		$slug_norm     = mb_strtolower( remove_accents( $slug_words ) );
		$kw_norm       = mb_strtolower( remove_accents( $kw ) );
		// Sanitize kw like a slug (spaces→dashes, remove non-ASCII) for comparison.
		$kw_slug_words = str_replace( [ '-', '_' ], ' ', sanitize_title( $kw ) );

		if ( str_contains( $slug_norm, $kw_norm )
			|| str_contains( $slug_norm, $kw_slug_words ) ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				'text'   => __( 'The focus keyword appears in the post slug.', 'mm-seo' ),
			];
		}

		return [
			'status' => self::STATUS_BAD,
			'score'  => 3,
			'text'   => __( 'The focus keyword does not appear in the post slug.', 'mm-seo' ),
		];
	}
}
