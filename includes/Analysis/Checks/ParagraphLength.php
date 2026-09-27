<?php
/**
 * Check: Paragraph Length.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that no paragraph exceeds 200 words.
 *
 * ID: paragraph_length
 */
class ParagraphLength extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'paragraph_length';
	}

	/**
	 * {@inheritdoc}
	 */
	public function weight(): int {
		return 1;
	}

	/**
	 * {@inheritdoc} — belongs to the Readability category.
	 */
	public function category(): string {
		return 'readability';
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $context
	 * @return array{status: string, score: int, text: string}
	 */
	public function run( array $context ): array {
		$content = $context['content'] ?? [];
		$text    = (string) ( $content['text'] ?? '' );

		if ( '' === $text ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				'text'   => __( 'No content found to evaluate paragraph length.', 'mm-seo' ),
			];
		}

		// Split on two or more consecutive newlines (ContentExtractor uses newlines as paragraph separators).
		$paragraphs = preg_split( '/\n{2,}/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$paragraphs = $paragraphs ?: [];

		if ( empty( $paragraphs ) ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				'text'   => __( 'Paragraph length looks fine.', 'mm-seo' ),
			];
		}

		$long_paragraphs = 0;
		foreach ( $paragraphs as $paragraph ) {
			$words = preg_split( '/\s+/u', trim( $paragraph ), -1, PREG_SPLIT_NO_EMPTY );
			if ( count( $words ?: [] ) > 200 ) {
				$long_paragraphs++;
			}
		}

		if ( $long_paragraphs > 0 ) {
			return [
				'status' => self::STATUS_BAD,
				'score'  => 4,
				/* translators: %d: number of paragraphs exceeding 200 words */
				'text'   => sprintf(
					_n(
						'%d paragraph exceeds 200 words. Break it into smaller paragraphs for better readability.',
						'%d paragraphs exceed 200 words. Break them into smaller paragraphs for better readability.',
						$long_paragraphs,
						'mm-seo'
					),
					$long_paragraphs
				),
			];
		}

		return [
			'status' => self::STATUS_GOOD,
			'score'  => 9,
			'text'   => __( 'All paragraphs are within the recommended length.', 'mm-seo' ),
		];
	}
}
