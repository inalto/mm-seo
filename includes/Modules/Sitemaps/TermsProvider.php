<?php
namespace MMSEO\Modules\Sitemaps;

use MMSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Sitemap provider for public taxonomies.
 *
 * One sitemap per enabled public taxonomy; excludes noindexed taxonomies
 * and terms marked as noindex via term meta.
 * No lastmod (term modification date is not reliably tracked in WP core).
 */
class TermsProvider extends Provider {

	/**
	 * Return sitemap slugs: one per enabled public taxonomy.
	 *
	 * @return string[]
	 */
	public function types(): array {
		$types = [];

		foreach ( $this->get_enabled_taxonomies() as $tax ) {
			$types[] = $tax;
		}

		return $types;
	}

	/**
	 * Return index entries for the sitemap index.
	 *
	 * @return array<int, array{loc: string, lastmod: string|null}>
	 */
	public function index_entries(): array {
		$entries = [];

		foreach ( $this->get_enabled_taxonomies() as $tax ) {
			$count = $this->count_terms( $tax );
			if ( 0 === $count ) {
				continue;
			}

			$per_page = $this->per_page();
			$pages    = (int) ceil( $count / $per_page );

			for ( $p = 1; $p <= $pages; $p++ ) {
				$loc = $p > 1
					? home_url( '/' . $tax . '-sitemap' . $p . '.xml' )
					: home_url( '/' . $tax . '-sitemap.xml' );

				$entries[] = [
					'loc'     => $loc,
					'lastmod' => null,
				];
			}
		}

		return $entries;
	}

	/**
	 * Return URL entries for a specific taxonomy sitemap page.
	 *
	 * @param string $type Taxonomy slug.
	 * @param int    $page 1-based page number.
	 * @return array<int, array{loc: string, lastmod: string|null, images: array}>
	 */
	public function page( string $type, int $page ): array {
		if ( ! taxonomy_exists( $type ) ) {
			return [];
		}

		$enabled = $this->get_enabled_taxonomies();
		if ( ! in_array( $type, $enabled, true ) ) {
			return [];
		}

		$per_page = $this->per_page();
		$offset   = ( $page - 1 ) * $per_page;

		$terms = get_terms( [
			'taxonomy'   => $type,
			'hide_empty' => true,
			'number'     => $per_page,
			'offset'     => $offset,
			'orderby'    => 'count',
			'order'      => 'DESC',
		] );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$urls = [];

		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}

			// Exclude terms marked as noindex.
			$noindex = get_term_meta( $term->term_id, '_mmseo_noindex', true );
			if ( '1' === $noindex ) {
				continue;
			}

			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				continue;
			}

			$urls[] = [
				'loc'     => $link,
				'lastmod' => null,
				'images'  => [],
			];
		}

		return $urls;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Get all public taxonomies enabled in sitemap settings.
	 *
	 * @return string[]
	 */
	private function get_enabled_taxonomies(): array {
		$all_taxonomies = get_taxonomies( [ 'public' => true ], 'names' );
		$enabled        = [];

		foreach ( $all_taxonomies as $tax ) {
			$settings = Options::title_for( 'taxonomies', $tax );

			// Skip if taxonomy is noindexed.
			if ( ! empty( $settings['noindex'] ) ) {
				continue;
			}

			// Check in_sitemap — default true for taxonomies (not explicitly stored in OPTIONS but respected if set).
			if ( isset( $settings['in_sitemap'] ) && false === $settings['in_sitemap'] ) {
				continue;
			}

			$enabled[] = $tax;
		}

		return $enabled;
	}

	/**
	 * Count public terms for a taxonomy (non-empty).
	 *
	 * @param string $tax Taxonomy slug.
	 * @return int
	 */
	private function count_terms( string $tax ): int {
		$count = wp_count_terms( [
			'taxonomy'   => $tax,
			'hide_empty' => true,
		] );

		if ( is_wp_error( $count ) ) {
			return 0;
		}

		return (int) $count;
	}
}
