<?php
/**
 * Check: Keyword in First Paragraph.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;
use MMSEO\ContentExtractor;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies that the focus keyword appears in the first paragraph of the content.
 *
 * ID: kw_in_first_paragraph
 */
class KwInFirstParagraph extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'kw_in_first_paragraph';
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
		$kw              = (string) ( $context['focus_kw'] ?? '' );
		$content         = $context['content'] ?? [];
		$first_paragraph = (string) ( $content['first_paragraph'] ?? '' );

		if ( '' === $kw ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				'text'   => __( 'Set a focus keyword to get keyword checks.', 'mm-seo' ),
			];
		}

		if ( ContentExtractor::keyword_match( $first_paragraph, $kw ) ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				'text'   => __( 'The focus keyword appears in the first paragraph.', 'mm-seo' ),
			];
		}

		return [
			'status' => self::STATUS_BAD,
			'score'  => 3,
			'text'   => __( 'The focus keyword does not appear in the first paragraph. Add it to the opening of your content.', 'mm-seo' ),
		];
	}
}
