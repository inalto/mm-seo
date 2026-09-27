<?php
/**
 * Check: SEO Title Length.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that the SEO title is within the recommended character range (30–60).
 *
 * ID: title_length
 */
class TitleLength extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'title_length';
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
		$title = (string) ( $context['title'] ?? '' );
		$len   = mb_strlen( $title );

		if ( 0 === $len ) {
			return [
				'status' => self::STATUS_BAD,
				'score'  => 1,
				'text'   => __( 'The SEO title is empty. Add a descriptive title.', 'mm-seo' ),
			];
		}

		if ( $len >= 30 && $len <= 60 ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				/* translators: %d: title character count */
				'text'   => sprintf(
					__( 'The SEO title is %d characters — good length.', 'mm-seo' ),
					$len
				),
			];
		}

		if ( ( $len >= 20 && $len <= 29 ) || ( $len >= 61 && $len <= 70 ) ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 6,
				/* translators: %d: title character count */
				'text'   => sprintf(
					__( 'The SEO title is %d characters. For best results aim for 30–60 characters.', 'mm-seo' ),
					$len
				),
			];
		}

		return [
			'status' => self::STATUS_BAD,
			'score'  => 3,
			/* translators: %d: title character count */
			'text'   => sprintf(
				__( 'The SEO title is %d characters. Keep it between 30 and 60 characters.', 'mm-seo' ),
				$len
			),
		];
	}
}
