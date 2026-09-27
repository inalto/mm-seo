<?php
/**
 * Check: Content Length.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that the content is long enough.
 * Minimum threshold filterable via 'mmseo_min_content_words'.
 *
 * ID: content_length
 */
class ContentLength extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'content_length';
	}

	/**
	 * {@inheritdoc}
	 */
	public function weight(): int {
		return 3;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $context
	 * @return array{status: string, score: int, text: string}
	 */
	public function run( array $context ): array {
		$content = $context['content'] ?? [];
		$wc      = (int) ( $content['word_count'] ?? 0 );

		/**
		 * Filter the minimum word count considered acceptable content length.
		 *
		 * @param int $min Minimum word count. Default 150.
		 */
		$min = (int) apply_filters( 'mmseo_min_content_words', 150 );

		if ( $wc >= 900 ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				/* translators: %d: word count */
				'text'   => sprintf(
					__( 'The content is %d words — excellent length.', 'mm-seo' ),
					$wc
				),
			];
		}

		if ( $wc >= 300 ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 8,
				/* translators: %d: word count */
				'text'   => sprintf(
					__( 'The content is %d words — good length. Consider expanding to 900+ words for better rankings.', 'mm-seo' ),
					$wc
				),
			];
		}

		if ( $wc >= $min ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				/* translators: %d: word count */
				'text'   => sprintf(
					__( 'The content is %d words. Aim for at least 300 words for better SEO.', 'mm-seo' ),
					$wc
				),
			];
		}

		return [
			'status' => self::STATUS_BAD,
			'score'  => 2,
			/* translators: %d: word count */
			'text'   => sprintf(
				__( 'The content is only %d words — too short. Write at least 300 words.', 'mm-seo' ),
				$wc
			),
		];
	}
}
