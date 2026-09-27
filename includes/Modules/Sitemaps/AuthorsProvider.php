<?php
namespace MMSEO\Modules\Sitemaps;

use MMSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Sitemap provider for author archives.
 *
 * Includes users who have published posts (for public post types).
 * Excludes authors when author archives are set to noindex in settings.
 * lastmod is omitted (querying last post date per author is expensive for
 * large sites; left as null for simplicity).
 */
class AuthorsProvider extends Provider {

	/**
	 * Return the sitemap slug handled by this provider.
	 *
	 * @return string[]
	 */
	public function types(): array {
		return [ 'author' ];
	}

	/**
	 * Return index entries for the sitemap index.
	 *
	 * @return array<int, array{loc: string, lastmod: string|null}>
	 */
	public function index_entries(): array {
		// Skip if author archives are noindexed.
		if ( $this->author_archives_noindex() ) {
			return [];
		}

		$count = $this->count_authors();
		if ( 0 === $count ) {
			return [];
		}

		$per_page = $this->per_page();
		$pages    = (int) ceil( $count / $per_page );
		$entries  = [];

		for ( $p = 1; $p <= $pages; $p++ ) {
			$loc = $p > 1
				? home_url( '/author-sitemap' . $p . '.xml' )
				: home_url( '/author-sitemap.xml' );

			$entries[] = [
				'loc'     => $loc,
				'lastmod' => null,
			];
		}

		return $entries;
	}

	/**
	 * Return URL entries for the author sitemap page.
	 *
	 * @param string $type Should be 'author'.
	 * @param int    $page 1-based page number.
	 * @return array<int, array{loc: string, lastmod: string|null, images: array}>
	 */
	public function page( string $type, int $page ): array {
		if ( 'author' !== $type ) {
			return [];
		}

		if ( $this->author_archives_noindex() ) {
			return [];
		}

		$per_page = $this->per_page();

		// Get public post types (excluding attachment).
		$public_post_types = array_diff(
			get_post_types( [ 'public' => true ], 'names' ),
			[ 'attachment' ]
		);

		$users = get_users( [
			'has_published_posts' => array_values( $public_post_types ),
			'number'              => $per_page,
			'offset'              => ( $page - 1 ) * $per_page,
			'fields'              => [ 'ID', 'user_login', 'display_name' ],
			'orderby'             => 'ID',
			'order'               => 'ASC',
		] );

		if ( empty( $users ) ) {
			return [];
		}

		$urls = [];

		foreach ( $users as $user ) {
			$author_url = get_author_posts_url( (int) $user->ID );
			if ( empty( $author_url ) ) {
				continue;
			}

			$urls[] = [
				'loc'     => $author_url,
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
	 * Check if author archives are set to noindex.
	 *
	 * @return bool
	 */
	private function author_archives_noindex(): bool {
		$settings = Options::title_for( 'archives', 'author' );
		return ! empty( $settings['noindex'] );
	}

	/**
	 * Count users with published posts.
	 *
	 * @return int
	 */
	private function count_authors(): int {
		$public_post_types = array_diff(
			get_post_types( [ 'public' => true ], 'names' ),
			[ 'attachment' ]
		);

		$users = get_users( [
			'has_published_posts' => array_values( $public_post_types ),
			'fields'              => 'ID',
			'number'              => 9999,
		] );

		return count( $users );
	}
}
