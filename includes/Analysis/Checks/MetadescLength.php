<?php
/**
 * Check: Meta Description Length.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that the meta description is within the recommended range (70–156 chars).
 *
 * ID: metadesc_length
 */
class MetadescLength extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'metadesc_length';
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
		$desc = (string) ( $context['meta_desc'] ?? '' );
		$len  = mb_strlen( $desc );

		if ( 0 === $len ) {
			return [
				'status' => self::STATUS_BAD,
				'score'  => 2,
				'text'   => __( 'No meta description set. Write one to improve click-through rates.', 'mm-seo' ),
			];
		}

		if ( $len >= 70 && $len <= 156 ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				/* translators: %d: description character count */
				'text'   => sprintf(
					__( 'The meta description is %d characters — good length.', 'mm-seo' ),
					$len
				),
			];
		}

		if ( $len > 156 ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				/* translators: %d: description character count */
				'text'   => sprintf(
					__( 'The meta description is %d characters and will be truncated by search engines. Keep it under 156 characters.', 'mm-seo' ),
					$len
				),
			];
		}

		// 1–69 chars
		return [
			'status' => self::STATUS_OK,
			'score'  => 5,
			/* translators: %d: description character count */
			'text'   => sprintf(
				__( 'The meta description is only %d characters. Aim for 70–156 characters.', 'mm-seo' ),
				$len
			),
		];
	}
}
