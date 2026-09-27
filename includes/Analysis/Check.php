<?php
namespace MMSEO\Analysis;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract base class for individual SEO analysis checks.
 *
 * Each check receives a $context array and returns a result array.
 *
 * Context keys:
 *   'title'     => string  — SEO title string
 *   'meta_desc' => string  — Meta description string
 *   'focus_kw'  => string  — Focus keyword
 *   'slug'      => string  — Post slug
 *   'permalink' => string  — Post permalink URL
 *   'content'   => array   — ContentExtractor::extract() result array
 *   'post'      => \WP_Post|null
 *
 * Return format:
 *   [
 *     'status' => 'good'|'ok'|'bad',
 *     'score'  => int (0–9),
 *     'text'   => string (translated human-readable message)
 *   ]
 */
abstract class Check {

	/** @var string Status constant: check passed. */
	public const STATUS_GOOD = 'good';

	/** @var string Status constant: check passed with caveats. */
	public const STATUS_OK = 'ok';

	/** @var string Status constant: check failed. */
	public const STATUS_BAD = 'bad';

	/**
	 * Return the unique check id (e.g. 'keyword_in_title').
	 *
	 * @return string
	 */
	abstract public function id(): string;

	/**
	 * Return the weight of this check in the overall score calculation.
	 * Higher values = more influence on the final score.
	 *
	 * @return int
	 */
	public function weight(): int {
		return 1;
	}

	/**
	 * Return the MM Score category this check belongs to.
	 *
	 * Categories: 'basic' | 'content' | 'readability'.
	 * Concrete checks that belong to 'basic' or 'readability' must override this.
	 * Content checks can rely on this default.
	 *
	 * @return string
	 */
	public function category(): string {
		return 'content';
	}

	/**
	 * Run the check against the provided context.
	 *
	 * @param array<string, mixed> $context Analysis context.
	 * @return array{status: string, score: int, text: string}
	 */
	abstract public function run( array $context ): array;
}
