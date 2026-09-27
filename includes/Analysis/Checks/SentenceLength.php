<?php
/**
 * Check: Sentence Length.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that no more than 25% of sentences exceed 25 words.
 *
 * ID: sentence_length
 */
class SentenceLength extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'sentence_length';
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
				'status' => self::STATUS_OK,
				'score'  => 5,
				'text'   => __( 'No content found to evaluate sentence length.', 'mm-seo' ),
			];
		}

		// Split on sentence-ending punctuation followed by whitespace.
		$sentences = preg_split( '/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$sentences = $sentences ?: [];

		$total = count( $sentences );

		if ( $total < 3 ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				'text'   => __( 'Not enough sentences to evaluate sentence length. Add more content.', 'mm-seo' ),
			];
		}

		$long_count = 0;
		foreach ( $sentences as $sentence ) {
			$words = preg_split( '/\s+/u', trim( $sentence ), -1, PREG_SPLIT_NO_EMPTY );
			if ( count( $words ?: [] ) > 25 ) {
				$long_count++;
			}
		}

		$pct = ( $long_count / $total ) * 100;

		if ( $pct <= 25 ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				/* translators: %s: percentage of long sentences */
				'text'   => sprintf(
					__( '%s%% of sentences exceed 25 words — good readability.', 'mm-seo' ),
					number_format( $pct, 0 )
				),
			];
		}

		if ( $pct <= 40 ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				/* translators: %s: percentage of long sentences */
				'text'   => sprintf(
					__( '%s%% of sentences exceed 25 words. Try to shorten some sentences for better readability.', 'mm-seo' ),
					number_format( $pct, 0 )
				),
			];
		}

		return [
			'status' => self::STATUS_BAD,
			'score'  => 3,
			/* translators: %s: percentage of long sentences */
			'text'   => sprintf(
				__( '%s%% of sentences exceed 25 words — too many long sentences. Break them up for better readability.', 'mm-seo' ),
				number_format( $pct, 0 )
			),
		];
	}
}
