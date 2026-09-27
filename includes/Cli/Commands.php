<?php
namespace MMSEO\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI commands for MM SEO.
 *
 * Registered as: wp mmseo <subcommand>
 *
 * Subcommands:
 *   status           Show score distribution, missing-desc count, noindex count, module toggles.
 *   analyze          Re-analyze posts via Analyzer::analyze_and_store(). Accepts --all or --post=<id>.
 *   flush-sitemap    Flush the sitemap cache via Sitemaps::flush_cache().
 *
 * NOTE FOR COORDINATOR:
 * Plugin.php's ALWAYS/MODULES arrays do not include Cli\Commands, so the
 * autoloader will never load this file through the normal boot path.
 * This file self-registers via a cli_init action at the bottom.
 * No changes to Plugin.php or mm-seo.php are required — WordPress/WP-CLI
 * loads all plugin files normally, so the cli_init hook fires and the command
 * is registered before the REPL processes any input.
 */
class Commands {

	// -------------------------------------------------------------------------
	// status
	// -------------------------------------------------------------------------

	/**
	 * Show an SEO-health summary for this site.
	 *
	 * Outputs:
	 *   - Score distribution across public posts (green ≥71, amber 41–70, red ≤40, unscored).
	 *   - Count of posts missing a custom meta description (_mmseo_desc).
	 *   - Count of posts with noindex enabled (_mmseo_noindex = '1').
	 *   - Module on/off status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mmseo status
	 *
	 * @when after_wp_load
	 */
	public function status(): void {
		global $wpdb;

		$public_types = array_diff(
			get_post_types( [ 'public' => true ] ),
			[ 'attachment' ]
		);

		if ( empty( $public_types ) ) {
			\WP_CLI::error( __( 'No public post types found.', 'mm-seo' ) );
		}

		$placeholders = implode( ', ', array_fill( 0, count( $public_types ), '%s' ) );
		$types_args   = array_values( $public_types );

		// Score distribution.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$scores = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_mmseo_seo_score'
				AND p.post_status = 'publish'
				AND p.post_type IN ({$placeholders})",
				...$types_args
			)
		);

		$green   = 0;
		$amber   = 0;
		$red     = 0;
		$unscored = 0;

		// Count published posts with no score meta at all.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$total_published = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->posts}
				WHERE post_status = 'publish'
				AND post_type IN ({$placeholders})",
				...$types_args
			)
		);

		$scored_count = count( $scores );
		$unscored     = max( 0, $total_published - $scored_count );

		foreach ( $scores as $score ) {
			$s = (int) $score;
			if ( $s >= 71 ) {
				$green++;
			} elseif ( $s >= 41 ) {
				$amber++;
			} else {
				$red++;
			}
		}

		\WP_CLI::line( '' );
		\WP_CLI::line( '=== MM SEO Status ===' );
		\WP_CLI::line( '' );
		\WP_CLI::line( sprintf( __( 'Published posts: %d', 'mm-seo' ), $total_published ) );
		\WP_CLI::line( '' );

		$score_rows = [
			[ 'status' => 'Green (≥71)',  'count' => $green ],
			[ 'status' => 'Amber (41–70)', 'count' => $amber ],
			[ 'status' => 'Red (≤40)',    'count' => $red ],
			[ 'status' => 'Unscored',     'count' => $unscored ],
		];
		\WP_CLI\Utils\format_items( 'table', $score_rows, [ 'status', 'count' ] );

		// Missing meta description.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$missing_desc = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->posts} p
				WHERE p.post_status = 'publish'
				AND p.post_type IN ({$placeholders})
				AND p.ID NOT IN (
					SELECT post_id FROM {$wpdb->postmeta}
					WHERE meta_key = '_mmseo_desc' AND meta_value != ''
				)",
				...$types_args
			)
		);

		\WP_CLI::line( '' );
		\WP_CLI::line( sprintf( __( 'Posts missing custom meta description: %d', 'mm-seo' ), $missing_desc ) );

		// Noindex count.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$noindex_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_mmseo_noindex'
				AND pm.meta_value = '1'
				AND p.post_status = 'publish'
				AND p.post_type IN ({$placeholders})",
				...$types_args
			)
		);

		\WP_CLI::line( sprintf( __( 'Published posts set to noindex: %d', 'mm-seo' ), $noindex_count ) );

		// Module toggles.
		\WP_CLI::line( '' );
		\WP_CLI::line( '--- Module Status ---' );

		$modules = \MMSEO\Options::get( 'modules', [] );
		$module_rows = [];
		foreach ( (array) $modules as $id => $enabled ) {
			$module_rows[] = [
				'module'  => $id,
				'enabled' => $enabled ? 'yes' : 'no',
			];
		}
		\WP_CLI\Utils\format_items( 'table', $module_rows, [ 'module', 'enabled' ] );
		\WP_CLI::line( '' );
	}

	// -------------------------------------------------------------------------
	// analyze
	// -------------------------------------------------------------------------

	/**
	 * Re-analyze posts and store SEO scores.
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * : Re-analyze all published public posts in batches of 100.
	 *
	 * [--post=<id>]
	 * : Analyze a single post by ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mmseo analyze --post=42
	 *     wp mmseo analyze --all
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>    $args       Positional arguments (unused).
	 * @param array<string, string> $assoc_args Named arguments.
	 */
	public function analyze( array $args, array $assoc_args ): void {
		if ( ! class_exists( \MMSEO\Analysis\Analyzer::class ) ) {
			\WP_CLI::error( __( 'Analyzer class not available. Make sure Analysis phase is built.', 'mm-seo' ) );
		}

		// Single post.
		if ( isset( $assoc_args['post'] ) ) {
			$post_id = (int) $assoc_args['post'];
			$post    = get_post( $post_id );
			if ( ! $post ) {
				\WP_CLI::error( sprintf( __( 'Post %d not found.', 'mm-seo' ), $post_id ) );
			}
			\MMSEO\Analysis\Analyzer::analyze_and_store( $post_id );
			\WP_CLI::success( sprintf( __( 'Analyzed post %d.', 'mm-seo' ), $post_id ) );
			return;
		}

		// All posts.
		if ( isset( $assoc_args['all'] ) ) {
			$public_types = array_diff(
				get_post_types( [ 'public' => true ] ),
				[ 'attachment' ]
			);

			$query_args = [
				'post_type'      => array_values( $public_types ),
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'paged'          => 1,
				'fields'         => 'ids',
				'no_found_rows'  => false,
			];

			$first_query  = new \WP_Query( $query_args );
			$total        = (int) $first_query->found_posts;
			$processed    = 0;

			if ( 0 === $total ) {
				\WP_CLI::line( __( 'No published posts found.', 'mm-seo' ) );
				return;
			}

			$progress = \WP_CLI\Utils\make_progress_bar(
				sprintf( __( 'Analyzing %d posts…', 'mm-seo' ), $total ),
				$total
			);

			// Process first page.
			foreach ( $first_query->posts as $post_id ) {
				\MMSEO\Analysis\Analyzer::analyze_and_store( (int) $post_id );
				$progress->tick();
				$processed++;
			}

			// Subsequent pages.
			$total_pages = $first_query->max_num_pages;
			for ( $page = 2; $page <= $total_pages; $page++ ) {
				$query_args['paged'] = $page;
				$query = new \WP_Query( $query_args );
				foreach ( $query->posts as $post_id ) {
					\MMSEO\Analysis\Analyzer::analyze_and_store( (int) $post_id );
					$progress->tick();
					$processed++;
				}
				wp_reset_postdata();
			}

			$progress->finish();
			\WP_CLI::success( sprintf( __( 'Analyzed %d posts.', 'mm-seo' ), $processed ) );
			return;
		}

		\WP_CLI::error( __( 'Specify --all to analyze all posts or --post=<id> for a single post.', 'mm-seo' ) );
	}

	// -------------------------------------------------------------------------
	// flush-sitemap
	// -------------------------------------------------------------------------

	/**
	 * Flush the MM SEO sitemap cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp mmseo flush-sitemap
	 *
	 * @when after_wp_load
	 */
	public function flush_sitemap(): void {
		$class  = \MMSEO\Modules\Sitemaps\Sitemaps::class;
		$method = 'flush_cache';

		if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
			\WP_CLI::error( __( 'Sitemaps class not available. Make sure the Sitemaps module is built and enabled.', 'mm-seo' ) );
		}

		$class::$method();
		\WP_CLI::success( __( 'Sitemap cache flushed.', 'mm-seo' ) );
	}
}

// Self-register when WP-CLI is running.
// Plugin::boot() does not list Cli\Commands, so we hook ourselves here.
// This file is loaded by PHP's include path (autoloader fires on cli_init for the fqcn).
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'mmseo', Commands::class );
}
