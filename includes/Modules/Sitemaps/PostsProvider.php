<?php
namespace MMSEO\Modules\Sitemaps;

use MMSEO\ContentExtractor;
use MMSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Sitemap provider for public post types.
 *
 * One sitemap per enabled public post type; excludes noindexed posts and types.
 * Images per URL: featured image + content images (up to 20 per post).
 */
class PostsProvider extends Provider {

	/**
	 * Maximum images per post in sitemap.
	 */
	private const MAX_IMAGES = 20;

	/**
	 * Return sitemap slugs: one per enabled public post type.
	 *
	 * @return string[]
	 */
	public function types(): array {
		$types = [];

		foreach ( $this->get_enabled_post_types() as $pt ) {
			$types[] = $pt;
		}

		return $types;
	}

	/**
	 * Return index entries (one per type, with lastmod from the most recent post).
	 *
	 * @return array<int, array{loc: string, lastmod: string|null}>
	 */
	public function index_entries(): array {
		$entries = [];

		foreach ( $this->get_enabled_post_types() as $pt ) {
			$count = $this->count_posts( $pt );
			if ( 0 === $count ) {
				continue;
			}

			$per_page = $this->per_page();
			$pages    = (int) ceil( $count / $per_page );

			for ( $p = 1; $p <= $pages; $p++ ) {
				$loc = $p > 1
					? home_url( '/' . $pt . '-sitemap' . $p . '.xml' )
					: home_url( '/' . $pt . '-sitemap.xml' );

				$lastmod = null;
				if ( 1 === $p ) {
					$lastmod = $this->get_type_lastmod( $pt );
				}

				$entries[] = [
					'loc'     => $loc,
					'lastmod' => $lastmod,
				];
			}
		}

		return $entries;
	}

	/**
	 * Return URL entries for a specific post type sitemap page.
	 *
	 * @param string $type Post type slug.
	 * @param int    $page 1-based page number.
	 * @return array<int, array{loc: string, lastmod: string|null, images: array}>
	 */
	public function page( string $type, int $page ): array {
		if ( ! post_type_exists( $type ) ) {
			return [];
		}

		// Verify this type is enabled.
		$enabled_types = $this->get_enabled_post_types();
		if ( ! in_array( $type, $enabled_types, true ) ) {
			return [];
		}

		$per_page = $this->per_page();
		$offset   = ( $page - 1 ) * $per_page;

		$query = new \WP_Query( [
			'post_type'              => $type,
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'offset'                 => $offset,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
			// Exclude posts with _mmseo_noindex = '1'.
			'meta_query'             => [
				'relation' => 'OR',
				[
					'key'     => '_mmseo_noindex',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_mmseo_noindex',
					'value'   => '1',
					'compare' => '!=',
				],
			],
		] );

		if ( empty( $query->posts ) ) {
			return [];
		}

		$urls = [];

		foreach ( $query->posts as $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			if ( ! $post instanceof \WP_Post ) {
				continue;
			}

			$loc     = get_permalink( $post );
			$lastmod = $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt
				? date( DATE_W3C, strtotime( $post->post_modified_gmt . ' GMT' ) )
				: null;

			$images = $this->collect_images( $post );

			$urls[] = [
				'loc'     => $loc,
				'lastmod' => $lastmod,
				'images'  => $images,
			];
		}

		return $urls;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Get all public post types enabled in sitemap settings.
	 *
	 * @return string[]
	 */
	private function get_enabled_post_types(): array {
		$all_types = get_post_types( [ 'public' => true ], 'names' );
		$enabled   = [];

		foreach ( $all_types as $pt ) {
			if ( 'attachment' === $pt ) {
				continue;
			}

			$settings = Options::title_for( 'post_types', $pt );

			// Skip if explicitly excluded from sitemap.
			if ( isset( $settings['in_sitemap'] ) && false === $settings['in_sitemap'] ) {
				continue;
			}

			// Skip if entire post type is noindexed.
			if ( ! empty( $settings['noindex'] ) ) {
				continue;
			}

			$enabled[] = $pt;
		}

		return $enabled;
	}

	/**
	 * Count published posts for a post type (excluding noindexed posts).
	 *
	 * @param string $pt Post type slug.
	 * @return int
	 */
	private function count_posts( string $pt ): int {
		$query = new \WP_Query( [
			'post_type'              => $pt,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
			'meta_query'             => [
				'relation' => 'OR',
				[
					'key'     => '_mmseo_noindex',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_mmseo_noindex',
					'value'   => '1',
					'compare' => '!=',
				],
			],
		] );

		return (int) $query->found_posts;
	}

	/**
	 * Get the lastmod date for the most recent post of a type.
	 *
	 * @param string $pt Post type slug.
	 * @return string|null W3C date or null.
	 */
	private function get_type_lastmod( string $pt ): ?string {
		$query = new \WP_Query( [
			'post_type'              => $pt,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'fields'                 => 'ids',
		] );

		if ( empty( $query->posts ) ) {
			return null;
		}

		$post = get_post( (int) $query->posts[0] );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		return $post->post_modified_gmt && '0000-00-00 00:00:00' !== $post->post_modified_gmt
			? date( DATE_W3C, strtotime( $post->post_modified_gmt . ' GMT' ) )
			: null;
	}

	/**
	 * Collect up to MAX_IMAGES images for a post (featured + content).
	 *
	 * @param \WP_Post $post
	 * @return array<int, array{src: string, title: string}>
	 */
	private function collect_images( \WP_Post $post ): array {
		$images = [];

		// Featured image first.
		$thumb_id = get_post_thumbnail_id( $post->ID );
		if ( $thumb_id ) {
			$src = wp_get_attachment_image_url( (int) $thumb_id, 'full' );
			if ( $src ) {
				$alt     = (string) get_post_meta( (int) $thumb_id, '_wp_attachment_image_alt', true );
				$images[] = [
					'src'   => $src,
					'title' => $alt ?: get_the_title( (int) $thumb_id ),
				];
			}
		}

		// Content images.
		if ( count( $images ) < self::MAX_IMAGES ) {
			$content_images = ContentExtractor::extract_images( $post->post_content );
			foreach ( $content_images as $img ) {
				if ( count( $images ) >= self::MAX_IMAGES ) {
					break;
				}
				if ( empty( $img['src'] ) ) {
					continue;
				}

				// Resolve relative src.
				$src = $img['src'];
				if ( ! preg_match( '#^https?://#i', $src ) ) {
					$src = home_url( ltrim( $src, '/' ) );
				}

				// Deduplicate.
				$already = false;
				foreach ( $images as $existing ) {
					if ( $existing['src'] === $src ) {
						$already = true;
						break;
					}
				}
				if ( $already ) {
					continue;
				}

				$images[] = [
					'src'   => $src,
					'title' => $img['alt'] ?? '',
				];
			}
		}

		return $images;
	}
}
