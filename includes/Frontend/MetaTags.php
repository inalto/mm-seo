<?php
namespace MMSEO\Frontend;

use MMSEO\ContentExtractor;
use MMSEO\Meta;
use MMSEO\Options;
use MMSEO\Replacer;

defined( 'ABSPATH' ) || exit;

/**
 * Generates meta description, canonical URL, robots directives, and rel prev/next.
 * Methods are called by Head::output().
 */
class MetaTags {

	/**
	 * Build the meta description for the current page.
	 *
	 * Priority: custom meta → template desc → auto-generated.
	 *
	 * @return string Unescaped description string (escape on output).
	 */
	public function description(): string {
		$desc = '';

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( ! $post instanceof \WP_Post ) {
				return '';
			}
			$context = [ 'post' => $post ];

			// 1. Custom meta.
			$desc = Meta::get_post( $post->ID, 'desc' );
			if ( '' !== $desc ) {
				$desc = Replacer::replace( $desc, $context );
			}

			// 2. Post-type template desc.
			if ( '' === $desc ) {
				$settings = Options::title_for( 'post_types', $post->post_type );
				if ( ! empty( $settings['desc'] ) ) {
					$desc = Replacer::replace( $settings['desc'], $context );
				}
			}

			// 3. Auto-generate from content.
			if ( '' === $desc ) {
				$extracted = ContentExtractor::extract( $post->post_content );
				$raw       = '' !== $extracted['first_paragraph']
					? $extracted['first_paragraph']
					: $extracted['text'];
				$trimmed   = mb_substr( $raw, 0, 156 );
				/**
				 * Filter the auto-generated meta description.
				 *
				 * @param string   $trimmed Trimmed plain text.
				 * @param \WP_Post $post    Current post.
				 */
				$desc = (string) apply_filters( 'mmseo_generated_description', $trimmed, $post );
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( ! $term instanceof \WP_Term ) {
				return '';
			}
			$context = [ 'term' => $term ];

			// 1. Term meta desc.
			$desc = Meta::get_term( $term->term_id, 'desc' );
			if ( '' !== $desc ) {
				$desc = Replacer::replace( $desc, $context );
			}

			// 2. Taxonomy template desc.
			if ( '' === $desc ) {
				$settings = Options::title_for( 'taxonomies', $term->taxonomy );
				if ( ! empty( $settings['desc'] ) ) {
					$desc = Replacer::replace( $settings['desc'], $context );
				}
			}
		} elseif ( is_front_page() || is_home() ) {
			$titles = Options::titles();
			$desc   = $titles['home']['desc'] ?? '';
			if ( '' === $desc ) {
				$desc = get_bloginfo( 'description' );
			}
		}

		/**
		 * Filter the final meta description.
		 *
		 * @param string $desc Description string (unescaped).
		 */
		return (string) apply_filters( 'mmseo_description', $desc );
	}

	/**
	 * Build the canonical URL for the current page.
	 *
	 * @return string Canonical URL or empty string.
	 */
	public function canonical(): string {
		$canonical = '';

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$canonical = Meta::get_post( $post->ID, 'canonical' );
				if ( '' === $canonical ) {
					$canonical = (string) get_permalink( $post );
					// Paged canonical.
					$page = (int) get_query_var( 'page' );
					if ( $page > 1 ) {
						$canonical = trailingslashit( $canonical ) . $page . '/';
					}
				}
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$canonical = Meta::get_term( $term->term_id, 'canonical' );
				if ( '' === $canonical ) {
					$canonical = (string) get_term_link( $term );
					if ( is_wp_error( $canonical ) ) {
						$canonical = '';
					}
				}
			}
		} elseif ( is_front_page() ) {
			$canonical = home_url( '/' );
		} elseif ( is_home() ) {
			$page      = (int) get_option( 'page_for_posts' );
			$canonical = $page ? (string) get_permalink( $page ) : home_url( '/' );
		} elseif ( is_author() || is_date() || is_post_type_archive() ) {
			$paged = (int) get_query_var( 'paged' );
			$canonical = $paged > 1 ? (string) get_pagenum_link( $paged ) : (string) get_pagenum_link( 1 );
		}

		/**
		 * Filter the canonical URL.
		 *
		 * @param string $canonical Canonical URL.
		 */
		return (string) apply_filters( 'mmseo_canonical', $canonical );
	}

	/**
	 * Filter callback for wp_robots: add MM SEO robot directives.
	 *
	 * @param array<string, bool|string> $robots Current robots array.
	 * @return array<string, bool|string>
	 */
	public function robots( array $robots ): array {
		$noindex  = false;
		$nofollow = false;
		$adv      = '';

		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof \WP_Post ) {
				$meta_noindex  = Meta::get_post( $post->ID, 'noindex' );
				$meta_nofollow = Meta::get_post( $post->ID, 'nofollow' );
				$adv           = Meta::get_post( $post->ID, 'robots_adv' );

				$noindex  = ( '1' === $meta_noindex );
				$nofollow = ( '1' === $meta_nofollow );

				// Also check post-type level noindex.
				if ( ! $noindex ) {
					$pt_settings = Options::title_for( 'post_types', $post->post_type );
					$noindex     = ! empty( $pt_settings['noindex'] );
				}
			}
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$meta_noindex = Meta::get_term( $term->term_id, 'noindex' );
				$noindex      = ( '1' === $meta_noindex );

				if ( ! $noindex ) {
					$tax_settings = Options::title_for( 'taxonomies', $term->taxonomy );
					$noindex      = ! empty( $tax_settings['noindex'] );
				}
			}
		} elseif ( is_date() ) {
			$date_settings = Options::title_for( 'archives', 'date' );
			$noindex       = ! empty( $date_settings['noindex'] );
		} elseif ( is_search() ) {
			$search_settings = Options::title_for( 'archives', 'search' );
			$noindex         = ! empty( $search_settings['noindex'] );
		} elseif ( is_author() ) {
			$author_settings = Options::title_for( 'archives', 'author' );
			$noindex         = ! empty( $author_settings['noindex'] );
		} elseif ( is_404() ) {
			$noindex = true;
		}

		if ( $noindex ) {
			$robots['noindex'] = true;
			unset( $robots['index'] );
		}

		if ( $nofollow ) {
			$robots['nofollow'] = true;
			unset( $robots['follow'] );
		}

		// Advanced robots directives from meta.
		if ( '' !== $adv ) {
			foreach ( explode( ',', $adv ) as $directive ) {
				$directive = trim( $directive );
				if ( '' !== $directive ) {
					$robots[ $directive ] = true;
				}
			}
		}

		// Add max-image-preview=large when page is indexable.
		if ( empty( $robots['noindex'] ) ) {
			$robots['max-image-preview'] = 'large';
		}

		return $robots;
	}

	/**
	 * Get prev/next URLs for paginated archives.
	 *
	 * @return array{prev: string, next: string}
	 */
	public function prev_next(): array {
		global $wp_query;

		$paged    = (int) get_query_var( 'paged' );
		$max      = isset( $wp_query ) ? (int) $wp_query->max_num_pages : 0;
		$prev_url = '';
		$next_url = '';

		if ( $paged > 1 ) {
			$prev_url = (string) get_pagenum_link( $paged - 1 );
		}

		if ( $paged < $max ) {
			$next_url = (string) get_pagenum_link( $paged + 1 );
		} elseif ( 0 === $paged && $max > 1 ) {
			// First page (paged=0 means page 1).
			$next_url = (string) get_pagenum_link( 2 );
		}

		return [
			'prev' => $prev_url,
			'next' => $next_url,
		];
	}
}
