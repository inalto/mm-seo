<?php
/**
 * SEO Analyzer: runs all registered checks and computes the branded MM Score.
 *
 * MM Score = weighted category mean, three categories:
 *   basic       (weight 50): kw_in_title, kw_in_metadesc, kw_in_slug, title_length, metadesc_length
 *   content     (weight 35): kw_in_first_paragraph, kw_in_headings, kw_density,
 *                            content_length, image_alts, internal_links, external_links
 *   readability (weight 15): sentence_length, paragraph_length
 *
 * @package MMSEO\Analysis
 */

namespace MMSEO\Analysis;

use MMSEO\ContentExtractor;
use MMSEO\Meta;
use MMSEO\Analysis\Checks\ContentLength;
use MMSEO\Analysis\Checks\ExternalLinks;
use MMSEO\Analysis\Checks\ImageAlts;
use MMSEO\Analysis\Checks\InternalLinks;
use MMSEO\Analysis\Checks\KwDensity;
use MMSEO\Analysis\Checks\KwInFirstParagraph;
use MMSEO\Analysis\Checks\KwInHeadings;
use MMSEO\Analysis\Checks\KwInMetadesc;
use MMSEO\Analysis\Checks\KwInSlug;
use MMSEO\Analysis\Checks\KwInTitle;
use MMSEO\Analysis\Checks\MetadescLength;
use MMSEO\Analysis\Checks\ParagraphLength;
use MMSEO\Analysis\Checks\SentenceLength;
use MMSEO\Analysis\Checks\TitleLength;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Runs all registered SEO checks and computes the weighted MM Score (0–100).
 */
class Analyzer {

	/**
	 * Register WordPress hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		// Store analysis on classic save_post (priority 20, after meta box saves at prio 10).
		add_action( 'save_post', [ $this, 'analyze_and_store' ], 20 );

		// Also hook REST insert events for Gutenberg saves.
		// We enumerate public post types at init priority 30 (after CPT registration).
		add_action( 'init', [ $this, 'hook_rest_insert' ], 30 );
	}

	/**
	 * Hook rest_after_insert_{post_type} for every public post type.
	 *
	 * @internal Called on init action.
	 * @return void
	 */
	public function hook_rest_insert(): void {
		$post_types = get_post_types( [ 'public' => true ] );
		foreach ( $post_types as $pt ) {
			add_action( "rest_after_insert_{$pt}", [ $this, 'analyze_and_store' ], 20 );
		}
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Build analysis context and run all checks for the given post, then store results.
	 *
	 * Accepts both a post ID (from save_post) and a WP_Post object (from rest_after_insert).
	 *
	 * @param int|WP_Post $post Post ID or WP_Post object.
	 * @return void
	 */
	public function analyze_and_store( int|WP_Post $post ): void {
		$post_id = $post instanceof WP_Post ? $post->ID : (int) $post;
		$post    = $post instanceof WP_Post ? $post : get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		// Skip autosaves, revisions, and auto-drafts.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( 'auto-draft' === $post->post_status ) {
			return;
		}

		// Skip non-public post types.
		$post_type_obj = get_post_type_object( $post->post_type );
		if ( ! $post_type_obj || ! $post_type_obj->public ) {
			return;
		}

		$result = self::analyze_post( $post_id );

		update_post_meta( $post_id, '_mmseo_seo_score', $result['score'] );
		update_post_meta( $post_id, '_mmseo_analysis', wp_json_encode( $result ) );
	}

	/**
	 * Analyse a post and return the full MM Score result array.
	 *
	 * Builds context from stored post data and stored meta, then runs all checks.
	 *
	 * @param int $post_id Post ID.
	 * @return array{score: int, rating: string, breakdown: array<string, array{score: int, label: string}>, checks: list<array{id: string, category: string, status: string, score: int, text: string}>}
	 */
	public static function analyze_post( int $post_id ): array {
		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post ) {
			return self::empty_result();
		}

		// Resolve SEO title: stored meta → fallback to post title.
		$title = Meta::get_post( $post_id, 'title' );
		if ( '' === $title ) {
			$title = get_the_title( $post );
		}

		$meta_desc = Meta::get_post( $post_id, 'desc' );
		$focus_kw  = Meta::get_post( $post_id, 'focuskw' );
		$slug      = $post->post_name;
		$content   = ContentExtractor::extract( $post->post_content );

		$context = [
			'title'     => $title,
			'meta_desc' => $meta_desc,
			'focus_kw'  => $focus_kw,
			'slug'      => $slug,
			'content'   => $content,
			'post'      => $post,
		];

		return self::run_checks( $context );
	}

	/**
	 * Run all registered checks against a context array and return the full MM Score result.
	 *
	 * This is the shared entry point used by both analyze_post() and RestController.
	 *
	 * Result shape:
	 *   score     int                — overall MM Score 0–100
	 *   rating    string             — 'excellent'|'good'|'fair'|'poor'
	 *   breakdown array              — per-category {score, label}
	 *   checks    list               — [{id, category, status, score, text}]
	 *
	 * @param array<string, mixed> $context Analysis context (title, meta_desc, focus_kw, slug, content, post).
	 * @return array{score: int, rating: string, breakdown: array<string, array{score: int, label: string}>, checks: list<array{id: string, category: string, status: string, score: int, text: string}>}
	 */
	public static function run_checks( array $context ): array {
		$check_instances = self::checks();

		/**
		 * Category weights (filterable). Keys: 'basic', 'content', 'readability'.
		 *
		 * @param array<string, int> $weights Category => weight.
		 */
		$cat_weights = (array) apply_filters(
			'mmseo_score_category_weights',
			[ 'basic' => 50, 'content' => 35, 'readability' => 15 ]
		);

		// Accumulators per category: weighted_sum and weight_total.
		$cat_sums    = [];
		$cat_totals  = [];
		$check_results = [];

		foreach ( array_keys( $cat_weights ) as $cat_id ) {
			$cat_sums[ $cat_id ]   = 0.0;
			$cat_totals[ $cat_id ] = 0.0;
		}

		foreach ( $check_instances as $check ) {
			$result   = $check->run( $context );
			$weight   = (float) $check->weight();
			$cat      = $check->category();

			// Guard against malformed check results.
			$score  = isset( $result['score'] )  ? (int)    $result['score']  : 0;
			$status = isset( $result['status'] )  ? (string) $result['status'] : Check::STATUS_BAD;
			$text   = isset( $result['text'] )    ? (string) $result['text']   : '';

			// Accumulate into the check's category (fall back to 'content' for unknown).
			$eff_cat = isset( $cat_sums[ $cat ] ) ? $cat : 'content';
			if ( isset( $cat_sums[ $eff_cat ] ) ) {
				$cat_sums[ $eff_cat ]   += $score * $weight;
				$cat_totals[ $eff_cat ] += $weight;
			}

			$check_results[] = [
				'id'       => $check->id(),
				'category' => $cat,
				'status'   => $status,
				'score'    => $score,
				'text'     => $text,
			];
		}

		// Compute per-category scores (0–100).
		$cat_scores = [];
		foreach ( array_keys( $cat_weights ) as $cat_id ) {
			if ( $cat_totals[ $cat_id ] > 0 ) {
				$cat_scores[ $cat_id ] = (int) round( $cat_sums[ $cat_id ] / $cat_totals[ $cat_id ] / 9 * 100 );
				$cat_scores[ $cat_id ] = max( 0, min( 100, $cat_scores[ $cat_id ] ) );
			} else {
				$cat_scores[ $cat_id ] = null; // No checks — redistribute.
			}
		}

		// Guard: redistribute weight of empty categories proportionally over remaining ones.
		$effective_weights = self::redistribute_weights( $cat_weights, $cat_scores );

		// Overall MM Score.
		$overall = 0.0;
		$total_w = array_sum( $effective_weights );
		if ( $total_w > 0 ) {
			foreach ( $effective_weights as $cat_id => $w ) {
				$s = $cat_scores[ $cat_id ] ?? 0;
				$overall += $s * ( $w / $total_w );
			}
		}
		$final_score = (int) round( $overall );
		$final_score = max( 0, min( 100, $final_score ) );

		// Build breakdown.
		$cat_defs  = self::categories();
		$breakdown = [];
		foreach ( $cat_defs as $cat_id => $def ) {
			$breakdown[ $cat_id ] = [
				'score' => $cat_scores[ $cat_id ] ?? 0,
				'label' => $def['label'],
			];
		}

		return [
			'score'     => $final_score,
			'rating'    => self::rating( $final_score ),
			'breakdown' => $breakdown,
			'checks'    => $check_results,
		];
	}

	/**
	 * Redistribute category weights so empty categories don't absorb score.
	 *
	 * When a category has no checks (after filtering), its weight is distributed
	 * proportionally across the remaining categories.
	 *
	 * @param array<string, int>       $weights    Original category weights.
	 * @param array<string, int|null>  $cat_scores Per-category score (null = no checks).
	 * @return array<string, float> Effective weights for categories that have checks.
	 */
	private static function redistribute_weights( array $weights, array $cat_scores ): array {
		$active = [];
		$orphan = 0.0;

		foreach ( $weights as $cat_id => $w ) {
			if ( null === $cat_scores[ $cat_id ] ) {
				$orphan += (float) $w;
			} else {
				$active[ $cat_id ] = (float) $w;
			}
		}

		if ( $orphan > 0 && ! empty( $active ) ) {
			$active_sum = array_sum( $active );
			foreach ( $active as $cat_id => $w ) {
				$active[ $cat_id ] = $w + $orphan * ( $w / $active_sum );
			}
		}

		return $active;
	}

	/**
	 * Return all registered check instances, filterable via 'mmseo_analysis_checks'.
	 *
	 * @return Check[]
	 */
	public static function checks(): array {
		$checks = [
			new KwInTitle(),
			new KwInSlug(),
			new KwInMetadesc(),
			new KwInFirstParagraph(),
			new KwInHeadings(),
			new KwDensity(),
			new TitleLength(),
			new MetadescLength(),
			new ContentLength(),
			new ImageAlts(),
			new InternalLinks(),
			new ExternalLinks(),
			new SentenceLength(),
			new ParagraphLength(),
		];

		/**
		 * Filter the list of SEO analysis check instances.
		 *
		 * @param Check[] $checks Array of Check instances.
		 */
		return (array) apply_filters( 'mmseo_analysis_checks', $checks );
	}

	/**
	 * Return the MM Score category definitions (id => label + weight).
	 *
	 * @return array<string, array{label: string, weight: int}>
	 */
	public static function categories(): array {
		return [
			'basic'       => [ 'label' => __( 'Basic SEO',    'mm-seo' ), 'weight' => 50 ],
			'content'     => [ 'label' => __( 'Content',      'mm-seo' ), 'weight' => 35 ],
			'readability' => [ 'label' => __( 'Readability',  'mm-seo' ), 'weight' => 15 ],
		];
	}

	/**
	 * Convert a 0–100 MM Score to a human-readable rating label key.
	 *
	 * >= 81 → 'excellent'
	 * >= 61 → 'good'
	 * >= 41 → 'fair'
	 * else  → 'poor'
	 *
	 * @param int $score Aggregated score (0–100).
	 * @return string 'excellent' | 'good' | 'fair' | 'poor'
	 */
	public static function rating( int $score ): string {
		if ( $score >= 81 ) { return 'excellent'; }
		if ( $score >= 61 ) { return 'good'; }
		if ( $score >= 41 ) { return 'fair'; }
		return 'poor';
	}

	/**
	 * Convert a 0–100 score to a traffic-light colour.
	 *
	 * Thresholds are intentionally different from rating() to preserve
	 * the existing list-table dot behaviour.
	 *
	 * @param int $score Aggregated score (0–100).
	 * @return string 'green' | 'amber' | 'red'
	 */
	public static function traffic_light( int $score ): string {
		if ( $score >= 71 ) {
			return 'green';
		}
		if ( $score >= 41 ) {
			return 'amber';
		}
		return 'red';
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Return an empty result structure for when no post is found.
	 *
	 * @return array{score: int, rating: string, breakdown: array, checks: array}
	 */
	private static function empty_result(): array {
		$breakdown = [];
		foreach ( self::categories() as $cat_id => $def ) {
			$breakdown[ $cat_id ] = [ 'score' => 0, 'label' => $def['label'] ];
		}
		return [ 'score' => 0, 'rating' => 'poor', 'breakdown' => $breakdown, 'checks' => [] ];
	}
}
