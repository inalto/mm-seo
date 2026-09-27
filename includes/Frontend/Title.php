<?php
namespace MMSEO\Frontend;

use MMSEO\Meta;
use MMSEO\Options;
use MMSEO\Replacer;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the document <title> tag via pre_get_document_title (priority 15).
 */
class Title {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_filter( 'pre_get_document_title', [ $this, 'build' ], 15 );
		add_filter( 'document_title_separator', [ $this, 'separator' ] );
	}

	/**
	 * Build the page title from MM SEO meta or title templates.
	 *
	 * Returns '' to let WordPress core handle cases that are not covered.
	 * Never returns null (WP filter expects string|null since WP 5.8, but
	 * returning '' is always safe and more explicit).
	 *
	 * @param string $title Current title from core.
	 * @return string
	 */
	public function build( string $title ): string {
		$context = [];

		// -----------------------------------------------------------------------
		// Singular posts / pages.
		// -----------------------------------------------------------------------
		if ( is_singular() ) {
			$post    = get_queried_object();
			if ( ! $post instanceof \WP_Post ) {
				return '';
			}
			$context['post'] = $post;

			// 1. Custom meta title.
			$meta_title = Meta::get_post( $post->ID, 'title' );
			if ( '' !== $meta_title ) {
				return $this->apply_filter(
					Replacer::replace( $meta_title, $context )
				);
			}

			// 2. Post-type template.
			$pt_settings = Options::title_for( 'post_types', $post->post_type );
			if ( ! empty( $pt_settings['title'] ) ) {
				return $this->apply_filter(
					Replacer::replace( $pt_settings['title'], $context )
				);
			}

			return '';
		}

		// -----------------------------------------------------------------------
		// Term archives (category, tag, custom taxonomy).
		// -----------------------------------------------------------------------
		if ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			if ( ! $term instanceof \WP_Term ) {
				return '';
			}
			$context['term'] = $term;

			// 1. Custom term meta title.
			$meta_title = Meta::get_term( $term->term_id, 'title' );
			if ( '' !== $meta_title ) {
				return $this->apply_filter(
					Replacer::replace( $meta_title, $context )
				);
			}

			// 2. Taxonomy template.
			$tax_settings = Options::title_for( 'taxonomies', $term->taxonomy );
			if ( ! empty( $tax_settings['title'] ) ) {
				return $this->apply_filter(
					Replacer::replace( $tax_settings['title'], $context )
				);
			}

			return '';
		}

		// -----------------------------------------------------------------------
		// Author archives.
		// -----------------------------------------------------------------------
		if ( is_author() ) {
			$settings = Options::title_for( 'archives', 'author' );
			if ( ! empty( $settings['title'] ) ) {
				return $this->apply_filter(
					Replacer::replace( $settings['title'], $context )
				);
			}
			return '';
		}

		// -----------------------------------------------------------------------
		// Date archives.
		// -----------------------------------------------------------------------
		if ( is_date() ) {
			$settings = Options::title_for( 'archives', 'date' );
			if ( ! empty( $settings['title'] ) ) {
				return $this->apply_filter(
					Replacer::replace( $settings['title'], $context )
				);
			}
			return '';
		}

		// -----------------------------------------------------------------------
		// Search results.
		// -----------------------------------------------------------------------
		if ( is_search() ) {
			$settings = Options::title_for( 'archives', 'search' );
			if ( ! empty( $settings['title'] ) ) {
				return $this->apply_filter(
					Replacer::replace( $settings['title'], $context )
				);
			}
			return '';
		}

		// -----------------------------------------------------------------------
		// 404 page.
		// -----------------------------------------------------------------------
		if ( is_404() ) {
			$settings = Options::title_for( 'archives', '404' );
			if ( ! empty( $settings['title'] ) ) {
				return $this->apply_filter(
					Replacer::replace( $settings['title'], $context )
				);
			}
			return '';
		}

		// -----------------------------------------------------------------------
		// Post-type archives.
		// -----------------------------------------------------------------------
		if ( is_post_type_archive() ) {
			$pt = get_query_var( 'post_type' );
			if ( is_array( $pt ) ) {
				$pt = reset( $pt );
			}
			$pt_settings = Options::title_for( 'post_types', (string) $pt );
			// Use a separate archive template key if present, else generic.
			$archive_settings = Options::title_for( 'archives', 'post_type_archive_' . $pt );
			$use_settings     = ! empty( $archive_settings['title'] ) ? $archive_settings : $pt_settings;
			if ( ! empty( $use_settings['title'] ) ) {
				return $this->apply_filter(
					Replacer::replace( $use_settings['title'], $context )
				);
			}
			return '';
		}

		// -----------------------------------------------------------------------
		// Front page (static or blog home).
		// -----------------------------------------------------------------------
		if ( is_front_page() || is_home() ) {
			$titles = Options::titles();
			if ( ! empty( $titles['home']['title'] ) ) {
				return $this->apply_filter(
					Replacer::replace( $titles['home']['title'], $context )
				);
			}
			return '';
		}

		return '';
	}

	/**
	 * Override the title separator with the one from MM SEO settings.
	 *
	 * @param string $sep Current separator.
	 * @return string
	 */
	public function separator( string $sep ): string {
		$custom = Options::get( 'separator', '' );
		return ( '' !== $custom ) ? $custom : $sep;
	}

	/**
	 * Apply the mmseo_title filter and return the result.
	 *
	 * @param string $title Processed title string.
	 * @return string
	 */
	private function apply_filter( string $title ): string {
		/**
		 * Filter the final MM SEO title string.
		 *
		 * @param string $title Processed title.
		 */
		return (string) apply_filters( 'mmseo_title', $title );
	}
}
