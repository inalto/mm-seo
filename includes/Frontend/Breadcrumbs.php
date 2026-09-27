<?php
namespace MMSEO\Frontend;

use MMSEO\Meta;
use MMSEO\Modules\Module;
use MMSEO\Options;

defined( 'ABSPATH' ) || exit;

/**
 * Breadcrumbs module.
 *
 * Provides:
 *  - static get_trail(): array  — structured trail data (used by Schema.php)
 *  - static render(): string    — HTML <nav> output
 *  - [mmseo_breadcrumbs] shortcode
 *  - mm-seo/breadcrumbs dynamic block
 *  - mmseo_breadcrumbs() global helper function (after this class)
 */
class Breadcrumbs extends Module {

	/**
	 * Return the module id.
	 *
	 * @return string
	 */
	public function id(): string {
		return 'breadcrumbs';
	}

	/**
	 * Register shortcode and block.
	 *
	 * These are registered unconditionally (shortcode is explicit user placement,
	 * not duplicate meta output). Frontend suspension does not gate these.
	 *
	 * @return void
	 */
	public function register(): void {
		// Shortcode [mmseo_breadcrumbs].
		add_shortcode( 'mmseo_breadcrumbs', [ self::class, 'render' ] );

		// Dynamic block mm-seo/breadcrumbs.
		if ( function_exists( 'register_block_type' ) ) {
			register_block_type(
				'mm-seo/breadcrumbs',
				[
					'api_version'     => 3,
					'title'           => __( 'MM SEO Breadcrumbs', 'mm-seo' ),
					'category'        => 'widgets',
					'render_callback' => [ self::class, 'render' ],
				]
			);
		}
	}

	// -------------------------------------------------------------------------
	// Trail builder
	// -------------------------------------------------------------------------

	/**
	 * Build the breadcrumb trail for the current page.
	 *
	 * Each item: ['url' => string, 'label' => string]
	 * The last item has 'url' => '' (no link — current page).
	 *
	 * @return array<int, array{url: string, label: string}>
	 */
	public static function get_trail(): array {
		$trail = [];

		// Home item.
		$home_label = (string) apply_filters(
			'mmseo_breadcrumbs_home_label',
			Options::get( 'breadcrumbs_home_label', __( 'Home', 'mm-seo' ) )
		);
		$trail[] = [
			'url'   => trailingslashit( home_url() ),
			'label' => $home_label,
		];

		if ( is_front_page() ) {
			// Only home, mark as current (url = '').
			$trail[0]['url'] = '';
			return $trail;
		}

		if ( is_home() ) {
			// Blog posts page.
			$page_for_posts = (int) get_option( 'page_for_posts' );
			$label          = $page_for_posts ? get_the_title( $page_for_posts ) : __( 'Blog', 'mm-seo' );
			$trail[]        = [ 'url' => '', 'label' => $label ];
			return $trail;
		}

		if ( is_singular() ) {
			$trail = array_merge( $trail, self::build_singular_trail() );
			return $trail;
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$trail = array_merge( $trail, self::build_term_trail() );
			return $trail;
		}

		if ( is_author() ) {
			$author = get_queried_object();
			$label  = $author instanceof \WP_User
				? $author->display_name
				: __( 'Author', 'mm-seo' );
			$trail[] = [ 'url' => '', 'label' => $label ];
			return $trail;
		}

		if ( is_date() ) {
			if ( is_year() ) {
				$trail[] = [ 'url' => '', 'label' => get_the_date( 'Y' ) ];
			} elseif ( is_month() ) {
				$trail[] = [
					'url'   => get_year_link( get_the_date( 'Y' ) ),
					'label' => get_the_date( 'Y' ),
				];
				$trail[] = [ 'url' => '', 'label' => get_the_date( 'F Y' ) ];
			} else {
				$trail[] = [
					'url'   => get_year_link( get_the_date( 'Y' ) ),
					'label' => get_the_date( 'Y' ),
				];
				$trail[] = [
					'url'   => get_month_link( get_the_date( 'Y' ), get_the_date( 'm' ) ),
					'label' => get_the_date( 'F Y' ),
				];
				$trail[] = [ 'url' => '', 'label' => get_the_date() ];
			}
			return $trail;
		}

		if ( is_search() ) {
			/* translators: %s: search query */
			$label   = sprintf( __( 'Search: %s', 'mm-seo' ), get_search_query() );
			$trail[] = [ 'url' => '', 'label' => $label ];
			return $trail;
		}

		if ( is_404() ) {
			$trail[] = [ 'url' => '', 'label' => __( 'Page not found', 'mm-seo' ) ];
			return $trail;
		}

		if ( is_post_type_archive() ) {
			$pt_obj = get_queried_object();
			$label  = $pt_obj instanceof \WP_Post_Type
				? ( $pt_obj->labels->name ?? $pt_obj->name )
				: get_the_archive_title();
			$trail[] = [ 'url' => '', 'label' => $label ];
			return $trail;
		}

		return $trail;
	}

	// -------------------------------------------------------------------------
	// Renderer
	// -------------------------------------------------------------------------

	/**
	 * Render the breadcrumb HTML.
	 *
	 * Can be used as a shortcode render callback (extra args are ignored).
	 *
	 * @return string HTML string.
	 */
	public static function render(): string {
		$trail = self::get_trail();

		if ( count( $trail ) <= 1 ) {
			return '';
		}

		$separator = esc_html( Options::get( 'separator', '/' ) );
		$total     = count( $trail );
		$items     = '';

		foreach ( $trail as $index => $crumb ) {
			$is_last  = ( $index === $total - 1 );
			$label    = esc_html( $crumb['label'] );
			$url      = esc_url( $crumb['url'] );

			if ( $is_last ) {
				$items .= '<li class="mmseo-breadcrumb-item mmseo-breadcrumb-current" aria-current="page">'
					. '<span>' . $label . '</span>'
					. '</li>';
			} else {
				$items .= '<li class="mmseo-breadcrumb-item">'
					. '<a href="' . $url . '">' . $label . '</a>'
					. '<span class="mmseo-breadcrumb-sep" aria-hidden="true">' . $separator . '</span>'
					. '</li>';
			}
		}

		return '<nav class="mmseo-breadcrumbs" aria-label="' . esc_attr__( 'breadcrumb', 'mm-seo' ) . '">'
			. '<ol class="mmseo-breadcrumb-list">'
			. $items
			. '</ol>'
			. '</nav>';
	}

	// -------------------------------------------------------------------------
	// Private trail helpers
	// -------------------------------------------------------------------------

	/**
	 * Build trail segments for singular posts.
	 *
	 * @return array<int, array{url: string, label: string}>
	 */
	private static function build_singular_trail(): array {
		$post   = get_queried_object();
		$trail  = [];

		if ( ! $post instanceof \WP_Post ) {
			return $trail;
		}

		// Post type archive link (if the post type has one).
		$pt_obj = get_post_type_object( $post->post_type );
		if ( $pt_obj && $pt_obj->has_archive ) {
			$archive_url = get_post_type_archive_link( $post->post_type );
			if ( $archive_url ) {
				$trail[] = [
					'url'   => $archive_url,
					'label' => $pt_obj->labels->name ?? $pt_obj->name,
				];
			}
		}

		// Primary taxonomy term chain.
		$term_chain = self::get_primary_term_chain( $post );
		if ( ! empty( $term_chain ) ) {
			$trail = array_merge( $trail, $term_chain );
		}

		// Current post (no URL — last item).
		$custom_label = Meta::get_post( $post->ID, 'breadcrumb_title' );
		$label        = '' !== $custom_label ? $custom_label : get_the_title( $post );
		$trail[]      = [ 'url' => '', 'label' => $label ];

		return $trail;
	}

	/**
	 * Build trail segments for term (taxonomy) archives.
	 *
	 * @return array<int, array{url: string, label: string}>
	 */
	private static function build_term_trail(): array {
		$term  = get_queried_object();
		$trail = [];

		if ( ! $term instanceof \WP_Term ) {
			return $trail;
		}

		// Ancestor chain (top-down).
		$ancestors = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $term->taxonomy );
			if ( $ancestor instanceof \WP_Term ) {
				$ancestor_url = get_term_link( $ancestor );
				$trail[]      = [
					'url'   => ! is_wp_error( $ancestor_url ) ? $ancestor_url : '',
					'label' => $ancestor->name,
				];
			}
		}

		// Current term (no URL).
		$trail[] = [ 'url' => '', 'label' => $term->name ];

		return $trail;
	}

	/**
	 * Get the primary term ancestor chain for a post.
	 *
	 * Primary term: _mmseo_primary_{tax} meta, else first assigned term.
	 * Only uses the primary taxonomy for each post type:
	 *   - 'post' → 'category'
	 *   - other types → first hierarchical public taxonomy
	 *
	 * @param \WP_Post $post
	 * @return array<int, array{url: string, label: string}>
	 */
	private static function get_primary_term_chain( \WP_Post $post ): array {
		$trail = [];

		// Determine which taxonomy to use.
		$tax = self::get_primary_taxonomy( $post->post_type );
		if ( null === $tax ) {
			return $trail;
		}

		// Look up primary term via meta, then fall back to first assigned term.
		$primary_id = (int) Meta::get_post( $post->ID, 'primary_' . $tax );
		$term       = null;

		if ( $primary_id > 0 ) {
			$t = get_term( $primary_id, $tax );
			if ( $t instanceof \WP_Term ) {
				$term = $t;
			}
		}

		if ( null === $term ) {
			$terms = get_the_terms( $post->ID, $tax );
			if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term = $terms[0];
			}
		}

		if ( null === $term ) {
			return $trail;
		}

		// Ancestors (top-down) then the term itself.
		$ancestors = array_reverse( get_ancestors( $term->term_id, $tax, 'taxonomy' ) );
		foreach ( $ancestors as $ancestor_id ) {
			$ancestor = get_term( $ancestor_id, $tax );
			if ( $ancestor instanceof \WP_Term ) {
				$url     = get_term_link( $ancestor );
				$trail[] = [
					'url'   => ! is_wp_error( $url ) ? $url : '',
					'label' => $ancestor->name,
				];
			}
		}

		// The primary term itself (with link — it's not the current page).
		$term_url = get_term_link( $term );
		$trail[]  = [
			'url'   => ! is_wp_error( $term_url ) ? $term_url : '',
			'label' => $term->name,
		];

		return $trail;
	}

	/**
	 * Return the primary taxonomy for a post type for breadcrumb purposes.
	 *
	 * @param string $post_type Post type slug.
	 * @return string|null Taxonomy slug or null if none.
	 */
	private static function get_primary_taxonomy( string $post_type ): ?string {
		// Posts → category (standard).
		if ( 'post' === $post_type ) {
			return 'category';
		}

		// For other types, find the first hierarchical public taxonomy.
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxonomies as $tax_obj ) {
			if ( $tax_obj->hierarchical && $tax_obj->public ) {
				return $tax_obj->name;
			}
		}

		// Fallback: first public taxonomy.
		foreach ( $taxonomies as $tax_obj ) {
			if ( $tax_obj->public ) {
				return $tax_obj->name;
			}
		}

		return null;
	}
}

// =========================================================================
// Global helper function
// =========================================================================

if ( ! function_exists( 'mmseo_breadcrumbs' ) ) {
	/**
	 * Output or return the MM SEO breadcrumb trail.
	 *
	 * @param bool $echo Whether to echo (true) or return (false). Default true.
	 * @return string|void HTML string when $echo is false.
	 */
	function mmseo_breadcrumbs( bool $echo = true ) {
		$html = \MMSEO\Frontend\Breadcrumbs::render();
		if ( $echo ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $html;
		} else {
			return $html;
		}
	}
}
