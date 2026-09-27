<?php
/**
 * Check: Keyword in Meta Description.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;
use MMSEO\ContentExtractor;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies that the focus keyword appears in the meta description.
 *
 * ID: kw_in_metadesc
 */
class KwInMetadesc extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'kw_in_metadesc';
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
		$desc = (string) ( $context['meta_desc'] ?? '' );

		if ( '' === $kw ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				'text'   => __( 'Set a focus keyword to get keyword checks.', 'mm-seo' ),
			];
		}

		if ( '' === $desc ) {
			return [
				'status' => self::STATUS_BAD,
				'score'  => 2,
				'text'   => __( 'No meta description set. Add one and include the focus keyword.', 'mm-seo' ),
			];
		}

		if ( ContentExtractor::keyword_match( $desc, $kw ) ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				'text'   => __( 'The focus keyword appears in the meta description.', 'mm-seo' ),
			];
		}

		return [
			'status' => self::STATUS_BAD,
			'score'  => 2,
			'text'   => __( 'The focus keyword does not appear in the meta description.', 'mm-seo' ),
		];
	}
}
